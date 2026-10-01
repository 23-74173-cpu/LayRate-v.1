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
     * (no Redis/queue needed): each connection gets the current `relay_state`
     * snapshot, and the browser reconnects every ~2 s for the next one.
     */
    public function stream(Request $request)
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // One pass per connection: send the current relay state and end the
        // response; the browser's EventSource reconnects after the retry
        // delay. The old 8-second sleep loop kept a PHP-FPM worker busy for
        // every open Environment tab. Applying the same state again on the
        // page is harmless.
        echo "retry: 2000\n\n";

        $relay = HardwareItem::with('lastChangedBy')
            ->where('device_type', 'relay')
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        echo "event: relay_state\n";
        echo 'data: ' . json_encode(RelayStateService::payload($relay)) . "\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
