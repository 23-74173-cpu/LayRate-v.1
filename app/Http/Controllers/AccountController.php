<?php

namespace App\Http\Controllers;

use App\Models\FeedConsumptionLog;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    private const WEAK_PINS = ['0000','1111','2222','3333','4444','5555','6666','7777','8888','9999','1234','4321','0123','1212'];

    public function profile(Request $request)
    {
        $staff = auth()->user()->isAdmin()
            ? User::orderBy('name')->get(['id', 'name', 'role', 'override_pin_hash'])
                ->map(fn ($u) => (object) [
                    'name'    => $u->name,
                    'role'    => $u->role,
                    'pin_set' => $u->override_pin_hash !== null,
                ])
            : null;

        $tab = in_array($request->get('tab'), ['profile', 'settings'], true)
            ? $request->get('tab')
            : 'profile';

        $user = auth()->user();

        // Personal contribution stats — everything this account has recorded.
        $activity = (object) [
            'egg_logs'       => ProductionLog::where('recorded_by', $user->id)->count(),
            'eggs_total'     => (int) ProductionLog::where('recorded_by', $user->id)->sum('egg_count'),
            'feed_logs'      => FeedConsumptionLog::where('recorded_by', $user->id)->count(),
            'mortality_logs' => MortalityLog::where('recorded_by', $user->id)->count(),
        ];

        // The sessions table is only authoritative when the database session
        // driver is active — under the file driver it holds stale rows, so we
        // fall back to showing just the current session from the live request.
        if (config('session.driver') === 'database') {
            $currentSessionId = $request->session()->getId();
            $sessions = DB::table('sessions')
                ->where('user_id', $user->id)
                ->orderByDesc('last_activity')
                ->get()
                ->map(fn ($s) => (object) [
                    'current'     => $s->id === $currentSessionId,
                    'ip'          => $s->ip_address ?? '—',
                    'device'      => $this->describeDevice($s->user_agent ?? ''),
                    'last_active' => Carbon::createFromTimestamp($s->last_activity),
                ]);
        } else {
            $sessions = collect([(object) [
                'current'     => true,
                'ip'          => $request->ip(),
                'device'      => $this->describeDevice($request->userAgent() ?? ''),
                'last_active' => now(),
            ]]);
        }

        // Admin-only read-only overview of farm-level configuration; each group
        // is edited on its own page, this is just the hub view.
        $farmSettings = $user->isAdmin() ? [
            'Environment thresholds' => [
                'route' => route('environment'),
                'values' => [
                    'Temperature' => Setting::get('temp_min', 18) . '°C — ' . Setting::get('temp_max', 30) . '°C',
                    'Humidity'    => Setting::get('hum_min', 40) . '% — ' . Setting::get('hum_max', 70) . '%',
                ],
            ],
            'Egg weights (FCR)' => [
                'route' => route('eggs.stocks'),
                'values' => [
                    'Small / Medium'  => Setting::get('egg_weight_small', 50) . 'g / ' . Setting::get('egg_weight_medium', 58) . 'g',
                    'Large / Jumbo'   => Setting::get('egg_weight_large', 65) . 'g / ' . Setting::get('egg_weight_jumbo', 73) . 'g',
                    'Fallback'        => Setting::get('egg_weight_fallback', 60) . 'g',
                ],
            ],
            'Farm layout grid' => [
                'route' => route('cages.index'),
                'values' => [
                    'Canvas size' => Setting::get('farm_grid_rows', 4) . ' rows × ' . Setting::get('farm_grid_cols', 4) . ' columns',
                ],
            ],
        ] : null;

        return view('profile', compact('staff', 'tab', 'activity', 'sessions', 'farmSettings'));
    }

    private function describeDevice(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg')     => 'Edge',
            str_contains($userAgent, 'Chrome')  => 'Chrome',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Safari')  => 'Safari',
            $userAgent === ''                    => 'Unknown device',
            default                              => 'Browser',
        };

        $os = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'),
            str_contains($userAgent, 'iPad')    => 'iOS',
            str_contains($userAgent, 'Mac')     => 'macOS',
            str_contains($userAgent, 'Linux')   => 'Linux',
            default                              => null,
        };

        return $os ? "{$browser} on {$os}" : $browser;
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->update($data);

        return redirect()->route('profile', ['tab' => 'profile'])->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($data['current_password'], auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user = auth()->user();
        $user->update(['password' => Hash::make($data['password'])]);

        // Keep the current session valid after a password change by syncing the
        // session hash that AuthenticateSession middleware checks on each request.
        // The middleware key is guard-specific (default guard = 'web').
        $request->session()->put('password_hash_' . Auth::getDefaultDriver(), $user->password);

        return redirect()->route('profile', ['tab' => 'settings'])->with('success', 'Password updated.');
    }

    public function updatePin(Request $request)
    {
        $request->validate([
            'pin'               => 'required|digits_between:4,6|confirmed',
            'current_pin'       => 'nullable|string',
            'current_password'  => 'nullable|string',
        ]);

        $pin  = $request->input('pin');
        $user = auth()->user();

        if (in_array($pin, self::WEAK_PINS, true)) {
            return back()->withErrors(['pin' => 'This PIN is too easy to guess. Choose a different one.']);
        }

        if ($user->override_pin_hash !== null) {
            $verifiedByPin      = $request->filled('current_pin') && Hash::check($request->input('current_pin'), $user->override_pin_hash);
            $verifiedByPassword = $request->filled('current_password') && Hash::check($request->input('current_password'), $user->password);

            if (! $verifiedByPin && ! $verifiedByPassword) {
                return back()->withErrors(['current_pin' => 'Current PIN (or account password) is incorrect.']);
            }
        }

        $user->update(['override_pin_hash' => Hash::make($pin)]);

        return redirect()->route('profile', ['tab' => 'settings'])->with('success', 'Override PIN saved.');
    }

    public function logoutOtherDevices(Request $request)
    {
        $data = $request->validate([
            'logout_password' => 'required',
        ]);

        if (! Hash::check($data['logout_password'], auth()->user()->password)) {
            return back()->withErrors(['logout_password' => 'Current password is incorrect.']);
        }

        Auth::logoutOtherDevices($data['logout_password']);

        // logoutOtherDevices() rehashes the user's password with a new salt, which
        // invalidates every other session. Sync the current session's hash so this
        // session stays alive.
        $user = auth()->user()->fresh();
        $request->session()->put('password_hash_' . Auth::getDefaultDriver(), $user->password);

        return redirect()->route('profile', ['tab' => 'settings'])->with('success', 'Signed out of all other devices.');
    }
}
