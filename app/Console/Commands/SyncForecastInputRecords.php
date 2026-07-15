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

        $upsertData = [];

        foreach ($records as $record) {
            $cageCode = $record->cage_code;
            $date = $record->date;

            // Breed and flock age from the first active hen in this cage.
            $henInfo = DB::table('hens as h')
                ->join('cage_slots as cs', 'h.cage_slot_id', '=', 'cs.id')
                ->join('cages as c', 'cs.cage_id', '=', 'c.id')
                ->where('c.cage_code', $cageCode)
                ->where('h.is_active', true)
                ->select('h.breed', 'h.flock_age_weeks')
                ->first();

            // Average environmental readings for this cage/date.
            $envInfo = DB::table('environmental_logs as el')
                ->join('cages as c', 'el.cage_id', '=', 'c.id')
                ->where('c.cage_code', $cageCode)
                ->whereDate('el.recorded_at', $date)
                ->select(
                    DB::raw('AVG(el.temperature_c) as temperature_c'),
                    DB::raw('AVG(el.humidity_pct) as humidity_percent')
                )
                ->first();

            // Feed consumption and batch info for this cage/date.
            $feedInfo = DB::table('feed_consumption_logs as fcl')
                ->join('cages as c', 'fcl.cage_id', '=', 'c.id')
                ->leftJoin('feed_batches as fb', 'fcl.feed_batch_id', '=', 'fb.id')
                ->where('c.cage_code', $cageCode)
                ->where('fcl.log_date', $date)
                ->select(
                    'fcl.feed_consumed_kg',
                    'fb.crude_protein as crude_protein_percent'
                )
                ->first();

            // Mortality count for this cage/date.
            $mortalityInfo = DB::table('mortality_logs as ml')
                ->join('cages as c', 'ml.cage_id', '=', 'c.id')
                ->where('c.cage_code', $cageCode)
                ->where('ml.log_date', $date)
                ->select(DB::raw('SUM(ml.count) as mortality_count'))
                ->first();

            $upsertData[] = [
                'date' => $date,
                'cage_code' => $cageCode,
                'breed' => $henInfo->breed ?? null,
                'flock_age_weeks' => $henInfo->flock_age_weeks ?? null,
                'hen_count' => (int) $record->hen_count,
                'egg_count' => (int) $record->egg_count,
                'temperature_c' => $envInfo->temperature_c !== null ? round($envInfo->temperature_c, 2) : null,
                'humidity_percent' => $envInfo->humidity_percent !== null ? round($envInfo->humidity_percent, 2) : null,
                'crude_protein_percent' => $feedInfo?->crude_protein_percent !== null ? round($feedInfo->crude_protein_percent, 2) : null,
                'feed_consumed_kg' => $feedInfo?->feed_consumed_kg !== null ? round($feedInfo->feed_consumed_kg, 2) : null,
                'mortality_count' => (int) ($mortalityInfo->mortality_count ?? 0),
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
