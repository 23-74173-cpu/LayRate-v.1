<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\ForecastInputSync;
use Illuminate\Console\Command;

class SyncForecastInputRecords extends Command
{
    protected $signature = 'forecast:sync-input-records
                            {--cage= : Sync only a specific cage code}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--dry-run : Show what would be synced without saving}
                            {--catch-up : Force catch-up mode regardless of last sync time}';

    protected $description = 'Sync forecast_input_records from existing farm tables.';

    public function handle(): int
    {
        if ($this->option('dry-run')) {
            $this->info('Dry run — would sync all available production records.');
            return self::SUCCESS;
        }

        $count = ForecastInputSync::run(
            array_filter([
                'cage' => $this->option('cage'),
                'from' => $this->option('from'),
                'to'   => $this->option('to'),
            ]),
            $this->option('catch-up')
        );

        if ($count === 0) {
            $this->warn('No new records to sync.');
        } else {
            $this->info("Synced {$count} records into forecast_input_records.");
        }

        return self::SUCCESS;
    }
}
