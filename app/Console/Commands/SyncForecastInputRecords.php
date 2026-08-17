<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncForecastInputRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'forecast:sync-input-records
                            {--cage= : Sync only a specific cage code}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--dry-run : Show what would be synced without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync forecast_input_records from existing farm tables so historical data can continue beyond 90 days.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Base query: aggregate production logs by cage and date.
        $query = DB::table('production_logs as pl')
            ->join('cage_slots as cs', 'pl.cage_slot_id', '=', 'cs.id')
            ->join('cages as c', 'cs.cage_id', '=', 'c.id')
            ->select(
                'pl.log_date as date',
                'c.cage_code',
                DB::raw('SUM(pl.egg_count) as egg_count'),
                DB::raw('SUM(pl.hen_count) as hen_count')
            )
            ->groupBy('pl.log_date', 'c.cage_code')
            ->orderBy('pl.log_date')
            ->orderBy('c.cage_code');

        if ($cage = $this->option('cage')) {
            $query->where('c.cage_code', $cage);
        }

        if ($from = $this->option('from')) {
            $query->where('pl.log_date', '>=', $from);
        }

        if ($to = $this->option('to')) {
            $query->where('pl.log_date', '<=', $to);
        }

        $records = $query->get();

        $this->info("Found {$records->count()} production record groups to sync.");

        if ($records->isEmpty()) {
            $this->warn('No production logs found for the given filters.');
            return self::SUCCESS;
        }

        // The loop below used to run 4 fresh queries per (cage, date) group —
        // one each for hen/env/feed/mortality info. For a full historical
        // sync that's 4N round trips (e.g. 4 cages x 365 days = ~5,840
        // queries in one command run). Replaced with 4 queries total,
        // executed once before the loop, each returning every (cage, date)
        // combination the loop will need, looked up from an in-memory map
        // inside the loop instead of hitting the DB per record.
        //
        // Two of the four keep their original "arbitrary first row per
        // group" semantics (henInfo, feedInfo — the original used ->first()
        // with no aggregate and no ORDER BY, so "first" was already
        // implementation-defined); those are now made deterministic by
        // ordering by id, which is a reasonable interpretation but is a
        // *behavior clarification*, not guaranteed byte-identical to
        // whichever arbitrary row MySQL's un-ordered query planner picked
        // before. The other two (envInfo, mortalityInfo) are real SQL
        // aggregates (AVG/SUM) and are unaffected by this distinction.
        $cageCodes = $records->pluck('cage_code')->unique()->values();
        $minDate = $records->min('date');
        $maxDate = $records->max('date');

        $henByCage = DB::table('hens as h')
            ->join('cage_slots as cs', 'h.cage_slot_id', '=', 'cs.id')
            ->join('cages as c', 'cs.cage_id', '=', 'c.id')
            ->whereIn('c.cage_code', $cageCodes)
            ->where('h.is_active', true)
            ->select('c.cage_code', 'h.breed', 'h.flock_age_weeks')
            ->orderBy('h.id')
            ->get()
            ->groupBy('cage_code')
            ->map(fn ($group) => $group->first());

        $envByCageDate = DB::table('environmental_logs as el')
            ->join('cages as c', 'el.cage_id', '=', 'c.id')
            ->whereIn('c.cage_code', $cageCodes)
            ->whereBetween(DB::raw('DATE(el.recorded_at)'), [$minDate, $maxDate])
            ->selectRaw('c.cage_code, DATE(el.recorded_at) as log_date, AVG(el.temperature_c) as temperature_c, AVG(el.humidity_pct) as humidity_percent')
            ->groupBy('c.cage_code', DB::raw('DATE(el.recorded_at)'))
            ->get()
            ->keyBy(fn ($r) => $r->cage_code . '|' . $r->log_date);

        $feedByCageDate = DB::table('feed_consumption_logs as fcl')
            ->join('cages as c', 'fcl.cage_id', '=', 'c.id')
            ->leftJoin('feed_batches as fb', 'fcl.feed_batch_id', '=', 'fb.id')
            ->whereIn('c.cage_code', $cageCodes)
            ->whereBetween('fcl.log_date', [$minDate, $maxDate])
            ->select('c.cage_code', 'fcl.log_date', 'fcl.feed_consumed_kg', 'fb.crude_protein as crude_protein_percent')
            ->orderBy('fcl.id')
            ->get()
            ->groupBy(fn ($r) => $r->cage_code . '|' . $r->log_date)
            ->map(fn ($group) => $group->first());

        $mortalityByCageDate = DB::table('mortality_logs as ml')
            ->join('cages as c', 'ml.cage_id', '=', 'c.id')
            ->whereIn('c.cage_code', $cageCodes)
            ->whereBetween('ml.log_date', [$minDate, $maxDate])
            ->selectRaw('c.cage_code, ml.log_date, SUM(ml.count) as mortality_count')
            ->groupBy('c.cage_code', 'ml.log_date')
            ->get()
            ->keyBy(fn ($r) => $r->cage_code . '|' . $r->log_date);

        $upsertData = [];

        foreach ($records as $record) {
            $cageCode = $record->cage_code;
            $date = $record->date;
            $key = $cageCode . '|' . $date;

            $henInfo = $henByCage->get($cageCode);
            $envInfo = $envByCageDate->get($key);
            $feedInfo = $feedByCageDate->get($key);
            $mortalityInfo = $mortalityByCageDate->get($key);

            // Nullsafe (?->) throughout: unlike the per-record queries this
            // replaced, a GROUP BY batch simply omits a (cage, date) key
            // entirely when zero underlying rows exist for it — there is no
            // row-with-null-columns the way a single ungrouped aggregate
            // query would return for that same empty case. Every one of
            // these four lookups can legitimately be null (a cage with no
            // active hens, a date with no environmental/feed/mortality
            // logs), which is an entirely normal, expected gap, not an
            // error condition.
            $upsertData[] = [
                'date' => $date,
                'cage_code' => $cageCode,
                'breed' => $henInfo?->breed,
                'flock_age_weeks' => $henInfo?->flock_age_weeks,
                'hen_count' => (int) $record->hen_count,
                'egg_count' => (int) $record->egg_count,
                'temperature_c' => $envInfo?->temperature_c !== null ? round($envInfo->temperature_c, 2) : null,
                'humidity_percent' => $envInfo?->humidity_percent !== null ? round($envInfo->humidity_percent, 2) : null,
                'crude_protein_percent' => $feedInfo?->crude_protein_percent !== null ? round($feedInfo->crude_protein_percent, 2) : null,
                'feed_consumed_kg' => $feedInfo?->feed_consumed_kg !== null ? round($feedInfo->feed_consumed_kg, 2) : null,
                'mortality_count' => (int) ($mortalityInfo?->mortality_count ?? 0),
                'source_file' => 'synced_from_app_tables',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($dryRun) {
            $this->info('Dry run mode. Would upsert ' . count($upsertData) . ' records.');
            foreach (array_slice($upsertData, 0, 5) as $row) {
                $this->line(json_encode($row));
            }
            return self::SUCCESS;
        }

        DB::table('forecast_input_records')->upsert(
            $upsertData,
            ['date', 'cage_code'],
            [
                'breed',
                'flock_age_weeks',
                'hen_count',
                'egg_count',
                'temperature_c',
                'humidity_percent',
                'crude_protein_percent',
                'feed_consumed_kg',
                'mortality_count',
                'source_file',
                'updated_at',
            ]
        );

        $this->info('Synced ' . count($upsertData) . ' records into forecast_input_records.');
        $this->info('Historical data is now continuous and can grow beyond 90 days.');

        return self::SUCCESS;
    }
}
