<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $type   = $request->get('type', 'production');
        $from   = $request->get('from');
        $to     = $request->get('to');
        $cageId = $request->get('cage', 'all');
        $reason = $request->get('reason', 'all');

        $allCages = Cage::orderBy('cage_code')->get();
        $rows    = collect();
        $summary = null;

        if ($from && $to) {
            $rows    = $this->buildReport($type, $from, $to, $cageId, $reason, $allCages);
            $summary = $this->buildSummary($type, $from, $to, $cageId, $reason, $allCages);
        }

        return view('reports', compact('type', 'from', 'to', 'cageId', 'reason', 'allCages', 'rows', 'summary'));
    }

    private function buildReport($type, $from, $to, $cageId, $reason, $allCages)
    {
        $cageIds = $cageId === 'all'
            ? $allCages->pluck('id')
            : [$allCages->where('cage_code', $cageId)->first()?->id];

        return match($type) {
            'feed'        => $this->feedReport($from, $to, $cageIds),
            'environment' => $this->environmentReport($from, $to, $cageIds),
            'mortality'   => $this->mortalityReport($from, $to, $cageIds, $reason),
            default       => $this->productionReport($from, $to, $cageIds, $allCages),
        };
    }

    private function buildSummary($type, $from, $to, $cageId, $reason, $allCages): ?object
    {
        $cageIds = $cageId === 'all'
            ? $allCages->pluck('id')
            : [$allCages->where('cage_code', $cageId)->first()?->id];

        return match($type) {
            'production' => (object) [
                'total_eggs'  => $this->productionLogsForCages($cageIds)->whereBetween('log_date', [$from, $to])->sum('egg_count'),
                'avg_hdep'    => number_format($this->productionLogsForCages($cageIds)->whereBetween('log_date', [$from, $to])->avg('hdep') ?? 0, 1) . '%',
                'total_hens'  => $this->productionLogsForCages($cageIds)->whereBetween('log_date', [$from, $to])->max('hen_count') ?? 0,
                'days'        => $this->productionLogsForCages($cageIds)->whereBetween('log_date', [$from, $to])->distinct('log_date')->count('log_date'),
            ],
            'feed' => (object) [
                'total_kg'    => number_format(FeedConsumptionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->sum('feed_consumed_kg'), 1),
                'avg_per_day' => number_format(FeedConsumptionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->avg('feed_consumed_kg') ?? 0, 1),
                'batches'     => FeedConsumptionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->distinct('feed_batch_id')->count('feed_batch_id'),
                'days'        => FeedConsumptionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->distinct('log_date')->count('log_date'),
            ],
            'environment' => (object) [
                'avg_temp'    => number_format(EnvironmentalLog::whereIn('cage_id', $cageIds)->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59'])->avg('temperature_c') ?? 0, 1) . '°C',
                'avg_hum'     => number_format(EnvironmentalLog::whereIn('cage_id', $cageIds)->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59'])->avg('humidity_pct') ?? 0, 1) . '%',
                'readings'    => EnvironmentalLog::whereIn('cage_id', $cageIds)->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59'])->count(),
                'alerts'      => EnvironmentalLog::whereIn('cage_id', $cageIds)->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59'])->where(fn($q) => $q->where('temperature_c', '>', 30)->orWhere('humidity_pct', '>', 70))->count(),
            ],
            'mortality' => (object) [
                'total_deaths'  => MortalityLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->sum('count'),
                'top_cause'     => MortalityLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->selectRaw('reason, SUM(`count`) as total')->groupBy('reason')->orderByDesc('total')->value('reason') ?? '—',
                'most_affected' => optional($allCages->find(MortalityLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->selectRaw('cage_id, SUM(`count`) as total')->groupBy('cage_id')->orderByDesc('total')->value('cage_id')))->cage_code ?? '—',
                'days'          => MortalityLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->distinct('log_date')->count('log_date'),
            ],
            default => null,
        };
    }

    private function productionReport($from, $to, $cageIds, $allCages)
    {
        return $this->productionLogsForCages($cageIds)
            ->with(['cageSlot.cage', 'cageSlot.hens'])
            ->whereBetween('log_date', [$from, $to])
            ->orderByDesc('log_date')
            ->get()
            ->map(function ($log) {
                $cageId = $log->cageSlot->cage_id;
                $feed = FeedConsumptionLog::with('feedBatch')
                    ->where('cage_id', $cageId)
                    ->where('log_date', $log->log_date)
                    ->first();
                $env = EnvironmentalLog::where('cage_id', $cageId)
                    ->whereDate('recorded_at', $log->log_date)
                    ->avg('temperature_c');
                $hum = EnvironmentalLog::where('cage_id', $cageId)
                    ->whereDate('recorded_at', $log->log_date)
                    ->avg('humidity_pct');

                return (object) [
                    'date'     => $log->log_date->format('Y-m-d'),
                    'cage'     => $log->cageSlot->cage->cage_code,
                    'slot'     => $log->cageSlot->label,
                    'breed'    => $log->cageSlot->hens->first()?->breed ?? '—',
                    'eggs'     => $log->egg_count,
                    'hens'     => $log->hen_count,
                    'hdep'     => number_format($log->hdep, 1) . '%',
                    'feed_kg'  => $feed ? number_format($feed->feed_consumed_kg, 1) : '—',
                    'cp_pct'   => $feed?->feedBatch ? number_format($feed->feedBatch->crude_protein, 1) . '%' : '—',
                    'temp'     => $env ? number_format($env, 1) : '—',
                    'humidity' => $hum ? number_format($hum, 1) . '%' : '—',
                ];
            });
    }

    private function productionLogsForCages($cageIds)
    {
        return ProductionLog::join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->whereIn('cage_slots.cage_id', $cageIds)
            ->select('production_logs.*');
    }

    private function feedReport($from, $to, $cageIds)
    {
        return FeedConsumptionLog::with(['cage', 'feedBatch'])
            ->whereIn('cage_id', $cageIds)
            ->whereBetween('log_date', [$from, $to])
            ->orderByDesc('log_date')
            ->get()
            ->map(fn($l) => (object) [
                'date'     => $l->log_date->format('Y-m-d'),
                'cage'     => $l->cage->cage_code,
                'batch'    => $l->feedBatch->batch_code,
                'consumed' => number_format($l->feed_consumed_kg, 2) . ' kg',
                'cp_pct'   => number_format($l->feedBatch->crude_protein, 1) . '%',
                'notes'    => $l->feedBatch->notes ?? '—',
            ]);
    }

    private function environmentReport($from, $to, $cageIds)
    {
        return EnvironmentalLog::with('cage')
            ->whereIn('cage_id', $cageIds)
            ->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderByDesc('recorded_at')
            ->limit(200)
            ->get()
            ->map(fn($l) => (object) [
                'datetime' => $l->recorded_at->format('Y-m-d H:i'),
                'cage'     => $l->cage->cage_code,
                'temp'     => $l->temperature_c . '°C',
                'humidity' => $l->humidity_pct . '%',
                'status'   => ($l->temperature_c > 30 || $l->humidity_pct > 70) ? 'Alert'
                            : (($l->temperature_c > 28.5 || $l->humidity_pct >= 70) ? 'Watch' : 'Normal'),
            ]);
    }

    private function mortalityReport($from, $to, $cageIds, $reason)
    {
        $query = MortalityLog::with('cage')
            ->whereIn('cage_id', $cageIds)
            ->whereBetween('log_date', [$from, $to])
            ->orderByDesc('log_date');

        if ($reason !== 'all') {
            $query->where('reason', $reason);
        }

        return $query->get()->map(fn($l) => (object) [
            'date'   => $l->log_date->format('Y-m-d'),
            'cage'   => $l->cage->cage_code,
            'count'  => $l->count,
            'reason' => $l->reason,
            'notes'  => $l->notes ?? '—',
        ]);
    }

    public function exportCsv(Request $request)
    {
        $type   = $request->get('type', 'production');
        $from   = $request->get('from');
        $to     = $request->get('to');
        $cageId = $request->get('cage', 'all');
        $reason = $request->get('reason', 'all');

        $allCages = Cage::orderBy('cage_code')->get();
        $rows = $this->buildReport($type, $from, $to, $cageId, $reason, $allCages);

        $filename = "layrate_{$type}_{$from}_to_{$to}.csv";
        $headers  = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename={$filename}"];

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($rows->isNotEmpty()) {
                fputcsv($out, array_keys((array) $rows->first()));
                foreach ($rows as $row) {
                    fputcsv($out, (array) $row);
                }
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
