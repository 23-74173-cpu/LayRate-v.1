<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // Login throttling (Phase 4a): 5 failed attempts per minute per
        // username+IP. Only failures count; success clears the counter.
        // Same generic error shape as a bad password, so the lockout message
        // never reveals whether the username exists.
        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many sign-in attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);

            if (Auth::user()->is_active === false) {
                Auth::logout();
                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => 'This account has been deactivated.']);
            }

            $request->session()->regenerate();

            // Unauthenticated Turbo-frame requests (the dashboard's analytics
            // fragments like /dashboard/mortality-trend that autoload after a
            // session expires) each record their own URL as "intended", so the
            // last one to fire wins and would land the user on a bare fragment
            // page after login. Strip dashboard sub-paths from intended and fall
            // back to the dashboard proper.
            $intendedPath = (string) parse_url((string) $request->session()->get('url.intended', ''), PHP_URL_PATH);
            if (str_starts_with($intendedPath, '/dashboard/')) {
                $request->session()->forget('url.intended');
            }

            // Walled garden retired (Phase 3): no nftables client authorization.
            // Login is HTTPS-only; no post-login IP allowlisting needed.

            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($throttleKey);

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Invalid email or password.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
