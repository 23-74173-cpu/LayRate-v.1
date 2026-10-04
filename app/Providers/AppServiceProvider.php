<?php

namespace App\Providers;

use App\Models\Alert;
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

        // Egg prices for the dock modal on egg pages (admins only; two tiny
        // queries). Rendered server-side with the modal — no lazy loading.
        View::composer('eggs._tabs', function ($view) {
            $user = auth()->user();

            $view->with('eggPrices', $user?->isAdmin()
                ? \App\Models\EggPrice::all()->keyBy('egg_size')->all()
                : []);
            $view->with('lastPriceChange', $user?->isAdmin()
                ? \App\Models\EggPriceHistory::with('changedBy')->latest()->first()
                : null);
        });

        View::composer('layouts.app', function ($view) {
            $alertCount = 0;
            $newAlerts = collect();
            $showAlertsModal = false;

            if (auth()->check()) {
                $acknowledgedIds = session()->get('alerts_acknowledged_ids', []);

                // Runs on every page: only the unread IDs are fetched here.
                // Full alert rows (with their cage) are loaded only when some
                // are new to this session and the popup will show them.
                $unreadIds = Alert::where('is_read', false)->pluck('id')->all();

                // Prune stale IDs (read/deleted alerts) so the session list
                // can't grow unbounded across logins.
                $pruned = array_values(array_intersect($acknowledgedIds, $unreadIds));
                if (count($pruned) !== count($acknowledgedIds)) {
                    session()->put('alerts_acknowledged_ids', $pruned);
                    $acknowledgedIds = $pruned;
                }

                $alertCount = count($unreadIds);
                $newIds = array_values(array_diff($unreadIds, $acknowledgedIds));
                if (! empty($newIds)) {
                    $newAlerts = Alert::whereIn('id', $newIds)
                        ->with('cage')
                        ->orderByDesc('triggered_at')
                        ->get();
                }
                $showAlertsModal = $newAlerts->isNotEmpty();
            }

            $view->with([
                'globalAlertCount' => $alertCount,
                'globalNewAlerts'  => $newAlerts,
                'showAlertsModal'  => $showAlertsModal,
                // Global quick-actions dock checklist (same data as the old
                // dashboard-only partial; guests get nothing to render).
                'dockCompleteness' => auth()->check()
                    ? \App\Services\DataCompletenessService::forToday()
                    : [],
            ]);
        });
    }
}
