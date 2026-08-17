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

        $device = $this->resolveDevice($plainKey);

        if (! $device) {
            return response()->json(['message' => 'Unrecognized device key.'], 401);
        }

        // Make the authenticated device available to controllers.
        $request->attributes->set('device', $device);

        return $next($request);
    }

    /**
     * Resolve the device that owns a plain API key.
     *
     * bcrypt is deliberately slow (~50-100ms on a Pi's CPU per check), and
     * the old implementation ran one such check per active device on every
     * single ingestion request — at ~1Hz per sensor, N registered devices
     * meant up to N x 100ms of pure hashing before the controller even
     * started, on the highest-frequency endpoint in the app. Keys issued
     * after this change embed their device id (see
     * Device::generateApiKey()), so the common case is one indexed lookup
     * by primary key plus exactly one hash check.
     *
     * Falls back to the previous full-scan behavior for keys issued before
     * this change (no id prefix) and for the rare case of a malformed or
     * forged prefix — this never regresses correctness, only the
     * already-existing worst case stays as slow as it always was.
     */
    private function resolveDevice(string $plainKey): ?Device
    {
        if (preg_match('/^lr_(\d+)_/', $plainKey, $m)) {
            $device = Device::where('id', (int) $m[1])->where('is_active', true)->first();
            if ($device && $device->verifyApiKey($plainKey)) {
                return $device;
            }
        }

        return Device::where('is_active', true)->get()
            ->first(fn (Device $d) => $d->verifyApiKey($plainKey));
    }
}
