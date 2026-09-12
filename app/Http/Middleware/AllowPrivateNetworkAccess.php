<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow Private Network Access for this LAN-only app.
 *
 * The Pi is reached as http://layratepi.local, which browsers may resolve
 * to different address spaces per request (global IPv6 via mDNS vs.
 * RFC-1918 IPv4, or a different interface after a network hop). Chrome then
 * treats the page as *less private* than its own subresources (Turbo
 * frames, EventSource streams, fetch POSTs) and blocks them with:
 *
 *   "The request client is not a secure context and the resource is in
 *    more-private address space `local`."
 *
 * Answering the Private Network Access preflight and stamping normal
 * responses with Access-Control-Allow-Private-Network keeps same-host
 * requests working. The Origin is only ever reflected for local/private
 * hosts — never for public internet origins.
 */
class AllowPrivateNetworkAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if ($request->isMethod('OPTIONS') && $origin !== null) {
            return $this->preflightResponse($request, $origin);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($origin !== null && $this->isLocalOrigin($origin, $request->getHost())) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Private-Network', 'true');
            $response->headers->set('Vary', trim($response->headers->get('Vary').', Origin', ', '));
        }

        return $response;
    }

    private function preflightResponse(Request $request, string $origin): Response
    {
        $response = response('', 204);

        if (! $this->isLocalOrigin($origin, $request->getHost())) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Private-Network', 'true');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $requestedHeaders = $request->headers->get('Access-Control-Request-Headers');
        $response->headers->set(
            'Access-Control-Allow-Headers',
            $requestedHeaders ?: 'Content-Type, X-CSRF-TOKEN, X-Requested-With, Accept, Authorization'
        );
        $response->headers->set('Access-Control-Max-Age', '600');
        $response->headers->set('Vary', 'Origin');

        return $response;
    }

    private function isLocalOrigin(string $origin, string $requestHost): bool
    {
        $host = strtolower((string) parse_url($origin, PHP_URL_HOST));

        if ($host === '' || $host === strtolower($requestHost)) {
            return true;
        }

        foreach (['localhost', '.local', '.localhost', '.home.arpa', '.invalid'] as $suffix) {
            if ($host === ltrim($suffix, '.') || str_ends_with($host, $suffix)) {
                return true;
            }
        }

        // IPv4 loopback / link-local / RFC-1918.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($host);
            return ($long & ip2long('255.0.0.0')) === ip2long('127.0.0.0')
                || ($long & ip2long('255.0.0.0')) === ip2long('10.0.0.0')
                || ($long & ip2long('255.240.0.0')) === ip2long('172.16.0.0')
                || ($long & ip2long('255.255.0.0')) === ip2long('192.168.0.0')
                || ($long & ip2long('255.255.0.0')) === ip2long('169.254.0.0');
        }

        // IPv6 loopback / link-local / unique-local.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($host);
            $first = ord($packed[0]);
            $second = ord($packed[1]);
            return $host === '::1'
                || ($first === 0xfe && ($second & 0xc0) === 0x80)
                || ($first & 0xfe) === 0xfc;
        }

        return false;
    }
}
