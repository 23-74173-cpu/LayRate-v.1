<?php

namespace App\Console\Commands;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Services\ReportingDateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportHistoricalProductionCsv extends Command
{
    protected $signature = 'farm:import-historical-csv {--dry-run : Validate and report without writing} {--approved-development-import : Explicitly authorize this reviewed import into local layrate} {--source= : CSV path; defaults to the preferred root-level hen-count CSV when present}';

    protected $description = 'Import reviewed aggregate historical CSV into guarded QA or explicitly approved local layrate.';

    public function handle(): int
    {
        $connection = config('database.default');
        $database = DB::connection($connection)->getDatabaseName();
        $qaTarget = app()->environment('testing') && $connection === 'mysql' && $database === 'layrate_qa_20261027';
        $approvedLocalTarget = app()->environment('local') && $connection === 'mysql' && $database === 'layrate'
            && config('database.connections.mysql.host') === '127.0.0.1'
            && (string) config('database.connections.mysql.port') === '3306';
        if (! $qaTarget && ! ($approvedLocalTarget && $this->option('approved-development-import'))) {
            throw new RuntimeException('Refusing historical CSV import: use QA testing target or explicitly approve exact local layrate target.');
        }

        $rows = $this->readRows();
        $this->validateRows($rows);
        $this->line('Target: '.$database.' ('.app()->environment().')');
        $this->line('Validated CSV rows: '.count($rows));

        if ($this->option('dry-run')) {
            $this->previewExistingRows($rows);
            $this->line('Dry run; no writes performed.');
            return self::SUCCESS;
        }

        $hasSourceFields = \Illuminate\Support\Facades\Schema::hasColumn('production_logs', 'source_key');
        $hasExtendedFields = \Illuminate\Support\Facades\Schema::hasColumn('feed_consumption_logs', 'crude_protein_pct')
            && \Illuminate\Support\Facades\Schema::hasColumn('environmental_logs', 'provenance')
            && \Illuminate\Support\Facades\Schema::hasColumn('cages', 'breed_snapshot');
        if (! $hasSourceFields && (Cage::whereIn('cage_code', ['CAGE-A', 'CAGE-B', 'CAGE-C'])->exists()
            || ProductionLog::whereBetween('log_date', ['2026-03-23', '2026-08-27'])->exists()
            || FeedConsumptionLog::whereBetween('log_date', ['2026-03-23', '2026-08-27'])->exists()
            || EnvironmentalLog::whereBetween('recorded_at', ['2026-03-23 00:00:00', '2026-08-28 00:00:00'])->exists()
            || MortalityLog::whereBetween('log_date', ['2026-03-23', '2026-08-27'])->exists())) {
            throw new RuntimeException('Target is not empty for the prototype scope; refusing to merge with existing rows.');
        }
        if ($hasSourceFields && ! $hasExtendedFields) {
            throw new RuntimeException('Target schema has a partial historical import migration; manual reconciliation is required.');
        }
        if (! $hasSourceFields && $approvedLocalTarget) {
            $tables = ['cages', 'cage_slots', 'hens', 'production_logs', 'egg_size_logs', 'feed_batches', 'feed_consumption_logs', 'farm_feed_entries', 'environmental_logs', 'mortality_logs', 'mortality_log_hens', 'forecasts', 'egg_stock_batches', 'pre_orders', 'finance_transactions'];
            foreach ($tables as $table) {
                if (DB::table($table)->count() !== 0) {
                    throw new RuntimeException("Baseline check failed: {$table} is not empty.");
                }
            }
            if (DB::table('users')->count() !== 2 || DB::table('settings')->count() !== 16) {
                throw new RuntimeException('Users/settings baseline differs from the approved snapshot; refusing import.');
            }
        }
        if ($approvedLocalTarget && $hasSourceFields) {
            foreach ([
                'production_logs' => ['production_data_clean:%:production', 474],
                'feed_consumption_logs' => ['production_data_clean:%:feed', 474],
                'environmental_logs' => ['production_data_clean:%:environment', 474],
                'mortality_logs' => ['production_data_clean:%:mortality', 45],
            ] as $table => [$pattern, $expectedCount]) {
                if (DB::table($table)->where('source_key', 'like', $pattern)->count() !== $expectedCount) {
                    throw new RuntimeException("Existing local historical source rows in {$table} do not match expected count; refusing replacement import.");
                }
            }
            if (Cage::whereIn('cage_code', ['CAGE-A', 'CAGE-B', 'CAGE-C'])->count() !== 3
                || DB::table('cage_slots')->count() !== 132
                || DB::table('hens')->count() !== 0) {
                throw new RuntimeException('Existing local historical cage/slot/individual-hen structure differs; refusing replacement import.');
            }
        }
                if (! $hasExtendedFields && ! $this->option('dry-run')) {
            $migrationName = '2026_10_27_135204_add_historical_source_fields_to_farm_tables';
            if (DB::table('migrations')->where('migration', $migrationName)->exists()) {
                throw new RuntimeException('Migration is recorded but required columns are absent; refusing partial schema state.');
            }
        }
        if ($hasSourceFields) {
            $hasUnmarkedHistory = ProductionLog::whereBetween('log_date', ['2026-03-23', '2026-08-27'])->whereNull('source_key')
                    ->whereHas('cageSlot', fn ($q) => $q->whereHas('cage', fn ($cq) => $cq->whereIn('cage_code', ['CAGE-A', 'CAGE-B', 'CAGE-C'])))->exists()
                || FeedConsumptionLog::whereBetween('log_date', ['2026-03-23', '2026-08-27'])->whereNull('source_key')->whereIn('cage_id', Cage::whereIn('cage_code', ['CAGE-A', 'CAGE-B', 'CAGE-C'])->select('id'))->exists()
                || EnvironmentalLog::whereBetween('recorded_at', ['2026-03-23 00:00:00', '2026-08-28 00:00:00'])->whereNull('source_key')->whereIn('cage_id', Cage::whereIn('cage_code', ['CAGE-A', 'CAGE-B', 'CAGE-C'])->select('id'))->exists()
                || MortalityLog::whereBetween('log_date', ['2026-03-23', '2026-08-27'])->whereNull('source_key')->whereIn('cage_id', Cage::whereIn('cage_code', ['CAGE-A', 'CAGE-B', 'CAGE-C'])->select('id'))->exists();
            if ($hasUnmarkedHistory) throw new RuntimeException('Unmarked history exists in the import scope; refusing to merge.');
        }
        $adminId = null;
        if ($approvedLocalTarget && ! $this->option('dry-run') && ! $hasSourceFields) {
            foreach (['cages', 'cage_slots', 'hens', 'production_logs', 'egg_size_logs', 'feed_batches', 'feed_consumption_logs', 'farm_feed_entries', 'environmental_logs', 'mortality_logs', 'mortality_log_hens', 'forecasts', 'egg_stock_batches', 'pre_orders', 'finance_transactions'] as $table) {
                if (DB::table($table)->count() !== 0) {
                    throw new RuntimeException("Local {$table} is not empty as in the approved baseline; refusing import.");
                }
            }
            if (DB::table('migrations')->where('migration', '2026_10_27_135204_add_historical_source_fields_to_farm_tables')->exists()) {
                throw new RuntimeException('Migration history is inconsistent with the schema; refusing import.');
            }
            if (! $adminId = DB::table('users')->where('role', 'admin')->value('id')) {
                throw new RuntimeException('Approved baseline admin is missing; refusing import.');
            }
        } elseif ($approvedLocalTarget && ! $this->option('dry-run')) {
            foreach ([
                'production_logs' => 'production_data_with_hen_counts:%:production',
                'feed_consumption_logs' => 'production_data_with_hen_counts:%:feed',
                'environmental_logs' => 'production_data_with_hen_counts:%:environment',
                'mortality_logs' => 'production_data_with_hen_counts:%:mortality',
            ] as $table => $pattern) {
                $importedCount = DB::table($table)->where('source_key', 'like', $pattern)->count();
                $expectedCount = match ($table) {
                    'production_logs', 'feed_consumption_logs', 'environmental_logs' => 474,
                    default => 45,
                };
            $cageIds = Cage::whereIn('cage_code', ['CAGE-A', 'CAGE-B', 'CAGE-C'])->select('id');
            $unmarked = DB::table($table)->whereNull('source_key')->whereBetween(
                $table === 'environmental_logs' ? 'recorded_at' : ($table === 'mortality_logs' ? 'log_date' : 'log_date'),
                $table === 'environmental_logs' ? ['2026-03-23 00:00:00', '2026-08-28 00:00:00'] : ['2026-03-23', '2026-08-27']
            )->whereIn($table === 'production_logs' ? 'cage_slot_id' : 'cage_id', $table === 'production_logs'
                ? DB::table('cage_slots')->whereIn('cage_id', $cageIds)->select('id')
                : $cageIds)->exists();
            if ($importedCount !== $expectedCount || $unmarked) {
                    throw new RuntimeException("Idempotent baseline check failed for {$table}; imported-key counts or unmarked scope differ.");
                }
            }
            if (Cage::whereIn('cage_code', ['CAGE-A', 'CAGE-B', 'CAGE-C'])->count() !== 3
                || DB::table('cage_slots')->count() !== 132
                || DB::table('hens')->count() !== 0 || DB::table('egg_size_logs')->count() !== 0
                || DB::table('feed_batches')->count() !== 0 || DB::table('farm_feed_entries')->count() !== 0
                || DB::table('mortality_log_hens')->count() !== 0) {
                throw new RuntimeException('Idempotent structural baseline differs from the approved import; refusing rerun.');
            }
            $adminId = DB::table('users')->where('role', 'admin')->value('id');
            if (! $adminId) throw new RuntimeException('Admin provenance user is missing; refusing rerun.');
        }

        $adminId = DB::table('users')->where('role', 'admin')->value('id') ?? $adminId;
        if (! $adminId) {
            $this->error('An admin user is required before importing; no account will be created or changed by the importer.');
            return self::FAILURE;
        }

        if (! $hasExtendedFields) {
            $this->line('Applying the narrowly scoped historical source-fields migration after validation and authorization checks.');
            if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) return self::FAILURE;
        }

