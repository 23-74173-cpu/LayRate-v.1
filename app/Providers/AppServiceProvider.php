<?php

namespace App\Providers;

use App\Models\Alert;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
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

        // Single place to change the app-wide display date format
        // (Month/Day/Year). Never use this for ISO date-key strings (form
        // values, array/query keys, log_date comparisons) — only for text
        // actually shown to a user.
        Carbon::macro('display', function () {
            /** @var \Carbon\Carbon $this */
            return $this->format('m/d/Y');
        });
        Carbon::macro('displayDateTime', function () {
            /** @var \Carbon\Carbon $this */
            return $this->format('m/d/Y g:i A');
        });

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

                // Prune stale IDs (read/deleted alerts) so the session list
                // can't grow unbounded across logins.
                $unreadIds = $unreadAlerts->pluck('id')->all();
                $pruned = array_values(array_intersect($acknowledgedIds, $unreadIds));
                if (count($pruned) !== count($acknowledgedIds)) {
                    session()->put('alerts_acknowledged_ids', $pruned);
                    $acknowledgedIds = $pruned;
                }

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
