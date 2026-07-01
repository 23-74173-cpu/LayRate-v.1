<?php

namespace App\Providers;

use App\Models\Alert;
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
    }
}
