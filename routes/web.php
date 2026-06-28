<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EggLoggingController;
use App\Http\Controllers\EnvironmentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\MortalityController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// ─── Guest routes ─────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ─── Authenticated routes ──────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/cages',               [CageController::class, 'index'])->name('cages.index');
    Route::post('/cages',              [CageController::class, 'store'])->name('cages.store');
    Route::put('/cages/{cage}',        [CageController::class, 'update'])->name('cages.update');
    Route::delete('/cages/{cage}',     [CageController::class, 'destroy'])->name('cages.destroy')->middleware('admin');

    Route::get('/egg-logging',                        [EggLoggingController::class, 'index'])->name('egg-logging');
    Route::post('/egg-logging',                       [EggLoggingController::class, 'store'])->name('egg-logging.store');
    Route::delete('/egg-logging/{productionLog}',     [EggLoggingController::class, 'destroy'])->name('egg-logging.destroy')->middleware('admin');

    Route::get('/environment',  [EnvironmentController::class, 'index'])->name('environment');
    Route::post('/environment/thresholds', [EnvironmentController::class, 'saveThresholds'])->name('environment.thresholds');

    Route::get('/feed',                    [FeedController::class, 'index'])->name('feed');
    Route::post('/feed/batch',             [FeedController::class, 'storeBatch'])->name('feed.batch.store');
    Route::put('/feed/batch/{feedBatch}',  [FeedController::class, 'updateBatch'])->name('feed.batch.update');
    Route::post('/feed/consumption',       [FeedController::class, 'storeConsumption'])->name('feed.consumption.store');

    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');

    Route::get('/forecast',           [ForecastController::class, 'index'])->name('forecast');
    Route::post('/forecast/generate', [ForecastController::class, 'generate'])->name('forecast.generate');

    Route::get('/account',           [AccountController::class, 'show'])->name('account');
    Route::post('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('/account/pin',      [AccountController::class, 'updatePin'])->name('account.pin');

    Route::get('/reports',     [ReportController::class, 'index'])->name('reports');
    Route::get('/reports/csv', [ReportController::class, 'exportCsv'])->name('reports.csv');

    Route::post('/alerts/{alert}/read',  [AlertController::class, 'markRead'])->name('alerts.read');
    Route::post('/alerts/read-all',      [AlertController::class, 'markAllRead'])->name('alerts.read-all');

    Route::get('/mortality',                    [MortalityController::class, 'index'])->name('mortality.index');
    Route::post('/mortality',                   [MortalityController::class, 'store'])->name('mortality.store');
    Route::delete('/mortality/{mortalityLog}',  [MortalityController::class, 'destroy'])->name('mortality.destroy')->middleware('admin');
});
