<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
