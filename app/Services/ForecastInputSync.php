<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ForecastInputSync
{
    /**
     * Run the full sync from production_logs + supporting tables into
     * forecast_input_records. Returns the upserted record count.
     *
     * @param  array{from?: string, to?: string, cage?: string}  $filters
     */
    public static function run(array $filters = [], bool $catchUp = false): int
    {
        $fromOption = $filters['from'] ?? null;

        // Auto-set --from from last sync unless catch-up or explicit --from.
        $lastSyncAt = Setting::get('last_forecast_sync_at');
        if ($fromOption === null && $lastSyncAt && ! $catchUp) {
            $fromOption = now()->parse($lastSyncAt)->addDay()->format('Y-m-d');
        }

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

        if (! empty($filters['cage'])) {
            $query->where('c.cage_code', $filters['cage']);
        }
        if ($fromOption) {
            $query->where('pl.log_date', '>=', $fromOption);
        }
        if (! empty($filters['to'])) {
            $query->where('pl.log_date', '<=', $filters['to']);
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            return 0;
        }

        $cageCodes = $records->pluck('cage_code')->unique()->values();
        $minDate = $records->min('date');
        $maxDate = $records->max('date');
        $maxDateSql = \Carbon\Carbon::parse($maxDate)->addDay()->toDateString();

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

        $envRows = DB::table('environmental_logs as el')
            ->join('cages as c', 'el.cage_id', '=', 'c.id')
            ->whereIn('c.cage_code', $cageCodes)
            ->whereBetween(DB::raw('DATE(el.recorded_at)'), [$minDate, $maxDateSql])
            ->select('c.cage_code', 'el.recorded_at', 'el.temperature_c', 'el.humidity_pct')
            ->get();

        $envByCageDate = $envRows->groupBy(fn ($r) => $r->cage_code)
            ->mapWithKeys(fn ($rows, $cageCode) => [
                $cageCode => $rows->groupBy(fn ($r) => ReportingDateService::reportingDateFor($r->recorded_at)->toDateString())
                    ->map(fn ($group) => (object) [
                        'cage_code' => $cageCode,
                        'log_date' => $group->first()->recorded_at,
                        'temperature_c' => round($group->avg('temperature_c'), 2),
                        'humidity_percent' => round($group->avg('humidity_pct'), 2),
                    ]),
            ])
            ->flatten(1)
            ->keyBy(fn ($r) => $r->cage_code . '|' . ReportingDateService::reportingDateFor($r->log_date)->toDateString());

        $feedRows = DB::table('feed_consumption_logs as fcl')
            ->join('cages as c', 'fcl.cage_id', '=', 'c.id')
            ->leftJoin('feed_batches as fb', 'fcl.feed_batch_id', '=', 'fb.id')
            ->whereIn('c.cage_code', $cageCodes)
            ->whereBetween('fcl.log_date', [$minDate, $maxDateSql])
            ->select('c.cage_code', 'fcl.log_date', 'fcl.feed_consumed_kg', 'fb.crude_protein as crude_protein_percent')
            ->orderBy('fcl.id')
            ->get();

        $feedByCageDate = $feedRows->keyBy(fn ($r) => $r->cage_code . '|' . $r->log_date);

        $mortalityRows = DB::table('mortality_logs as ml')
            ->join('cages as c', 'ml.cage_id', '=', 'c.id')
            ->whereIn('c.cage_code', $cageCodes)
            ->whereBetween('ml.log_date', [$minDate, $maxDateSql])
            ->select('c.cage_code', 'ml.log_date', DB::raw('SUM(ml.count) as mortality_count'))
            ->groupBy('c.cage_code', 'ml.log_date')
            ->get();

        $mortalityByCageDate = $mortalityRows->keyBy(fn ($r) => $r->cage_code . '|' . $r->log_date);

        $upsertData = [];

        foreach ($records as $record) {
            $cageCode = $record->cage_code;
            $date = $record->date;
            $key = $cageCode . '|' . $date;

            $henInfo = $henByCage->get($cageCode);
            $envInfo = $envByCageDate->get($key);
            $feedInfo = $feedByCageDate->get($key);
            $mortalityInfo = $mortalityByCageDate->get($key);

            $temperature = $envInfo?->temperature_c;
            $humidity = $envInfo?->humidity_percent;
            if ($temperature === null || $humidity === null) {
                continue;
            }

            $upsertData[] = [
                'date' => $date,
                'cage_code' => $cageCode,
                'breed' => $henInfo?->breed,
                'flock_age_weeks' => $henInfo?->flock_age_weeks,
                'hen_count' => (int) $record->hen_count,
                'egg_count' => (int) $record->egg_count,
                'temperature_c' => round($temperature, 2),
                'humidity_percent' => round($humidity, 2),
                'crude_protein_percent' => $feedInfo?->crude_protein_percent !== null ? round($feedInfo->crude_protein_percent, 2) : null,
                'feed_consumed_kg' => $feedInfo?->feed_consumed_kg !== null ? round($feedInfo->feed_consumed_kg, 2) : null,
                'mortality_count' => (int) ($mortalityInfo?->mortality_count ?? 0),
                'source_file' => 'synced_from_app_tables',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (empty($upsertData)) {
            return 0;
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

        Setting::set('last_forecast_sync_at', now()->toDateTimeString());

        return count($upsertData);
    }
}
