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
