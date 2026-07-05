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
