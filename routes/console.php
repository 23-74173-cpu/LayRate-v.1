<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync daily farm records into forecast_input_records so the forecasting
// module always has a continuously growing historical dataset.
Schedule::command('forecast:sync-input-records')
    ->daily()
    ->at('02:00')
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

// Check active hardware sensors for staleness every 15 minutes.
// Sensors with no environmental_log within the threshold (default 30 min)
// are automatically marked as faulty.
Schedule::command('hardware:check-staleness')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/hardware-staleness-check.log'));
