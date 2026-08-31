<?php

use App\Models\Setting;
use App\Services\ReportingDateService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Register all artisan commands explicitly (Laravel 12 slim mode
// does not auto-discover commands from app/Console/Commands).
Artisan::command('forecast:sync-input-records {--cage=} {--from=} {--to=} {--dry-run} {--catch-up}', function () {
    if ($this->option('dry-run')) {
        $this->info('Dry run — would sync all available production records.');
        return;
    }
    $count = \App\Services\ForecastInputSync::run(
        array_filter([
            'cage' => $this->option('cage'),
            'from' => $this->option('from'),
            'to'   => $this->option('to'),
        ]),
        $this->option('catch-up')
    );
    $count === 0 ? $this->warn('No new records to sync.') : $this->info("Synced {$count} records into forecast_input_records.");
})->purpose('Sync farm records into forecast_input_records');

// Sync daily farm records into forecast_input_records so the forecasting
// module always has a continuously growing historical dataset.
//
// Runs just after midnight so the previous calendar day is fully captured.
// Scheduled in the farm timezone (Asia/Manila).
Schedule::command('forecast:sync-input-records')
    ->daily()
    ->at('00:05')
    ->timezone(ReportingDateService::timezone())
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/forecast-sync.log'));

// Backstop: run 6 hours after midnight. Catches cases where the server
// was off during midnight and came back online later.
Schedule::command('forecast:sync-input-records --catch-up')
    ->daily()
    ->at('06:05')
    ->timezone(ReportingDateService::timezone())
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/forecast-sync.log'));

// Check environmental log thresholds for violations every 15 minutes.
Schedule::command('alerts:check-environment')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/alerts-check-environment.log'));

// Compute daily average environmental readings (runs early morning for previous day).
Schedule::command('environment:compute-daily-averages')
    ->daily()
    ->at('03:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/environment-daily-averages.log'));

// Hardware health state machine — 15-min backstop for elapsed-time
// escalations only (online/stale is driven live by ingestion).
Schedule::command('hardware:check-health')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/hardware-health-check.log'));
