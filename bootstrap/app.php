<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'system-time-set' => \App\Http\Middleware\EnsureSystemTimeSet::class,
        ]);
        $middleware->web(append: [
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
    })
    ->withSchedule(function (Illuminate\Console\Scheduling\Schedule $schedule) {
        $schedule->command('layrate:reconcile-occupancy --apply')
            ->daily()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/occupancy-reconcile.log'));

        $schedule->command('forecast:sync-input-records')
            ->daily()
            ->at('00:05')
            ->timezone(\App\Services\ReportingDateService::timezone())
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/forecast-sync.log'));

        $schedule->command('forecast:sync-input-records --catch-up')
            ->daily()
            ->at('06:05')
            ->timezone(\App\Services\ReportingDateService::timezone())
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/forecast-sync.log'));

        $schedule->command('alerts:check-environment')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/alerts-check-environment.log'));

        $schedule->command('environment:compute-daily-averages')
            ->daily()
            ->at('03:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/environment-daily-averages.log'));

        $schedule->command('hardware:check-health')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/hardware-health-check.log'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
