<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemTimeSet
{
    /**
     * Minimum acceptable system year. Anything older suggests the Pi clock
     * reset after losing power (no RTC / no internet NTP sync).
     */
    private const MIN_YEAR = 2024;

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldCheck($request) && $this->timeLooksUnset()) {
            $request->session()->put('system_time_check_done', true);

            return redirect()->route('settings.system-time')
                ->with('warning', 'The system clock appears to be unset. Please set the current date and time for accurate farm records.');
        }

        return $next($request);
    }

    /**
     * Only check for admins once per session, and never on the system-time page itself.
     */
    private function shouldCheck(Request $request): bool
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            return false;
        }

        if ($request->routeIs('settings.system-time', 'settings.system-time.update')) {
            return false;
        }

        return ! $request->session()->get('system_time_check_done');
    }

    /**
     * Heuristic: if the year is before 2024, the clock is almost certainly wrong.
     */
    private function timeLooksUnset(): bool
    {
        return (int) date('Y') < self::MIN_YEAR;
    }
}
