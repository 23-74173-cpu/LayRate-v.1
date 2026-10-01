<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\ProductionLog;
use App\Services\ReportingDateService;
use Illuminate\Http\Request;

class EggCountSseController extends Controller
{
    public function stream(Request $request)
    {
        $cageCode = $request->query('cage_code', 'CAGE-T');

        $cage = Cage::where('cage_code', $cageCode)->first();

        if (! $cage) {
            return response('Cage not found', 404);
        }

        $slotIds = $cage->cageSlots()->pluck('id');

        if ($slotIds->isEmpty()) {
            return response('Cage has no slots', 404);
        }

        $lastCounts = [];

        $allSlotIds = CageSlot::pluck('cage_id', 'id');

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // One pass per connection: send the current counts and end the
        // response. The browser's EventSource reconnects by itself after the
        // retry delay, so the page still updates about every 2 seconds. The
        // old version looped with sleep(1) for 8 seconds per connection,
        // keeping a PHP-FPM worker busy the whole time, so a few open Egg
        // Logging tabs could use up the worker pool and make every other
        // request wait in line.
        echo "retry: 2000\n\n";

        $today = ReportingDateService::reportingDateString();

        $logs = ProductionLog::whereIn('cage_slot_id', $slotIds)
            ->where('log_date', $today)
            ->get();

        foreach ($logs as $log) {
            $lastCounts[$log->cage_slot_id] = ['egg_count' => $log->egg_count, 'hen_count' => $log->hen_count];
        }

        foreach ($slotIds as $sid) {
            if (! isset($lastCounts[$sid])) {
                $lastCounts[$sid] = ['egg_count' => 0, 'hen_count' => 0];
            }
        }

        echo "event: count\n";
        echo "data: " . json_encode(['counts' => $lastCounts]) . "\n\n";

        $todayLogs = ProductionLog::where('log_date', $today)->get();
        $cageStats = [];

        foreach ($todayLogs as $log) {
            $cageId = $allSlotIds[$log->cage_slot_id] ?? null;
            if (! $cageId) continue;

            if (! isset($cageStats[$cageId])) {
                $cageStats[$cageId] = ['total_eggs' => 0, 'logged_slots' => []];
            }
            $cageStats[$cageId]['total_eggs'] += $log->egg_count;
            $cageStats[$cageId]['logged_slots'][$log->cage_slot_id] = true;
        }

        foreach ($cageStats as $cageId => &$stats) {
            $stats['logged_count'] = count($stats['logged_slots']);
            unset($stats['logged_slots']);
        }
        unset($stats);

        echo "event: cage_stats\n";
        echo "data: " . json_encode($cageStats) . "\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
