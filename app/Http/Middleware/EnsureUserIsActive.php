<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivating a user (Settings → Team) must take effect on their next
 * request, not just block their next login — otherwise an already-logged-in
 * session keeps working until it naturally expires.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Strict false check, not a truthiness check: a user row always has
        // is_active set via the DB column default, but an in-memory model
        // built without explicitly passing the attribute (e.g. a test that
        // skips the factory default) would read null rather than true —
        // treat that as active rather than incorrectly locking them out.
        if ($user && $user->is_active === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This account has been deactivated.',
            ]);
        }

        return $next($request);
    }
}
