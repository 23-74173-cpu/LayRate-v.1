<?php

namespace App\Http\Controllers;

use App\Models\ProductionLog;
use Illuminate\Http\Request;

class EggLogsSseController extends Controller
{
    public function stream(Request $request)
    {
        $lastKnownId = (int) $request->query('since', 0);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $maxRuntime = 60;
        $start = time();

        while (! connection_aborted() && (time() - $start) < $maxRuntime) {
            $latestId = (int) ProductionLog::max('id');

            if ($latestId > $lastKnownId) {
                echo "event: log_update\n";
                echo "data: " . json_encode(['latest_id' => $latestId]) . "\n\n";
                $lastKnownId = $latestId;
                ob_flush();
                flush();
            }

            echo ": heartbeat\n\n";
            ob_flush();
            flush();

            sleep(3);
        }
    }
}
