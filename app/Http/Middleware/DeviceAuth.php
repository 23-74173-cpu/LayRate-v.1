<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lightweight LAN authentication for sensor-ingestion devices.
 *
 * Validates the X-Device-Key header against hashed keys stored in the
 * devices table. Rejects requests that are missing, malformed, or invalid.
 */
class DeviceAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainKey = $request->header('X-Device-Key');

        if (empty($plainKey) || ! is_string($plainKey)) {
            return response()->json(['message' => 'Missing or invalid device key.'], 401);
        }

        $device = Device::where('is_active', true)->get()
            ->first(fn (Device $d) => $d->verifyApiKey($plainKey));

        if (! $device) {
            return response()->json(['message' => 'Unrecognized device key.'], 401);
        }

        // Make the authenticated device available to controllers.
        $request->attributes->set('device', $device);

        return $next($request);
    }
}
