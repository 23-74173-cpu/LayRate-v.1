<?php

namespace App\Http\Controllers;

use App\Models\HardwareItem;
use App\Services\RelayStateService;
use Illuminate\Http\Request;

class EnvironmentRelaySseController extends Controller
{
    /**
     * Server-Sent Events stream for the relay/fan widget.
     *
     * Follows the same polling-DB-and-push pattern as EggCountSseController
     * (no Redis/queue needed): emits a `relay_state` event whenever the relay
     * snapshot changes, plus an initial snapshot on connect and heartbeats so
     * proxies keep the connection open. Browsers reconnect after the 8s cap.
     */
    public function stream(Request $request)
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $lastPayload = null;
        $maxRuntime = 8;
        $start = time();

        while (! connection_aborted() && (time() - $start) < $maxRuntime) {
            $relay = HardwareItem::with('lastChangedBy')
                ->where('device_type', 'relay')
                ->where('status', 'active')
                ->orderBy('id')
                ->first();

            $payload = RelayStateService::payload($relay);

            if ($payload !== $lastPayload) {
                $lastPayload = $payload;
                echo "event: relay_state\n";
                echo 'data: ' . json_encode($payload) . "\n\n";
                ob_flush();
                flush();
            }

            echo ": heartbeat\n\n";
            ob_flush();
            flush();

            sleep(1);
        }
    }
}
