<?php

namespace App\Http\Controllers;

use App\Models\HardwareItem;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RelayCommandController extends Controller
{
    /**
     * Return the relay command the bridge should currently enforce.
     *
     * This is a polled "intended state" endpoint (not a one-shot queue): the
     * response reflects the persistent control_mode / relay_status on the
     * hardware item, so the bridge can re-apply it after an Arduino reboot
     * (self-healing) without any lossy command queue.
     *
     *   control_mode = auto   -> command "auto"  (firmware hysteresis)
     *   control_mode = manual -> command "on"/"off" (persistent user override)
     *
     * AUTO hysteresis thresholds ride along on this same poll: the fan turns
     * ON at temp_max and OFF at temp_max - 5C (the historical firmware dead-band,
     * derived here so there is a single source of truth — the app's temp_max —
     * and no extra schema/UI). The bridge sends a THRESH command to the Arduino
     * only when these change.
     *
     * No active relay registered to this device => {"relay": null} (bridge idles).
     */
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\Device $device */
        $device = $request->attributes->get('device');

        $relay = HardwareItem::where('device_type', 'relay')
            ->where('device_id', $device->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if (! $relay) {
            return response()->json(['relay' => null]);
        }

        $command = $relay->control_mode === 'manual'
            ? ($relay->relay_status ?: 'off')
            : 'auto';

        $thresholds = Setting::thresholds();
        $onTemp  = (float) $thresholds['temp_max'];
        $offTemp = max(0.0, $onTemp - 5.0); // derived dead-band, mirrors historical 5C gap

        return response()->json([
            'relay' => [
                'serial_number' => $relay->serial_number,
                'mode' => $relay->control_mode,
                'command' => $command,
                'on_temp' => $onTemp,
                'off_temp' => $offTemp,
            ],
        ]);
    }
}
