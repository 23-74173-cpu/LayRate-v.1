<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class SystemTimeController extends Controller
{
    /**
     * Show the system time setup form.
     */
    public function show(Request $request)
    {
        $request->session()->put('system_time_check_done', true);

        $current = Carbon::now('Asia/Manila');
        $timezone = date_default_timezone_get();

        return view('system-time', compact('current', 'timezone'));
    }

    /**
     * Apply the manually entered system time and timezone.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'system_time' => ['required', 'date_format:Y-m-d\TH:i'],
        ]);

        $datetime = Carbon::createFromFormat('Y-m-d\TH:i', $data['system_time'], 'Asia/Manila');
        if (! $datetime) {
            return back()->withErrors(['system_time' => 'Invalid date/time.']);
        }

        $formatted = $datetime->format('Y-m-d H:i:s');

        // Always force Asia/Manila and set the system clock.
        $errors = [];
        $this->runTimedatectl('set-timezone', 'Asia/Manila', $errors);
        $this->runTimedatectl('set-time', $formatted, $errors);

        if (! empty($errors)) {
            return back()->withErrors(['system_time' => 'Could not set system time: ' . implode('; ', $errors)]);
        }

        // Update PHP's default timezone so the current request reflects the change immediately.
        date_default_timezone_set('Asia/Manila');

        return redirect()->route('dashboard')->with('success', 'System time set to ' . $formatted . ' Asia/Manila.');
    }

    /**
     * Run a timedatectl command via sudo and collect any errors.
     */
    private function runTimedatectl(string $command, string $value, array &$errors): void
    {
        $value = escapeshellarg($value);
        $cmd = "sudo timedatectl {$command} {$value} 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            $errors[] = implode(' ', $output) ?: "{$command} failed";
        }
    }
}
