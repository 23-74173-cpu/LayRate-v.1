<?php

namespace App\Http\Controllers;

use App\Exports\AllReportsExport;
use App\Exports\ReportSheetExport;
use App\Models\Cage;
use App\Models\EggStockBatch;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    private const ALL_TYPES = ['production', 'feed', 'environment', 'mortality', 'egg_stock'];

    public function index(Request $request)
    {
        [$type, $from, $to, $cageId, $reason, $allCages] = $this->filtersFromRequest($request);
        $charts = $request->boolean('charts');
        // Item #84: results land on a preview table first; the printable
        // letterhead document is an explicit second step (?full=1).
        $full = $request->boolean('full');

        if ($type === 'all') {
            $sections = $this->buildSections($from, $to, $cageId, $reason, $allCages, $charts);

            if (!$full) {
                foreach ($sections as &$section) {
                    $section['rows'] = $this->paginateSection($section['rows'], $request, "page_{$section['type']}");
                }
                unset($section);
            }

            $chartsPayload = collect($sections)->pluck('chart', 'type')->all();

            return $this->noStore(view('reports', compact('type', 'from', 'to', 'cageId', 'reason', 'allCages', 'sections', 'full', 'charts', 'chartsPayload')));
        }

        $rows    = $this->buildReport($type, $from, $to, $cageId, $reason, $allCages);
        $summary = $this->buildSummary($type, $from, $to, $cageId, $reason, $allCages);
        $chart   = $charts ? $this->buildChartData($type, $from, $to, $cageId, $reason, $allCages) : null;

        // The preview table is paginated; the printable document (?full=1) and
        // the CSV export both need every row, so pagination only applies here.
        if (!$full) {
            $rows = $this->paginateCollection($rows, $request);
        }

        $chartsPayload = $chart ? [$type => $chart] : [];

        return $this->noStore(view('reports', compact('type', 'from', 'to', 'cageId', 'reason', 'allCages', 'rows', 'summary', 'full', 'charts', 'chart', 'chartsPayload')));
    }

    // Chrome's back-forward cache (bfcache) restores a page from an in-memory
    // snapshot on back/forward navigation without re-running its scripts —
    // any JS fix landing after that snapshot was taken (e.g. the chart
    // print-readiness logic) wouldn't take effect until a real reload.
    // Cache-Control: no-store is the documented way to opt a page out of
    // bfcache entirely, so "View Printable Report" / "Back to Preview" always
    // get a fresh script evaluation — this report page shows live data anyway,
    // so it shouldn't be cached either way.
    private function noStore($view)
    {
        return response($view)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    private function filtersFromRequest(Request $request): array
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

        return [$type, $from, $to, $cageId, $reason, $allCages];
    }

    private function paginateCollection($items, Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $page  = LengthAwarePaginator::resolveCurrentPage();
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($slice, $items->count(), $perPage, $page, [
            'path'  => LengthAwarePaginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);
    }

    // Used for each "All Reports" section, which needs its own page state
    // (page_production, page_feed, ...) independent of the other sections —
    // unlike paginateCollection() above (single-type, untouched), the path is
    // pinned to route('reports') because this is also called from data()
    // (GET /reports/data), where the current request path would be wrong.
    private function paginateSection($rows, Request $request, string $pageName, int $perPage = 20): LengthAwarePaginator
    {
        $page  = (int) $request->get($pageName, 1);
        $total = $rows->count();

        return new LengthAwarePaginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => route('reports'), 'query' => $request->query(), 'pageName' => $pageName]
        );
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'production'  => 'Production Report',
            'feed'        => 'Feed Report',
            'environment' => 'Environment Report',
            'mortality'   => 'Mortality Report',
            'egg_stock'   => 'Egg Stock Report',
            default       => ucfirst($type) . ' Report',
        };
    }

    private function resolveCageIds($cageId, $allCages)
    {
        return $cageId === 'all'
            ? $allCages->pluck('id')
            : [$allCages->where('cage_code', $cageId)->first()?->id];
    }

    // Builds all five report types as independent sections for type=all.
    // The mortality `reason` filter is scoped to only the mortality section —
    // the other four always run unfiltered by reason.
    private function buildSections($from, $to, $cageId, $reason, $allCages, bool $charts): array
    {
        return collect(self::ALL_TYPES)->map(function ($t) use ($from, $to, $cageId, $reason, $allCages, $charts) {
            $sectionReason = $t === 'mortality' ? $reason : 'all';

            return [
                'type'    => $t,
                'label'   => $this->typeLabel($t),
                'rows'    => $this->buildReport($t, $from, $to, $cageId, $sectionReason, $allCages),
                'summary' => $this->buildSummary($t, $from, $to, $cageId, $sectionReason, $allCages),
                'chart'   => $charts ? $this->buildChartData($t, $from, $to, $cageId, $sectionReason, $allCages) : null,
            ];
        })->all();
    }

    private function buildReport($type, $from, $to, $cageId, $reason, $allCages)
    {
        $cageIds = $this->resolveCageIds($cageId, $allCages);

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
        $cageIds = $this->resolveCageIds($cageId, $allCages);

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

    // ── Chart data (only built when the "Include Graphs" checkbox is on) ──

    private function buildChartData(string $type, $from, $to, $cageId, $reason, $allCages): ?array
    {
        $cageIds = $this->resolveCageIds($cageId, $allCages);

        return match ($type) {
            'production'  => $this->productionChart($from, $to, $cageIds),
            'feed'        => $this->feedChart($from, $to, $cageIds),
            'environment' => $this->environmentChart($from, $to, $cageIds),
            'mortality'   => $this->mortalityChart($from, $to, $cageIds, $reason),
            'egg_stock'   => $this->eggStockChart($from, $to, $cageIds, $cageId),
            default       => null,
        };
    }

    private function productionChart($from, $to, $cageIds): array
    {
        $hasRange = $from && $to;
        $rows = ProductionLog::whereHas('cageSlot', fn($q) => $q->whereIn('cage_id', $cageIds))
            ->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]))
            ->selectRaw('log_date, SUM(egg_count) as eggs, AVG(hdep) as hdep')
            ->groupBy('log_date')
            ->orderBy('log_date')
            ->get();

        return [
            'kind'   => 'production',
            'labels' => $rows->map(fn($r) => $r->log_date->format('Y-m-d'))->all(),
            'eggs'   => $rows->map(fn($r) => (int) $r->eggs)->all(),
            'hdep'   => $rows->map(fn($r) => round((float) $r->hdep, 1))->all(),
        ];
    }

    private function feedChart($from, $to, $cageIds): array
    {
        $hasRange = $from && $to;
        $rows = FeedConsumptionLog::whereIn('cage_id', $cageIds)
            ->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]))
            ->selectRaw('log_date, SUM(feed_consumed_kg) as kg')
            ->groupBy('log_date')
            ->orderBy('log_date')
            ->get();

        return [
            'kind'   => 'feed',
            'labels' => $rows->map(fn($r) => $r->log_date->format('Y-m-d'))->all(),
            'kg'     => $rows->map(fn($r) => round((float) $r->kg, 1))->all(),
        ];
    }

    private function environmentChart($from, $to, $cageIds): array
    {
        $hasRange = $from && $to;
        $rows = EnvironmentalLog::whereIn('cage_id', $cageIds)
            ->when($hasRange, fn($q) => $q->whereBetween('recorded_at', [$from . ' 00:00:00', $to . ' 23:59:59']))
            ->selectRaw('DATE(recorded_at) as log_date, AVG(temperature_c) as avg_temp, AVG(humidity_pct) as avg_hum')
            ->groupBy(DB::raw('DATE(recorded_at)'))
            ->orderBy('log_date')
            ->get();

        return [
            'kind'     => 'environment',
            'labels'   => $rows->pluck('log_date')->all(),
            'temp'     => $rows->map(fn($r) => round((float) $r->avg_temp, 1))->all(),
            'humidity' => $rows->map(fn($r) => round((float) $r->avg_hum, 1))->all(),
        ];
    }

    private function mortalityChart($from, $to, $cageIds, $reason): array
    {
        $hasRange = $from && $to;
        $query = MortalityLog::whereIn('cage_id', $cageIds)
            ->when($hasRange, fn($q) => $q->whereBetween('log_date', [$from, $to]));

        if ($reason !== 'all') {
            $query->where('reason', $reason);
        }

        $rows = $query->selectRaw('reason, SUM(count) as total')
            ->groupBy('reason')
            ->orderByDesc('total')
            ->get();

        return [
            'kind'   => 'mortality',
            'labels' => $rows->pluck('reason')->all(),
            'counts' => $rows->map(fn($r) => (int) $r->total)->all(),
        ];
    }

    private function eggStockChart($from, $to, $cageIds, $cageId): array
    {
        $rows = $this->eggStockQuery($from, $to, $cageIds, $cageId)
            ->selectRaw('egg_size, SUM(`count`) as total')
            ->groupBy('egg_size')
            ->orderByDesc('total')
            ->get();

        return [
            'kind'   => 'egg_stock',
            'labels' => $rows->map(fn($r) => ucfirst($r->egg_size))->all(),
            'counts' => $rows->map(fn($r) => (int) $r->total)->all(),
        ];
    }

    public function data(Request $request)
    {
        [$type, $from, $to, $cageId, $reason, $allCages] = $this->filtersFromRequest($request);
        $charts = $request->boolean('charts');

        if ($type === 'all') {
            $sections = $this->buildSections($from, $to, $cageId, $reason, $allCages, $charts);
            $total = 0;
            foreach ($sections as &$section) {
                $total += $section['rows']->count();
                $section['rows'] = $this->paginateSection($section['rows'], $request, "page_{$section['type']}");
            }
            unset($section);

            $chartsPayload = collect($sections)->pluck('chart', 'type')->all();

            $previewHtml = view('reports._preview', [
                'type'         => $type,
                'from'         => $from,
                'to'           => $to,
                'cageId'       => $cageId,
                'reason'       => $reason,
                'allCages'     => $allCages,
                'sections'     => $sections,
                'charts'       => $charts,
                'cageColorMap' => Cage::getColorMap(),
            ])->render();

            return response()->json([
                'html'   => $previewHtml,
                'charts' => $chartsPayload,
                'meta'   => ['total' => $total, 'type' => $type, 'cageId' => $cageId, 'from' => $from, 'to' => $to],
            ]);
        }

        $page = (int) $request->get('page', 1);
        $rows     = $this->buildReport($type, $from, $to, $cageId, $reason, $allCages);
        $summary  = $this->buildSummary($type, $from, $to, $cageId, $reason, $allCages);
        $chart    = $charts ? $this->buildChartData($type, $from, $to, $cageId, $reason, $allCages) : null;

        $perPage = 20;
        $total   = $rows->count();
        $paginator = new LengthAwarePaginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => route('reports'), 'query' => $request->query()],
        );

        $previewHtml = view('reports._preview', [
            'type'         => $type,
            'from'         => $from,
            'to'           => $to,
            'cageId'       => $cageId,
            'reason'       => $reason,
            'allCages'     => $allCages,
            'rows'         => $paginator,
            'summary'      => $summary,
            'charts'       => $charts,
            'chart'        => $chart,
            'cageColorMap' => Cage::getColorMap(),
        ])->render();

        return response()->json([
            'html'   => $previewHtml,
            'charts' => $chart ? [$type => $chart] : [],
            'meta'   => [
                'total'  => $total,
                'type'   => $type,
                'cageId' => $cageId,
                'from'   => $from,
                'to'     => $to,
            ],
        ]);
    }

    public function exportCsv(Request $request)
    {
        [$type, $from, $to, $cageId, $reason, $allCages] = $this->filtersFromRequest($request);

        $rangeLabel = ($from && $to) ? "{$from}_to_{$to}" : 'all_time';
        $filename = "layrate_{$type}_{$rangeLabel}.csv";
        $headers  = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename={$filename}"];

        if ($type === 'all') {
            $sections = $this->buildSections($from, $to, $cageId, $reason, $allCages, false);

            $callback = function () use ($sections) {
                $out = fopen('php://output', 'w');
                foreach ($sections as $i => $section) {
                    if ($i > 0) {
                        fputcsv($out, []);
                    }
                    fputcsv($out, [$section['label']]);
                    $rows = $section['rows'];
                    if ($rows->isNotEmpty()) {
                        fputcsv($out, array_keys((array) $rows->first()));
                        foreach ($rows as $row) {
                            fputcsv($out, (array) $row);
                        }
                    }
                }
                fclose($out);
            };

            return response()->stream($callback, 200, $headers);
        }

        $rows = $this->buildReport($type, $from, $to, $cageId, $reason, $allCages);

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

    public function exportExcel(Request $request)
    {
        [$type, $from, $to, $cageId, $reason, $allCages] = $this->filtersFromRequest($request);
        $rangeLabel = ($from && $to) ? "{$from}_to_{$to}" : 'all_time';
        $filename = "layrate_{$type}_{$rangeLabel}.xlsx";

        $chartTempFiles = [];

        try {
            $chartTempFiles = $this->decodeChartImages($request->input('chart_images', []));

            if ($type === 'all') {
                $sections = $this->buildSections($from, $to, $cageId, $reason, $allCages, false);

                return Excel::download(new AllReportsExport($sections, $chartTempFiles), $filename);
            }

            $rows = $this->buildReport($type, $from, $to, $cageId, $reason, $allCages);

            return Excel::download(new ReportSheetExport($this->typeLabel($type), $rows, $chartTempFiles), $filename);
        } finally {
            if (!empty($chartTempFiles)) {
                register_shutdown_function(function () use ($chartTempFiles) {
                    foreach ($chartTempFiles as $path) {
                        file_exists($path) && @unlink($path);
                    }
                });
            }
        }
    }

    public function exportPdf(Request $request)
    {
        ini_set('memory_limit', '256M');

        [$type, $from, $to, $cageId, $reason, $allCages] = $this->filtersFromRequest($request);
        $rangeLabel = ($from && $to) ? "{$from}_to_{$to}" : 'all_time';
        $filename = "layrate_{$type}_{$rangeLabel}.pdf";

        $chartImages = $this->validateChartImages($request->input('chart_images', []));

        if ($type === 'all') {
            $sections = $this->buildSections($from, $to, $cageId, $reason, $allCages, false);
        } else {
            $sections = [[
                'type'    => $type,
                'label'   => $this->typeLabel($type),
                'rows'    => $this->buildReport($type, $from, $to, $cageId, $reason, $allCages),
                'summary' => $this->buildSummary($type, $from, $to, $cageId, $reason, $allCages),
            ]];
        }

        try {
            $pdf = Pdf::loadView('reports.pdf', [
                'sections'    => $sections,
                'type'        => $type,
                'from'        => $from,
                'to'          => $to,
                'cageId'      => $cageId,
                'chartImages' => $chartImages,
            ])->setPaper('a4', 'portrait');

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::warning('Report PDF export failed with chart images, retrying without: ' . $e->getMessage());
            $pdf = Pdf::loadView('reports.pdf', [
                'sections'    => $sections,
                'type'        => $type,
                'from'        => $from,
                'to'          => $to,
                'cageId'      => $cageId,
                'chartImages' => [],
            ])->setPaper('a4', 'portrait');

            return $pdf->download($filename);
        }
    }

    private function validateChartImages(array $rawImages): array
    {
        $valid = [];
        foreach ($rawImages as $type => $dataUrl) {
            if (!is_string($dataUrl)) continue;
            if (!preg_match('/^data:image\\/png;base64,[A-Za-z0-9+\/=]+$/', $dataUrl)) {
                Log::warning("Report export: invalid chart image format for [{$type}], skipping");
                continue;
            }
            $valid[$type] = $dataUrl;
        }
        return $valid;
    }

    private function decodeChartImages(array $rawImages): array
    {
        $tempFiles = [];
        foreach ($rawImages as $type => $dataUrl) {
            if (!is_string($dataUrl)) continue;
            if (!preg_match('/^data:image\\/png;base64,[A-Za-z0-9+\/=]+$/', $dataUrl)) {
                Log::warning("Report export: invalid chart image format for [{$type}], skipping");
                continue;
            }
            $encoded = explode(',', $dataUrl, 2)[1] ?? '';
            $decoded = base64_decode($encoded, true);
            if ($decoded === false) {
                Log::warning("Report export: base64 decode failed for [{$type}], skipping");
                continue;
            }
            if (strlen($decoded) > 5 * 1024 * 1024) {
                Log::warning("Report export: chart image [{$type}] exceeds 5 MB limit, skipping");
                continue;
            }
            $tmp = @tempnam(sys_get_temp_dir(), 'lre_chart_');
            if ($tmp === false) {
                Log::warning("Report export: failed to create temp file for [{$type}], skipping");
                continue;
            }
            $written = @file_put_contents($tmp, $decoded);
            if ($written === false) {
                Log::warning("Report export: failed to write temp file for [{$type}], skipping");
                @unlink($tmp);
                continue;
            }
            $tempFiles[$type] = $tmp;
        }
        return $tempFiles;
    }
}