        $counts = DB::transaction(function () use ($rows, $adminId) {
            $cages = [];
            $cagesInserted = $slotsInserted = 0;
            foreach (['CAGE-A' => 60, 'CAGE-B' => 36, 'CAGE-C' => 36] as $code => $slotCount) {
                $cage = Cage::where('cage_code', $code)->first();
                if (! $cage) {
                    $cage = Cage::create([
                    'location' => 'Historical CSV import', 'rows' => 1, 'slots_per_row' => $slotCount,
                    'max_chickens_per_slot' => 4, 'total_capacity' => $slotCount * 4, 'is_active' => true,
                    'breed_snapshot' => 'Dekalb White',
                    'cage_code' => $code,
                    ]);
                    $cagesInserted++;
                } elseif ((int) $cage->slots_per_row !== $slotCount || (int) $cage->rows !== 1 || $cage->breed_snapshot !== 'Dekalb White') {
                    throw new RuntimeException("Existing cage {$code} does not match approved import structure.");
                }
                $cages[$code] = $cage;
                $slotLayout = $cage->cageSlots()->orderBy('slot_number')->pluck('slot_number')->all();
                if ($slotLayout && $slotLayout !== range(1, $slotCount)) {
                    throw new RuntimeException("Existing cage {$code} has a non-approved slot numbering/layout.");
                }
                if (! $cage->cageSlots()->exists()) {
                    for ($slot = 1; $slot <= $slotCount; $slot++) {
                        CageSlot::create([
                            'cage_id' => $cage->id, 'row_number' => 1, 'column_number' => $slot,
                            'slot_number' => $slot, 'current_occupancy' => 0,
                        ]);
                        $slotsInserted++;
                    }
                } elseif ($cage->cageSlots()->count() !== $slotCount) {
                    throw new RuntimeException("Existing cage {$code} has a partial or unexpected slot layout.");
                }
            }

            $productionInserted = $feedInserted = $environmentInserted = $mortalityInserted = 0;
            $productionSkipped = $feedSkipped = $environmentSkipped = $mortalitySkipped = 0;
            foreach ($rows as $row) {
                $cage = $cages[$row['Cage_Code']];
                $slot = $cage->cageSlots()->orderBy('slot_number')->firstOrFail();
                $key = $this->sourceKey($row, 'production');
                $existingProduction = ProductionLog::where('source_key', $key)->first();
                if (! $existingProduction && ProductionLog::where('cage_slot_id', $slot->id)->where('log_date', $row['Date'])->whereNull('source_key')->exists()) {
                    throw new RuntimeException("Unmarked production record conflicts with {$key}; refusing overwrite.");
                }
                if (! $existingProduction && ProductionLog::whereHas('cageSlot', fn ($q) => $q->where('cage_id', $cage->id))
                    ->where('log_date', $row['Date'])->whereNull('source_key')->exists()) {
                    throw new RuntimeException("Production already exists for cage/date represented by {$key}; refusing aggregate duplication.");
                }
                $productionValues = [
                    'cage_slot_id' => $slot->id,
                    'log_date' => $row['Date'],
                    'egg_count' => (int) $row['Egg_Count'],
                    'hen_count' => (int) $row['Hen_Count'],
                    'hdep' => (int) $row['Hen_Count'] > 0 ? round((int) $row['Egg_Count'] / (int) $row['Hen_Count'] * 100, 2) : 0,
                    'notes' => 'CSV aggregate; cage-day snapshot; no per-slot distribution supplied.',
                    'logged_via' => 'unknown',
                    'is_demo' => false,
                    'source_key' => $key,
                    'recorded_by' => $adminId,
                ];
                if (! $existingProduction) {
                    ProductionLog::create($productionValues);
                    $productionInserted++;
                } else {
                    $expected = collect($productionValues)->except('recorded_by')->all();
                    $expected['notes'] = 'CSV aggregate; cage-day snapshot; no per-slot distribution supplied.';
                    foreach ($expected as $attribute => $value) {
                        $actual = $existingProduction->getAttribute($attribute);
                        if ($attribute === 'log_date' && $actual instanceof \DateTimeInterface) {
                            $actual = $actual->format('Y-m-d');
                        }
                        if ($attribute === 'hdep') {
                            $actual = round((float) $actual, 2);
                            $value = round((float) $value, 2);
                        }
                        if ((string) $actual !== (string) $value) {
                            throw new RuntimeException("Existing production conflict for {$key} at {$attribute}; refusing overwrite.");
                        }
                    }
                    $productionSkipped++;
                }
                if ($existingProduction && $existingProduction->eggSizeLogs()->exists()) {
                    throw new RuntimeException("Unexpected egg-size rows exist for aggregate source {$key}; refusing modification.");
                }
                if ($existingProduction && ((int) $existingProduction->cage_slot_id !== (int) $slot->id
                    || $existingProduction->logged_via !== 'unknown' || (bool) $existingProduction->is_demo)) {
                    throw new RuntimeException("Existing production provenance conflict for {$key}; refusing overwrite.");
                }

                $feedKey = $this->sourceKey($row, 'feed');
                $feedExisting = FeedConsumptionLog::where('source_key', $feedKey)->first();
                if (! $feedExisting && FeedConsumptionLog::where('source_key', $feedKey)->exists()) {
                    throw new RuntimeException("Feed source key state is inconsistent for {$feedKey}.");
                }
                if (! $feedExisting && FeedConsumptionLog::where('cage_id', $cage->id)->where('log_date', $row['Date'])->whereNull('source_key')->exists()) {
                    throw new RuntimeException("Unmarked feed record conflicts with {$feedKey}; refusing overwrite.");
                }
                if (! $feedExisting) {
                    FeedConsumptionLog::create([
                        'cage_id' => $cage->id, 'feed_batch_id' => null, 'log_date' => $row['Date'],
                        'feed_consumed_kg' => $row['Feed_Consumed_kg'], 'crude_protein_pct' => $row['Crude_Protein_Percent'], 'recorded_by' => $adminId,
                        'source' => 'direct', 'source_key' => $feedKey,
                    ]);
                    $feedInserted++;
                } elseif ((int) $feedExisting->cage_id !== (int) $cage->id || $feedExisting->log_date->toDateString() !== $row['Date'] || (float) $feedExisting->feed_consumed_kg !== (float) $row['Feed_Consumed_kg'] || (float) $feedExisting->crude_protein_pct !== (float) $row['Crude_Protein_Percent']) {
                    throw new RuntimeException("Existing feed conflict for {$feedKey}; refusing overwrite.");
                } else {
                    $feedSkipped++;
                }
                if ($feedExisting && ($feedExisting->feed_batch_id !== null || $feedExisting->farm_feed_entry_id !== null)) {
                    throw new RuntimeException("Unexpected batch relation on imported aggregate feed {$feedKey}.");
                }

                $environmentKey = $this->sourceKey($row, 'environment');
                $environmentExisting = EnvironmentalLog::where('source_key', $environmentKey)->first();
                if (! $environmentExisting && EnvironmentalLog::where('cage_id', $cage->id)->where('recorded_at', $row['Date'].' 12:00:00')->whereNull('source_key')->exists()) {
                    throw new RuntimeException("Unmarked environment record conflicts with {$environmentKey}; refusing overwrite.");
                }
                if (! $environmentExisting) {
                    EnvironmentalLog::create([
                        'cage_id' => $cage->id, 'recorded_at' => $row['Date'].' 12:00:00',
                        'temperature_c' => $row['Temperature_C'], 'humidity_pct' => $row['Humidity_Percent'],
                        'is_override' => true, 'is_demo' => false, 'provenance' => 'historical_csv_aggregate',
                        'humidity_corrected' => $row['Humidity_Corrected'] === 'Y', 'source_key' => $environmentKey,
                    ]);
                    $environmentInserted++;
                } elseif ((int) $environmentExisting->cage_id !== (int) $cage->id || $environmentExisting->recorded_at->format('Y-m-d H:i:s') !== $row['Date'].' 12:00:00' || abs((float) $environmentExisting->temperature_c - (float) $row['Temperature_C']) > 0.001 || abs((float) $environmentExisting->humidity_pct - (float) $row['Humidity_Percent']) > 0.001 || (bool) $environmentExisting->humidity_corrected !== ($row['Humidity_Corrected'] === 'Y') || $environmentExisting->provenance !== 'historical_csv_aggregate') {
                    throw new RuntimeException("Existing environment conflict for {$environmentKey}; refusing overwrite.");
                } else {
                    $environmentSkipped++;
                }
                if ($environmentExisting && (! (bool) $environmentExisting->is_override || (bool) $environmentExisting->is_demo)) {
                    throw new RuntimeException("Existing environment provenance conflict for {$environmentKey}.");
                }

                $deaths = (int) $row['Mortality_Count'];
                if ($deaths > 0) {
                    $mortalityKey = $this->sourceKey($row, 'mortality');
                    $mortalityExisting = MortalityLog::where('source_key', $mortalityKey)->first();
                    if (! $mortalityExisting && MortalityLog::where('cage_id', $cage->id)->where('log_date', $row['Date'])->whereNull('source_key')->exists()) {
                        throw new RuntimeException("Unmarked mortality record conflicts with {$mortalityKey}; refusing overwrite.");
                    }
                    if (! $mortalityExisting) {
                        MortalityLog::create([
                            'cage_id' => $cage->id, 'log_date' => $row['Date'], 'count' => $deaths,
                            'reason' => 'Unknown', 'notes' => 'Aggregate CSV count; no individual bird/event identity supplied.',
                            'source_key' => $mortalityKey, 'recorded_by' => $adminId,
                        ]);
                        $mortalityInserted++;
                    } else {
                        if ((int) $mortalityExisting->cage_id !== (int) $cage->id || $mortalityExisting->log_date->toDateString() !== $row['Date'] || (int) $mortalityExisting->count !== $deaths) {
                            throw new RuntimeException("Existing mortality conflict for {$mortalityKey}; refusing overwrite.");
                        }
                        if ($mortalityExisting && ($mortalityExisting->reason !== 'Unknown' || ! str_contains((string) $mortalityExisting->notes, 'no individual bird/event identity supplied'))) {
                            throw new RuntimeException("Existing mortality provenance conflict for {$mortalityKey}.");
                        }
                        $mortalitySkipped++;
                    }
                }
            }

            $cagesSkipped = 3 - $cagesInserted;
            $slotsSkipped = 132 - $slotsInserted;
            return compact('cagesInserted', 'cagesSkipped', 'slotsInserted', 'slotsSkipped', 'productionInserted', 'productionSkipped', 'feedInserted', 'feedSkipped', 'environmentInserted', 'environmentSkipped', 'mortalityInserted', 'mortalitySkipped');
        });

