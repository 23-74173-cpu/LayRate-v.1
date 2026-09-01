<?php

namespace App\Providers;

use App\Models\Alert;
use App\Services\ForecastInputSync;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn ($user) => $user->isAdmin());

        View::composer('layouts.app', function ($view) {
            $alertCount = 0;
            $newAlerts = collect();
            $showAlertsModal = false;

            if (auth()->check()) {
                $acknowledgedIds = session()->get('alerts_acknowledged_ids', []);

                $unreadAlerts = Alert::where('is_read', false)
                    ->with('cage')
                    ->orderByDesc('triggered_at')
                    ->get();

                $alertCount = $unreadAlerts->count();
                $newAlerts = $unreadAlerts->whereNotIn('id', $acknowledgedIds);
                $showAlertsModal = $newAlerts->isNotEmpty();
            }

            $view->with([
                'globalAlertCount' => $alertCount,
                'globalNewAlerts'  => $newAlerts,
                'showAlertsModal'  => $showAlertsModal,
            ]);
        });

        // Sync forecast input records on every server startup.
        // Uses a lock file to run only once per process lifetime (not every request).
        $lockFile = storage_path('app/forecast_sync_boot.lock');
        $shouldSync = true;
        if (file_exists($lockFile)) {
            $lockAge = time() - (int) file_get_contents($lockFile);
            if ($lockAge < 3600) { // already synced within the last hour
                $shouldSync = false;
            }
        }
        if ($shouldSync) {
            try {
                ForecastInputSync::run([], catchUp: true);
                file_put_contents($lockFile, (string) time());
                Log::info('Forecast input records synced on server startup');
            } catch (\Throwable $e) {
                Log::error('Startup forecast sync failed: ' . $e->getMessage());
            }
        }
    }
}
