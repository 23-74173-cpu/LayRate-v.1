<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\RelayCommandController;
use App\Http\Controllers\SensorIngestionController;
use App\Http\Middleware\DeviceAuth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sensor Ingestion API Routes
|--------------------------------------------------------------------------
|
| These routes are intended for local-network hardware devices only
| (e.g. a Raspberry Pi forwarding Arduino sensor output). Authentication
| is lightweight: a pre-shared API key in the X-Device-Key header.
|
*/

Route::middleware(DeviceAuth::class)->group(function () {
    Route::post('/sensor-readings', [SensorIngestionController::class, 'store'])
        ->name('api.sensor-readings.store');

    Route::get('/relay/command', [RelayCommandController::class, 'show'])
        ->name('api.relay.command');

    Route::get('/alerts', [AlertController::class, 'apiIndex'])
        ->name('api.alerts.index');

    Route::put('/alerts/{alert}/read', [AlertController::class, 'apiMarkRead'])
        ->name('api.alerts.read');
});
