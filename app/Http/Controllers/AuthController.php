<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
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

            // Authorize client IP in the nftables walled garden
            $clientIp = $request->ip();
            if ($clientIp && $clientIp !== '127.0.0.1' && $clientIp !== '::1') {
                exec('sudo /usr/local/bin/layrate-auth-client ' . escapeshellarg($clientIp) . ' 2>/dev/null &');
            }

            return redirect()->intended(route('dashboard'));
        }

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
