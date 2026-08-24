<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ReportingDateService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Mandatory first-run setup wizard (admin only, no skip).
 *
 * Two steps, each with a short tutorial, collected on one page and saved
 * together:
 *   1. Date & Time     -> sets the system clock via timedatectl (Pi only)
 *   2. Day Reset Time  -> setting key `day_reset_time`
 *
 * On save it writes the `setup_completed` setting so the wizard never auto-
 * runs again. The date/time step is best-effort: on a machine without
 * `sudo timedatectl` (e.g. local Windows dev) it warns but does not block
 * the other step.
 */
class SetupController extends Controller
{
    public function show(Request $request)
    {
        $request->session()->put('system_time_check_done', true);

        $current = Carbon::now(ReportingDateService::timezone());

        return view('setup', [
            'current'     => $current,
            'timezone'    => ReportingDateService::timezone(),
            'resetTime'   => ReportingDateService::resetTime(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'system_time'    => ['required', 'date_format:Y-m-d\TH:i'],
            'day_reset_time' => ['required', 'regex:/^([01]?\d|2[0-3]):[0-5]\d$/'],
        ]);

        // ── Day reset time ────────────────────────────────────────
        Setting::set('day_reset_time', $data['day_reset_time']);

        // ── Date & time (best-effort, Pi only) ────────────────────
        $clockWarning = $this->applySystemTime($data['system_time']);

        // ── Mark complete ─────────────────────────────────────────
        Setting::set('setup_completed', '1');

        if ($clockWarning) {
            return redirect()->route('dashboard')
                ->with('success', 'Account setup complete.')
                ->with('warning', $clockWarning);
        }

        return redirect()->route('dashboard')->with('success', 'Account setup complete.');
    }

    /**
     * Attempt to set the OS clock + timezone to the entered value.
     * Returns a human-readable warning on failure, null on success.
     */
    private function applySystemTime(string $systemTime): ?string
    {
        $datetime = Carbon::createFromFormat('Y-m-d\TH:i', $systemTime, ReportingDateService::timezone());
        if (! $datetime) {
            return 'Date & time could not be applied (invalid format). You can adjust it later from Account settings.';
        }

        $formatted = $datetime->format('Y-m-d H:i:s');

        $errors = [];
        $this->runTimedatectl('set-timezone', ReportingDateService::timezone(), $errors);
        $this->runTimedatectl('set-time', $formatted, $errors);

        // Reflect the change in the current request regardless.
        date_default_timezone_set(ReportingDateService::timezone());

        if (! empty($errors)) {
            return 'Set the date & time successfully is not possible on this machine — ' . implode('; ', $errors) .
                '. Your day reset time and setup are already saved; adjust the clock from Account settings.';
        }

        return null;
    }

    private function runTimedatectl(string $command, string $value, array &$errors): void
    {
        if (! function_exists('exec') || stripos(PHP_OS, 'WIN') === 0) {
            // Windows dev box (or exec disabled): cannot set the OS clock.
            $errors[] = "system clock not controllable on this platform ({$command})";
            return;
        }

        $value = escapeshellarg($value);
        $cmd = "sudo timedatectl {$command} {$value} 2>&1";
        @exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            $errors[] = implode(' ', $output) ?: "{$command} failed";
        }
    }
}
