<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CageController;
use App\Http\Controllers\ChickensController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\EggLoggingController;
use App\Http\Controllers\EggProductionHistoryController;
use App\Http\Controllers\EggStockController;
use App\Http\Controllers\EnvironmentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\HardwareItemController;
use App\Http\Controllers\MortalityController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PreOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ─── Public landing page ───────────────────────────────────────
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return view('landing');
})->name('landing');

// ─── Guest routes ─────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Temporary: opcache reset endpoint (no auth required)
Route::get('/_reset-opcache', function () {
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    return 'opcache reset done';
});

// ─── Authenticated routes ──────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/dashboard/feed-mortality', [DashboardController::class, 'feedMortality'])->name('dashboard.feed-mortality');
    Route::post('/settings/farm-layout', [SettingsController::class, 'storeFarmLayout'])->name('settings.farm-layout');

    // Cages — reads (all authenticated users)
    Route::get('/cages',               [CageController::class, 'index'])->name('cages.index');
    Route::get('/cages/{cage}/slots-json', [CageController::class, 'slotsJson'])->name('cages.slots-json');
    Route::get('/cages/slots/{slot}/hens-json', [CageController::class, 'hensJson'])->name('cages.slots.hens-json');
    Route::get('/cages/bulk-add',  [CageController::class, 'bulkAdd'])->name('cages.bulk-add');
    Route::post('/cages/bulk-add', [CageController::class, 'storeBulkAdd'])->name('cages.bulk-add.store');
    Route::get('/cages/{cage}/print-label', [CageController::class, 'printLabel'])->name('cages.print-label');

    // Cages — farm layout mutations (admins only; enforced server-side)
    Route::middleware('admin')->group(function () {
        Route::post('/cages',              [CageController::class, 'store'])->name('cages.store');
        Route::put('/cages/{cage}',        [CageController::class, 'update'])->name('cages.update');
        Route::patch('/cages/{cage}/position', [CageController::class, 'updatePosition'])->name('cages.position');
        Route::post('/cages/batch-position', [CageController::class, 'batchUpdatePosition'])->name('cages.batch-position');
        Route::post('/cages/remove-cell', [CageController::class, 'removeCell'])->name('cages.remove-cell');
        Route::post('/cages/{cage}/slots/reorder', [CageController::class, 'batchReorderSlots'])->name('cages.slots.reorder');
        Route::get('/cages/{cage}/sensor-info', [CageController::class, 'sensorInfo'])->name('cages.sensor-info');
        Route::get('/cages/{cage}/delete-info', [CageController::class, 'deleteInfo'])->name('cages.delete-info');
        Route::delete('/cages/{cage}',     [CageController::class, 'destroy'])->name('cages.destroy');
        Route::get('/cages/{cage}/confirm-delete', [CageController::class, 'deleteConfirm'])->name('cages.confirm-delete');
        Route::delete('/cages/{cage}/force', [CageController::class, 'forceDestroy'])->name('cages.force-destroy');
    });

    Route::get('/notes',           [NoteController::class, 'index'])->name('notes.index');
    Route::post('/notes',          [NoteController::class, 'store'])->name('notes.store');
    Route::put('/notes/{note}',    [NoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

    Route::get('/chickens',              [ChickensController::class, 'index'])->name('chickens.index');
    Route::get('/chickens/inventory-list', [ChickensController::class, 'inventoryList'])->name('chickens.inventory-list');
    Route::get('/chickens/mortality-records', [ChickensController::class, 'mortalityRecords'])->name('chickens.mortality-records');
    Route::post('/chickens',               [ChickensController::class, 'store'])->name('chickens.store');
    Route::post('/chickens/health-event',  [ChickensController::class, 'storeHealthEvent'])->name('chickens.health-event');
    Route::post('/chickens/weight-check',  [ChickensController::class, 'storeWeightCheck'])->name('chickens.weight-check');
    Route::post('/chickens/cull',          [ChickensController::class, 'storeCulling'])->name('chickens.cull');
    Route::post('/chickens/removal',        [ChickensController::class, 'storeRemoval'])->name('chickens.removal');
    Route::get('/chickens/culling-records', [ChickensController::class, 'cullingRecords'])->name('chickens.culling-records');
    Route::get('/chickens/removal-records', [ChickensController::class, 'removalRecords'])->name('chickens.removal-records');
    Route::post('/chickens/move',          [ChickensController::class, 'move'])->name('chickens.move');
    Route::post('/chickens/remove',        [ChickensController::class, 'remove'])->name('chickens.remove');

    Route::redirect('/egg-logging', '/eggs/logging', 301);

    Route::get('/eggs/logging',                        [EggLoggingController::class, 'index'])->name('eggs.logging');
    Route::get('/eggs/logging/logs',                   [EggLoggingController::class, 'logs'])->name('eggs.logging.logs');
    Route::get('/eggs/recent-logs',                    [EggLoggingController::class, 'recentLogs'])->name('eggs.recent-logs');
    Route::get('/egg-production-history',              [EggProductionHistoryController::class, 'index'])->name('egg-production-history');
    Route::post('/eggs/logging',                       [EggLoggingController::class, 'store'])->name('eggs.logging.store');
    Route::post('/eggs/logging/verify-override',       [EggLoggingController::class, 'verifyOverride'])->name('eggs.logging.verify-override')->middleware('throttle:6,1');
    Route::put('/eggs/logging/{productionLog}',        [EggLoggingController::class, 'update'])->name('eggs.logging.update');
    Route::delete('/eggs/logging/{productionLog}',     [EggLoggingController::class, 'destroy'])->name('eggs.logging.destroy')->middleware('admin');

    Route::get('/eggs/stocks',                         [EggStockController::class, 'index'])->name('eggs.stocks');
    Route::get('/eggs/stocks/live-data',                [EggStockController::class, 'liveData'])->name('eggs.stocks.live-data');
    Route::get('/eggs/stocks/pool-data',                [EggStockController::class, 'poolData'])->name('eggs.stocks.pool-data');
    Route::post('/eggs/stocks',                        [EggStockController::class, 'store'])->name('eggs.stocks.store');
    Route::put('/eggs/stocks/{batch}',                 [EggStockController::class, 'update'])->name('eggs.stocks.update');
    Route::delete('/eggs/stocks/{batch}',              [EggStockController::class, 'destroy'])->name('eggs.stocks.destroy')->middleware('admin');
    Route::get('/eggs/stocks/{batch}/qr',              [EggStockController::class, 'qr'])->name('eggs.stocks.qr');

    Route::get('/eggs/pre-orders',                     [PreOrderController::class, 'index'])->name('eggs.preorders');
    Route::get('/eggs/pre-orders/table',                [PreOrderController::class, 'table'])->name('eggs.preorders.table');
    Route::get('/eggs/pre-orders/pool-data',            [PreOrderController::class, 'poolData'])->name('eggs.preorders.pool-data');
    Route::post('/eggs/pre-orders',                    [PreOrderController::class, 'store'])->name('eggs.preorders.store');
    Route::patch('/eggs/pre-orders/{order}',           [PreOrderController::class, 'update'])->name('eggs.preorders.update');
    Route::delete('/eggs/pre-orders/{order}',          [PreOrderController::class, 'destroy'])->name('eggs.preorders.destroy')->middleware('admin');

    Route::get('/environment',        [EnvironmentController::class, 'index'])->name('environment');
    Route::get('/environment/live-data', [EnvironmentController::class, 'liveData'])->name('environment.live-data');
    Route::get('/environment/logs',      [EnvironmentController::class, 'logs'])->name('environment.logs');
    Route::post('/environment/thresholds', [EnvironmentController::class, 'saveThresholds'])->name('environment.thresholds');
    Route::post('/eggs/stocks/egg-weights', [EggStockController::class, 'saveEggWeights'])->name('eggs.stocks.egg-weights');
    Route::post('/eggs/stocks/thresholds', [EggStockController::class, 'saveThresholds'])->name('eggs.stocks.thresholds');

    Route::get('/hardware',                    [HardwareItemController::class, 'index'])->name('hardware.index');
    Route::get('/hardware/live-data',          [HardwareItemController::class, 'liveData'])->name('hardware.live-data');
    Route::post('/hardware',                   [HardwareItemController::class, 'store'])->name('hardware.store');
    Route::put('/hardware/{hardwareItem}',     [HardwareItemController::class, 'update'])->name('hardware.update');
    Route::delete('/hardware/{hardwareItem}',  [HardwareItemController::class, 'destroy'])->name('hardware.destroy')->middleware('admin');

    Route::middleware('admin')->group(function () {
        Route::post('/devices',                           [DeviceController::class, 'store'])->name('devices.store');
        Route::post('/devices/{device}/regenerate-key',   [DeviceController::class, 'regenerateKey'])->name('devices.regenerate-key');
        Route::delete('/devices/{device}',                [DeviceController::class, 'destroy'])->name('devices.destroy');
    });

    Route::get('/feed',                          [FeedController::class, 'index'])->name('feed');
    Route::get('/feed/live-data',                [FeedController::class, 'liveData'])->name('feed.live-data');
    Route::get('/feed/fcr-data',                 [FeedController::class, 'fcrData'])->name('feed.fcr-data');
    Route::post('/feed/batch',                   [FeedController::class, 'storeBatch'])->name('feed.batch.store');
    Route::put('/feed/batch/{feedBatch}',        [FeedController::class, 'updateBatch'])->name('feed.batch.update');
    Route::delete('/feed/batch/{feedBatch}',     [FeedController::class, 'destroyBatch'])->name('feed.batch.destroy')->middleware('admin');
    Route::get('/feed/batch/{feedBatch}/delete-check', [FeedController::class, 'checkDeleteBatch'])->name('feed.batch.delete-check');
    Route::post('/feed/consumption',             [FeedController::class, 'storeConsumption'])->name('feed.consumption.store');
    Route::put('/feed/consumption/{feedConsumptionLog}', [FeedController::class, 'updateConsumption'])->name('feed.consumption.update');
    Route::delete('/feed/consumption/{feedConsumptionLog}', [FeedController::class, 'destroyConsumption'])->name('feed.consumption.destroy')->middleware('admin');
    Route::post('/feed/farm-entry',              [FeedController::class, 'storeFarmFeedEntry'])->name('feed.farm-entry.store');
    Route::put('/feed/farm-entry/{farmFeedEntry}', [FeedController::class, 'updateFarmFeedEntry'])->name('feed.farm-entry.update');
    Route::delete('/feed/farm-entry/{farmFeedEntry}', [FeedController::class, 'destroyFarmFeedEntry'])->name('feed.farm-entry.destroy');

    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
    Route::get('/analytics/charts', [AnalyticsController::class, 'charts'])->name('analytics.charts');
    Route::get('/analytics/data', [AnalyticsController::class, 'data'])->name('analytics.data');

    Route::get('/forecast',             [ForecastController::class, 'index'])->name('forecast');
    Route::post('/forecast/generate',   [ForecastController::class, 'generate'])->name('forecast.generate')->middleware('admin');
    Route::post('/forecast/clear',      [ForecastController::class, 'clear'])->name('forecast.clear')->middleware('admin');
    Route::get('/forecast/template',    [ForecastController::class, 'downloadTemplate'])->name('forecast.template');
    Route::post('/forecast/import',     [ForecastController::class, 'import'])->name('forecast.import')->middleware('admin');

    Route::get('/profile',                         [AccountController::class, 'profile'])->name('profile');
    Route::post('/profile',                        [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/logout-other-devices',   [AccountController::class, 'logoutOtherDevices'])->name('profile.logout-other-devices');
    Route::post('/settings/users',                 [AccountController::class, 'storeUser'])->name('settings.users.store')->middleware('admin');
    Route::put('/settings/users/{targetUser}',      [AccountController::class, 'updateUser'])->name('settings.users.update')->middleware('admin');
    Route::post('/settings/users/{targetUser}/toggle-active', [AccountController::class, 'toggleUserActive'])->name('settings.users.toggle-active')->middleware('admin');
    Route::redirect('/account', '/profile', 301);
    Route::post('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('/account/pin',      [AccountController::class, 'updatePin'])->name('account.pin');

    Route::get('/reports',     [ReportController::class, 'index'])->name('reports');
    Route::get('/reports/csv', [ReportController::class, 'exportCsv'])->name('reports.csv');

    Route::get('/notifications',                    [AlertController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/table',              [AlertController::class, 'table'])->name('notifications.table');
    Route::post('/alerts/acknowledge-modal',         [AlertController::class, 'acknowledgeModal'])->name('alerts.acknowledge-modal');
    Route::post('/alerts/{alert}/read',              [AlertController::class, 'markRead'])->name('alerts.read');
    Route::post('/alerts/read-all',                  [AlertController::class, 'markAllRead'])->name('alerts.read-all');

    Route::get('/mortality',                    [MortalityController::class, 'index'])->name('mortality.index');
    Route::get('/mortality/logs',               [MortalityController::class, 'logs'])->name('mortality.logs');
    Route::post('/mortality',                   [MortalityController::class, 'store'])->name('mortality.store');
    Route::put('/mortality/{mortalityLog}',     [MortalityController::class, 'update'])->name('mortality.update');
    Route::delete('/mortality/{mortalityLog}',  [MortalityController::class, 'destroy'])->name('mortality.destroy')->middleware('admin');
});
