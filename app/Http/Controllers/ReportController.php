<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\EggStockBatch;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $type   = $request->get('type', 'production');
        // No date filter means "all time", not "nothing" — a first page load
        // or a Generate click with the date fields untouched must still
        // produce a real report instead of silently showing empty results.
        $from   = $request->get('from') ?: null;
        $to     = $request->get('to') ?: null;
        $cageId = $request->get('cage', 'all');
        $reason = $request->get('reason', 'all');

        $allCages = Cage::orderBy('cage_code')->get();
        // Item #84: results land on a preview table first; the printable
        // letterhead document is an explicit second step (?full=1).
        $full    = $request->boolean('full');

        $rows    = $this->buildReport($type, $from, $to, $cageId, $reason, $allCages);
        $summary = $this->buildSummary($type, $from, $to, $cageId, $reason, $allCages);

        return view('reports', compact('type', 'from', 'to', 'cageId', 'reason', 'allCages', 'rows', 'summary', 'full'));
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
            'egg_stock'   => $this->eggStockReport($from, $to, $cageIds, $cageId),
            default       => $this->productionReport($from, $to, $cageIds, $allCages),
        };
    }

    private function buildSummary($type, $from, $to, $cageId, $reason, $allCages): ?object
    {
        $cageIds = $cageId === 'all'
            ? $allCages->pluck('id')
            : [$allCages->where('cage_code', $cageId)->first()?->id];

        $hasRange = $from && $to;

        return match($type) {
            'production' => (object) [
                'total_eggs'  => ProductionLog::whereHas('cageSlot', fn($q) => $q->whereIn('cage_id', $cageIds))->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]))->sum('egg_count'),
                'avg_hdep'    => number_format(ProductionLog::whereHas('cageSlot', fn($q) => $q->whereIn('cage_id', $cageIds))->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]))->avg('hdep') ?? 0, 1) . '%',
                'total_hens'  => Hen::whereHas('cageSlot', fn($q) => $q->whereIn('cage_id', $cageIds))->where('is_active', 1)->count(),
                'days'        => ProductionLog::whereHas('cageSlot', fn($q) => $q->whereIn('cage_id', $cageIds))->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]))->distinct('log_date')->count('log_date'),
            ],
            'feed' => (object) [
                'total_kg'    => number_format(FeedConsumptionLog::whereIn('cage_id', $cageIds)->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]))->sum('feed_consumed_kg'), 1),
                'avg_per_day' => number_format(FeedConsumptionLog::whereIn('cage_id', $cageIds)->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]))->avg('feed_consumed_kg') ?? 0, 1),
                'batches'     => FeedConsumptionLog::whereIn('cage_id', $cageIds)->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]))->distinct('feed_batch_id')->count('feed_batch_id'),
                'days'        => FeedConsumptionLog::whereIn('cage_id', $cageIds)->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]))->distinct('log_date')->count('log_date'),
            ],
            'environment' => (object) [
                'avg_temp'    => number_format(EnvironmentalLog::whereIn('cage_id', $cageIds)->when($hasRange, fn($q) => $q->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59']))->avg('temperature_c') ?? 0, 1) . '°C',
                'avg_hum'     => number_format(EnvironmentalLog::whereIn('cage_id', $cageIds)->when($hasRange, fn($q) => $q->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59']))->avg('humidity_pct') ?? 0, 1) . '%',
                'readings'    => EnvironmentalLog::whereIn('cage_id', $cageIds)->when($hasRange, fn($q) => $q->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59']))->count(),
                'alerts'      => EnvironmentalLog::whereIn('cage_id', $cageIds)->when($hasRange, fn($q) => $q->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59']))->where(fn($q) => $q->where('temperature_c', '>', 30)->orWhere('humidity_pct', '>', 70))->count(),
            ],
            'egg_stock' => (object) [
                'total_stocked' => (int) $this->eggStockQuery($from, $to, $cageIds, $cageId)->sum('count'),
                'batches'       => $this->eggStockQuery($from, $to, $cageIds, $cageId)->count(),
                'top_size'      => ucfirst($this->eggStockQuery($from, $to, $cageIds, $cageId)->selectRaw('egg_size, SUM(`count`) as total')->groupBy('egg_size')->orderByDesc('total')->value('egg_size') ?? '—'),
                'days'          => $this->eggStockQuery($from, $to, $cageIds, $cageId)->distinct('harvested_date')->count('harvested_date'),
            ],
            'mortality' => (object) [
                'total_deaths'  => MortalityLog::whereIn('cage_id', $cageIds)->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]))->sum('count'),
                'top_cause'     => MortalityLog::whereIn('cage_id', $cageIds)->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]))->selectRaw('reason, SUM(`count`) as total')->groupBy('reason')->orderByDesc('total')->value('reason') ?? '—',
                'most_affected' => optional($allCages->find(MortalityLog::whereIn('cage_id', $cageIds)->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]))->selectRaw('cage_id, SUM(`count`) as total')->groupBy('cage_id')->orderByDesc('total')->value('cage_id')))->cage_code ?? '—',
                'days'          => MortalityLog::whereIn('cage_id', $cageIds)->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]))->distinct('log_date')->count('log_date'),
            ],
            default => null,
        };
    }

    private function productionReport($from, $to, $cageIds, $allCages)
    {
        // Hens must be filtered to active here (same as AnalyticsController) —
        // an unfiltered ->first() can attribute a dead/transferred hen's breed
        // to a historical production row.
        $hasRange = $from && $to;

        $logs = ProductionLog::with(['cageSlot.cage', 'cageSlot.hens' => fn($q) => $q->where('is_active', 1)])
            ->whereHas('cageSlot', fn($q) => $q->whereIn('cage_id', $cageIds))
            ->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]))
            ->orderByDesc('log_date')
            ->get();

        $feedLogs = FeedConsumptionLog::with('feedBatch')
            ->whereIn('cage_id', $cageIds)
            ->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]))
            ->get()
            ->keyBy(fn($f) => $f->log_date->format('Y-m-d') . '-' . $f->cage_id);

        $envData = EnvironmentalLog::whereIn('cage_id', $cageIds)
            ->when($hasRange, fn($q) => $q->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59']))
            ->selectRaw('cage_id, DATE(recorded_at) as log_date, AVG(temperature_c) as avg_temp, AVG(humidity_pct) as avg_hum')
            ->groupBy('cage_id', DB::raw('DATE(recorded_at)'))
            ->get()
            ->keyBy(fn($e) => $e->log_date . '-' . $e->cage_id);

        return $logs->map(function ($log) use ($feedLogs, $envData) {
            $key = $log->log_date->format('Y-m-d') . '-' . ($log->cage?->id ?? '0');
            $feed = $feedLogs->get($key);
            $env  = $envData->get($key);

            return (object) [
                'date'     => $log->log_date->format('Y-m-d'),
                'cage'     => $log->cageSlot?->cage?->cage_code ?? '—',
                'breed'    => $log->cageSlot->hens->first()?->breed ?? '—',
                'eggs'     => $log->egg_count,
                'hens'     => $log->hen_count,
                'hdep'     => number_format($log->hdep, 1) . '%',
                'feed_kg'  => $feed ? number_format($feed->feed_consumed_kg, 1) : '—',
                'cp_pct'   => $feed?->feedBatch ? number_format($feed->feedBatch->crude_protein, 1) . '%' : '—',
                'temp'     => $env ? number_format($env->avg_temp, 1) : '—',
                'humidity' => $env ? number_format($env->avg_hum, 1) . '%' : '—',
            ];
        });
    }

    private function feedReport($from, $to, $cageIds)
    {
        return FeedConsumptionLog::with(['cage', 'feedBatch'])
            ->whereIn('cage_id', $cageIds)
            ->when($from && $to, fn($q) => $q->whereBetween('log_date', [$from, $to]))
            ->orderByDesc('log_date')
            ->get()
            ->map(fn($l) => (object) [
                'date'     => $l->log_date->format('Y-m-d'),
                'cage'     => $l->cage?->cage_code ?? '—',
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
            ->when($from && $to, fn($q) => $q->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59']))
            ->orderByDesc('recorded_at')
            ->limit(200)
            ->get()
            ->map(fn($l) => (object) [
                'datetime' => $l->recorded_at->format('Y-m-d H:i'),
                'cage'     => $l->cage?->cage_code ?? '—',
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
            ->when($from && $to, fn($q) => $q->whereBetween('log_date', [$from, $to]))
            ->orderByDesc('log_date');

        if ($reason !== 'all') {
            $query->where('reason', $reason);
        }

        return $query->get()->map(fn($l) => (object) [
            'date'   => $l->log_date->format('Y-m-d'),
                'cage'     => $l->cage?->cage_code ?? '—',
            'count'  => $l->count,
            'reason' => $l->reason,
            'notes'  => $l->notes ?? '—',
        ]);
    }

    // egg_stock_batches.cage_id is nullable (farm-level batches) — "All Cages"
    // must include NULL-cage rows, unlike the other report types.
    private function eggStockQuery($from, $to, $cageIds, $cageId)
    {
        return EggStockBatch::when($from && $to, fn($q) => $q->whereBetween('harvested_date', [$from, $to]))
            ->when($cageId === 'all',
                fn($q) => $q->where(fn($w) => $w->whereIn('cage_id', $cageIds)->orWhereNull('cage_id')),
                fn($q) => $q->whereIn('cage_id', $cageIds));
    }

    private function eggStockReport($from, $to, $cageIds, $cageId)
    {
        return $this->eggStockQuery($from, $to, $cageIds, $cageId)
            ->with('cage')
            ->orderByDesc('harvested_date')
            ->get()
            ->map(fn($b) => (object) [
                'date'      => $b->harvested_date->format('Y-m-d'),
                'cage'      => $b->cage?->cage_code ?? '—',
                'size'      => ucfirst($b->egg_size),
                'count'     => $b->count,
                'freshness' => ucfirst($b->freshness_status),
            ]);
    }

    public function exportCsv(Request $request)
    {
        $type   = $request->get('type', 'production');
        $from   = $request->get('from') ?: null;
        $to     = $request->get('to') ?: null;
        $cageId = $request->get('cage', 'all');
        $reason = $request->get('reason', 'all');

        $allCages = Cage::orderBy('cage_code')->get();
        $rows = $this->buildReport($type, $from, $to, $cageId, $reason, $allCages);

        $rangeLabel = ($from && $to) ? "{$from}_to_{$to}" : 'all_time';
        $filename = "layrate_{$type}_{$rangeLabel}.csv";
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
