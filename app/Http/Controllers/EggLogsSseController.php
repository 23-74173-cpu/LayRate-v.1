<?php

namespace App\Http\Controllers;

use App\Models\ProductionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EggLogsSseController extends Controller
{
    public function stream(Request $request)
    {
        $lastKnownId = (int) $request->query('since', 0);
        $lastVersion = (int) $request->query('version', -1);
        $version = (int) Cache::get('egg_logs_sse_version', 0);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // One pass per connection, then the browser's EventSource reconnects
        // after the retry delay (3 s, the same check interval as before). The
        // old 10-second sleep loop kept a PHP-FPM worker busy per open tab.
        // The page only reloads its list when latest_id goes past the id it
        // already has, so sending the same id again is harmless.
        echo "retry: 3000\n\n";

        $latestId = (int) ProductionLog::max('id');

        if ($latestId > $lastKnownId || $version !== $lastVersion) {
            echo "event: log_update\n";
            echo "data: " . json_encode(['latest_id' => $latestId, 'version' => $version]) . "\n\n";
        }

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
