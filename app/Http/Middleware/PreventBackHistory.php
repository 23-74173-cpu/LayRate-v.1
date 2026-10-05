<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back/Forward must never show a page from the browser's cache. Without
 * no-store, pressing Back after sign-out redrew the last farm page from the
 * HTTP cache or the back-forward cache, and pressing Back after sign-in drew
 * the login page again. With it, the browser asks the server, and the `auth`
 * and `guest` middleware send the user to the right place.
 *
 * Applied to every web response: farm pages, the login and info pages, and
 * the redirects between them. Static files (CSS/JS/images) are served by the
 * web server, not Laravel, so they stay cacheable.
 */
class PreventBackHistory
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
