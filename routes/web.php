<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CageController;
use App\Http\Controllers\ChickensController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EggLoggingController;
use App\Http\Controllers\EggStockController;
use App\Http\Controllers\EnvironmentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\HardwareItemController;
use App\Http\Controllers\MortalityController;
use App\Http\Controllers\PreOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
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
    Route::post('/settings/farm-layout', [SettingsController::class, 'storeFarmLayout'])->name('settings.farm-layout');

    Route::get('/cages',               [CageController::class, 'index'])->name('cages.index');
    Route::post('/cages',              [CageController::class, 'store'])->name('cages.store');
    Route::put('/cages/{cage}',        [CageController::class, 'update'])->name('cages.update');
    Route::patch('/cages/{cage}/position', [CageController::class, 'updatePosition'])->name('cages.position');
    Route::post('/cages/batch-position', [CageController::class, 'batchUpdatePosition'])->name('cages.batch-position');
    Route::delete('/cages/{cage}',     [CageController::class, 'destroy'])->name('cages.destroy')->middleware('admin');
    Route::get('/cages/{cage}/slots-json', [CageController::class, 'slotsJson'])->name('cages.slots-json');
    Route::get('/cages/slots/{slot}/hens-json', [CageController::class, 'hensJson'])->name('cages.slots.hens-json');
    Route::get('/cages/bulk-add',  [CageController::class, 'bulkAdd'])->name('cages.bulk-add');
    Route::post('/cages/bulk-add', [CageController::class, 'storeBulkAdd'])->name('cages.bulk-add.store');
    Route::get('/cages/{cage}/confirm-delete', [CageController::class, 'deleteConfirm'])->name('cages.confirm-delete');
    Route::delete('/cages/{cage}/force', [CageController::class, 'forceDestroy'])->name('cages.force-destroy')->middleware('admin');

    Route::get('/chickens',        [ChickensController::class, 'index'])->name('chickens.index');
    Route::post('/chickens/move',  [ChickensController::class, 'move'])->name('chickens.move');
    Route::post('/chickens/remove', [ChickensController::class, 'remove'])->name('chickens.remove');

    Route::redirect('/egg-logging', '/eggs/logging', 301);

    Route::get('/eggs/logging',                        [EggLoggingController::class, 'index'])->name('eggs.logging');
    Route::post('/eggs/logging',                       [EggLoggingController::class, 'store'])->name('eggs.logging.store');
    Route::post('/eggs/logging/verify-override',       [EggLoggingController::class, 'verifyOverride'])->name('eggs.logging.verify-override')->middleware('throttle:6,1');
    Route::put('/eggs/logging/{productionLog}',        [EggLoggingController::class, 'update'])->name('eggs.logging.update');
    Route::delete('/eggs/logging/{productionLog}',     [EggLoggingController::class, 'destroy'])->name('eggs.logging.destroy')->middleware('admin');

    Route::get('/eggs/stocks',                         [EggStockController::class, 'index'])->name('eggs.stocks');
    Route::post('/eggs/stocks',                        [EggStockController::class, 'store'])->name('eggs.stocks.store');
    Route::put('/eggs/stocks/{batch}',                 [EggStockController::class, 'update'])->name('eggs.stocks.update');
    Route::delete('/eggs/stocks/{batch}',              [EggStockController::class, 'destroy'])->name('eggs.stocks.destroy');
    Route::get('/eggs/stocks/{batch}/qr',              [EggStockController::class, 'qr'])->name('eggs.stocks.qr');

    Route::get('/eggs/pre-orders',                     [PreOrderController::class, 'index'])->name('eggs.preorders');
    Route::post('/eggs/pre-orders',                    [PreOrderController::class, 'store'])->name('eggs.preorders.store');
    Route::patch('/eggs/pre-orders/{order}',           [PreOrderController::class, 'update'])->name('eggs.preorders.update');
    Route::delete('/eggs/pre-orders/{order}',          [PreOrderController::class, 'destroy'])->name('eggs.preorders.destroy');

    Route::get('/environment',  [EnvironmentController::class, 'index'])->name('environment');
    Route::post('/environment/thresholds', [EnvironmentController::class, 'saveThresholds'])->name('environment.thresholds');

    Route::get('/hardware',                    [HardwareItemController::class, 'index'])->name('hardware.index');
    Route::post('/hardware',                   [HardwareItemController::class, 'store'])->name('hardware.store');
    Route::put('/hardware/{hardwareItem}',     [HardwareItemController::class, 'update'])->name('hardware.update');
    Route::delete('/hardware/{hardwareItem}',  [HardwareItemController::class, 'destroy'])->name('hardware.destroy');

    Route::get('/feed',                          [FeedController::class, 'index'])->name('feed');
    Route::post('/feed/batch',                   [FeedController::class, 'storeBatch'])->name('feed.batch.store');
    Route::put('/feed/batch/{feedBatch}',        [FeedController::class, 'updateBatch'])->name('feed.batch.update');
    Route::delete('/feed/batch/{feedBatch}',     [FeedController::class, 'destroyBatch'])->name('feed.batch.destroy');
    Route::get('/feed/batch/{feedBatch}/delete-check', [FeedController::class, 'checkDeleteBatch'])->name('feed.batch.delete-check');
    Route::post('/feed/consumption',             [FeedController::class, 'storeConsumption'])->name('feed.consumption.store');
    Route::delete('/feed/consumption/{feedConsumptionLog}', [FeedController::class, 'destroyConsumption'])->name('feed.consumption.destroy');

    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');

    Route::get('/forecast',           [ForecastController::class, 'index'])->name('forecast');
    Route::post('/forecast/generate', [ForecastController::class, 'generate'])->name('forecast.generate');

    Route::get('/account',           [AccountController::class, 'show'])->name('account');
    Route::post('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('/account/pin',      [AccountController::class, 'updatePin'])->name('account.pin');

    Route::get('/reports',     [ReportController::class, 'index'])->name('reports');
    Route::get('/reports/csv', [ReportController::class, 'exportCsv'])->name('reports.csv');

    Route::get('/notifications',                    [AlertController::class, 'index'])->name('notifications.index');
    Route::post('/alerts/acknowledge-modal',         [AlertController::class, 'acknowledgeModal'])->name('alerts.acknowledge-modal');
    Route::post('/alerts/{alert}/read',              [AlertController::class, 'markRead'])->name('alerts.read');
    Route::post('/alerts/read-all',                  [AlertController::class, 'markAllRead'])->name('alerts.read-all');

    Route::get('/mortality',                    [MortalityController::class, 'index'])->name('mortality.index');
    Route::post('/mortality',                   [MortalityController::class, 'store'])->name('mortality.store');
    Route::put('/mortality/{mortalityLog}',     [MortalityController::class, 'update'])->name('mortality.update');
    Route::delete('/mortality/{mortalityLog}',  [MortalityController::class, 'destroy'])->name('mortality.destroy')->middleware('admin');
});