        $this->table(['Table', 'Inserted', 'Skipped/existing'], [
            ['cages', $counts['cagesInserted'], $counts['cagesSkipped']], ['cage_slots', $counts['slotsInserted'], $counts['slotsSkipped']],
            ['production_logs', $counts['productionInserted'], $counts['productionSkipped']],
            ['feed_consumption_logs', $counts['feedInserted'], $counts['feedSkipped']], ['environmental_logs', $counts['environmentInserted'], $counts['environmentSkipped']],
            ['mortality_logs', $counts['mortalityInserted'], $counts['mortalitySkipped']],
        ]);

        return self::SUCCESS;
    }

    private function readRows(): array
    {
        $source = $this->option('source') ?: (is_file(base_path('production_data_with_hen_counts.csv'))
            ? base_path('production_data_with_hen_counts.csv')
            : base_path('data/production_data_clean.csv'));
        $handle = fopen($source, 'r');
        if (! $handle) throw new RuntimeException('CSV could not be opened.');
        $headers = fgetcsv($handle);
        $hasFarmHenMetadata = in_array('Farm_Hen_Count_On_Date', $headers, true)
            && in_array('Hen_Count_Record_Type', $headers, true);
        if ($hasFarmHenMetadata) $this->line('Hen counts validated as historical aggregate snapshots; no individual hens will be created.');
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $values);
            if (! $hasFarmHenMetadata) {
                $row['Farm_Hen_Count_On_Date'] = '';
                $row['Hen_Count_Record_Type'] = 'Historical cage-day aggregate (not individual hen inventory)';
            }
            $rows[] = $row;
        }
        fclose($handle);
        $this->line('Source: '.$source);
        return $rows;
    }

    private function validateRows(array $rows): void
    {
        if (count($rows) !== 474) throw new RuntimeException('Expected exactly 474 CSV rows.');
        $keys = [];
        $dailyFarmCounts = [];
        $hasHenMetadata = isset($rows[0]['Farm_Hen_Count_On_Date']) && $rows[0]['Farm_Hen_Count_On_Date'] !== '';
        foreach ($rows as $row) {
            if (! in_array($row['Cage_Code'], ['CAGE-A', 'CAGE-B', 'CAGE-C'], true)) throw new RuntimeException('Unexpected cage mapping.');
            if ($row['Date'] < '2026-03-23' || $row['Date'] > '2026-08-27' || $row['Date'] > ReportingDateService::reportingDateString()) throw new RuntimeException('CSV date is outside approved historical range.');
            $key = $row['Date'].'|'.$row['Cage_Code'];
            if (isset($keys[$key])) throw new RuntimeException('Duplicate cage/day source key: '.$key);
            $keys[$key] = true;
            if ($hasHenMetadata) {
                if (! isset($row['Farm_Hen_Count_On_Date']) || $row['Farm_Hen_Count_On_Date'] === '') {
                    throw new RuntimeException('Farm hen metadata must be present on every CSV row.');
                }
                if (! ctype_digit((string) $row['Hen_Count']) || ! ctype_digit((string) $row['Farm_Hen_Count_On_Date'])) {
                    throw new RuntimeException('Hen counts must be non-negative whole-number aggregate snapshots.');
                }
                $type = trim((string) ($row['Hen_Count_Record_Type'] ?? ''));
                if ($type !== 'Historical cage-day aggregate (not individual hen inventory)') {
                    throw new RuntimeException('Unexpected hen-count record type; refusing to treat aggregate counts as inventory.');
                }
                $dailyFarmCounts[$row['Date']]['farm'] = (int) $row['Farm_Hen_Count_On_Date'];
                $dailyFarmCounts[$row['Date']]['cages'] = ($dailyFarmCounts[$row['Date']]['cages'] ?? 0) + (int) $row['Hen_Count'];
                $dailyFarmCounts[$row['Date']]['rows'] = ($dailyFarmCounts[$row['Date']]['rows'] ?? 0) + 1;
            }
        }
        foreach ($dailyFarmCounts as $date => $counts) {
            if ($counts['rows'] !== 3 || $counts['cages'] !== $counts['farm'] || $counts['farm'] !== 528) {
                throw new RuntimeException("Farm hen snapshot does not reconcile to three cage aggregates on {$date}.");
            }
        }
    }

    private function sourceKey(array $row, string $kind): string
    {
        return 'production_data_clean:'.$row['Date'].':'.$row['Cage_Code'].':'.$kind;
    }

    private function previewExistingRows(array $rows): void
    {
        $differences = [];
        foreach ($rows as $row) {
            $key = $this->sourceKey($row, 'production');
            $production = ProductionLog::where('source_key', $key)->first();
            if (! $production && \Illuminate\Support\Facades\Schema::hasColumn('production_logs', 'source_key')) {
                $legacyKey = 'production_data_clean:'.$row['Date'].':'.$row['Cage_Code'].':production';
                $production = ProductionLog::where('source_key', $legacyKey)->first();
                if ($production) $key = $legacyKey;
            }
            if (! $production) {
                $differences[] = ['production_logs', $key, 'missing row; no insert preview unless separately approved'];
                continue;
            }
            $expectedHdep = (int) $row['Hen_Count'] > 0
                ? round((int) $row['Egg_Count'] / (int) $row['Hen_Count'] * 100, 2)
                : 0;
            foreach (['egg_count' => (int) $row['Egg_Count'], 'hen_count' => (int) $row['Hen_Count'], 'hdep' => $expectedHdep] as $field => $expected) {
                if ((float) $production->{$field} !== (float) $expected) {
                    $differences[] = ['production_logs', $key, "{$field}: current={$production->{$field}}, CSV={$expected}"];
                }
            }

            foreach (['feed' => 'feed_consumption_logs', 'environment' => 'environmental_logs'] as $kind => $table) {
                $sourceKey = $this->sourceKey($row, $kind);
                $legacyKey = 'production_data_clean:'.$row['Date'].':'.$row['Cage_Code'].':'.$kind;
                $exists = DB::table($table)->where('source_key', $sourceKey)->exists()
                    || DB::table($table)->where('source_key', $legacyKey)->exists();
                if (! $exists) $differences[] = [$table, $sourceKey, 'missing row; no insert preview unless separately approved'];
            }
            if ((int) $row['Mortality_Count'] > 0) {
                $sourceKey = $this->sourceKey($row, 'mortality');
                $legacyKey = 'production_data_clean:'.$row['Date'].':'.$row['Cage_Code'].':mortality';
                if (! DB::table('mortality_logs')->where('source_key', $sourceKey)->exists()
                    && ! DB::table('mortality_logs')->where('source_key', $legacyKey)->exists()) {
                    $differences[] = ['mortality_logs', $sourceKey, 'missing row; no insert preview unless separately approved'];
                }
            }
        }

        if ($differences) {
            $this->warn('Existing-row differences (showing first 30; no changes proposed/applied):');
            $this->table(['Table', 'Source key', 'Difference'], array_slice($differences, 0, 30));
            $this->line('Total differences: '.count($differences));
        } else {
            $this->info('Existing-row preview: 474/474 production cage/day rows match egg_count, hen_count, and HDEP; feed, environment, and positive mortality source rows are present. Proposed row updates: 0.');
            $this->line('Farm_Hen_Count_On_Date is validation-only; it is not copied into individual chicken records or added to each cage row.');
        }
    }
}
