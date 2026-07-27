<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\ProductionLog;
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
        $lastCageStats = null;

        $allSlotIds = CageSlot::pluck('id', 'cage_id');

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $maxRuntime = 30;
        $start = time();

        while (! connection_aborted() && (time() - $start) < $maxRuntime) {
            $today = now()->toDateString();

            $logs = ProductionLog::whereIn('cage_slot_id', $slotIds)
                ->where('log_date', $today)
                ->get();

            $changed = false;

            foreach ($logs as $log) {
                $slotId = $log->cage_slot_id;
                $current = ['egg_count' => $log->egg_count, 'hen_count' => $log->hen_count];

                if (($lastCounts[$slotId] ?? null) !== $current) {
                    $lastCounts[$slotId] = $current;
                    $changed = true;
                }
            }

            foreach ($slotIds as $sid) {
                if (! isset($lastCounts[$sid])) {
                    $lastCounts[$sid] = ['egg_count' => 0, 'hen_count' => 0];
                    $changed = true;
                }
            }

            if ($changed) {
                echo "event: count\n";
                echo "data: " . json_encode(['counts' => $lastCounts]) . "\n\n";
                ob_flush();
                flush();
            }

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

            $statsJson = json_encode($cageStats);
            if ($statsJson !== $lastCageStats) {
                $lastCageStats = $statsJson;
                echo "event: cage_stats\n";
                echo "data: $statsJson\n\n";
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
