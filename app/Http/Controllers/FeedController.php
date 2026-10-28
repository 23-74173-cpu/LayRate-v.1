<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\FarmFeedEntry;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\Note;
use App\Models\ProductionLog;
use App\Services\FcrCalculator;
use App\Services\FinancePostingService;
use App\Services\ReportingDateService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class FeedController extends Controller
{
    public function index()
    {
        $filters = $this->feedFilters();
        return view('feed', [
            'preselectedCageId' => (int) request('cage_id') ?: null,
            'feedFilters' => $filters,
            'autopostOn' => FinancePostingService::autopostOn(),
            'autopostDate' => FinancePostingService::cutover(),
        ]);
    }

    /** Resolve explicit filters without silently rewriting the user's dates. */
    private function feedFilters(): array
    {
        $today = ReportingDateService::reportingDateString();
        $preset = request()->query('feed_preset');
        $from = request()->query('feed_from');
        $to = request()->query('feed_to');

        $cageId = filter_var(request()->query('feed_cage', request()->query('cage_id')), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        if ($cageId && ! Cage::whereKey($cageId)->where('is_active', true)->exists()) $cageId = null;

        if ($preset === 'latest') {
            $latest = FeedConsumptionLog::query()
                ->when($cageId, fn ($q) => $q->where('cage_id', $cageId))
                ->whereHas('cage', fn ($q) => $q->where('is_active', true))
                ->max('log_date');
            $from = $latest;
            $to = $latest;
            if (! $latest) {
                $from = $to = $today;
            }
        } elseif (in_array($preset, ['today', '7d', '30d'], true)) {
            $to = $today;
            $from = match ($preset) {
                'today' => $today,
                '7d' => Carbon::parse($today)->subDays(6)->toDateString(),
                default => Carbon::parse($today)->subDays(29)->toDateString(),
            };
        }

        $validDate = fn ($date) => is_string($date)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && Carbon::createFromFormat('!Y-m-d', $date)?->format('Y-m-d') === $date;
        if (! $validDate($from)) $from = null;
        if (! $validDate($to)) $to = null;
        if ($from && $to && $from > $to) [$from, $to] = [$to, $from];
        if ($to && $to > $today) $to = $today;

        if (($preset === 'latest' || $preset === 'today' || $preset === '7d' || $preset === '30d') && ! $from) {
            // An explicit empty result remains an empty result; don't turn it into an unbounded query.
            $from = $today;
            $to = $today;
        }
        return ['preset' => in_array($preset, ['today', '7d', '30d', 'latest'], true) ? $preset : '', 'from' => $from, 'to' => $to, 'cage_id' => $cageId, 'today' => $today];
    }

    public function batchSessionData()
    {
        $batchId = session('reopen_edit_batch');
        return $batchId ? FeedBatch::find($batchId) : null;
    }

    public function consumptionSessionData()
    {
        $logId = session('reopen_edit_consumption');
        return $logId ? FeedConsumptionLog::with(['cage', 'feedBatch'])->find($logId) : null;
    }

    public function farmEntrySessionData()
    {
        $entryId = session('reopen_edit_farm_entry');
        return $entryId ? FarmFeedEntry::find($entryId) : null;
    }

    public function liveData()
    {
        $filters = $this->feedFilters();
        $cageId = $filters['cage_id'];
        $dateStart = $filters['from'];
        $dateEnd = $filters['to'];
        // Lean batch fetch: the page only needs id/code/cost/qty for the modal
        // dropdowns and the JS remaining-kg map (accessors read the preloaded
        // consumed_sum, so no per-batch SUM queries). avg CP% is a single
        // aggregate instead of hydrating every batch ever recorded.
        $allBatches = FeedBatch::orderByDesc('date_received')
            ->withSum(['consumptionLogs as consumed_sum' => fn ($q) => $q
                ->when($cageId, fn ($query) => $query->where('cage_id', $cageId))
                ->when($dateStart, fn ($query) => $query->where('log_date', '>=', $dateStart))
                ->when($dateEnd, fn ($query) => $query->where('log_date', '<=', $dateEnd))], 'feed_consumed_kg')
            ->get(['id', 'batch_code', 'unit_cost', 'total_quantity_kg']);
        $avgCp = FeedBatch::query()
            ->whereHas('consumptionLogs', fn ($q) => $q->when($cageId, fn ($query) => $query->where('cage_id', $cageId))
                ->when($dateStart, fn ($query) => $query->where('log_date', '>=', $dateStart))
                ->when($dateEnd, fn ($query) => $query->where('log_date', '<=', $dateEnd)))
            ->avg('crude_protein');

        $batches = FeedBatch::withSum(['consumptionLogs as consumed_sum' => fn ($q) => $q
            ->when($cageId, fn ($query) => $query->where('cage_id', $cageId))
            ->when($dateStart, fn ($query) => $query->where('log_date', '>=', $dateStart))
            ->when($dateEnd, fn ($query) => $query->where('log_date', '<=', $dateEnd))], 'feed_consumed_kg')
            ->when($dateEnd, fn ($q) => $q->where('date_received', '<=', $dateEnd))
            ->when(request('walkthrough4') === '1' && request('wt4_batch'), fn ($q) => $q->whereKey((int) request('wt4_batch')))
            ->orderByDesc('date_received')->paginate((int) config('tables.per_page', 25))->withQueryString();

        $preselectedCageId = $cageId;

        $viewMode = request('view') === 'calendar' ? 'calendar' : 'records';
        $today = ReportingDateService::reportingDateString();
        $requestedMonth = (string) request('month', substr($today, 0, 7));
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requestedMonth)
            ? $requestedMonth
            : substr($today, 0, 7);
        $monthStart = $month.'-01';
        $monthEnd = \Carbon\Carbon::createFromFormat('!Y-m-d', $monthStart)->addMonth()->toDateString();
        $maxMonth = substr($today, 0, 7);
        if ($month > $maxMonth) {
            $month = $maxMonth;
            $monthStart = $month.'-01';
            $monthEnd = \Carbon\Carbon::createFromFormat('!Y-m-d', $monthStart)->addMonth()->toDateString();
        }

        $calendarDays = collect();
        $calendarTotals = ['total' => 0.0, 'average' => 0.0, 'days' => 0, 'missing' => 0];
        if ($viewMode === 'calendar') {
            $calendarQuery = $this->scopedConsumptionQuery($cageId, $dateStart, $dateEnd)
                ->when($preselectedCageId, fn ($q) => $q->where('cage_id', $preselectedCageId))
                ->when(request('walkthrough4') === '1' && request('wt4_log'), fn ($q) => $q->whereKey((int) request('wt4_log')))
                ->when(request('walkthrough4') === '1' && request('wt4_farm'), fn ($q) => $q->where('farm_feed_entry_id', (int) request('wt4_farm')))
                ->where('log_date', '>=', $monthStart)
                ->where('log_date', '<', $monthEnd);

            $calendarDays = (clone $calendarQuery)
                ->selectRaw('log_date, SUM(feed_consumed_kg) AS total_kg, COUNT(*) AS records')
                ->groupBy('log_date')
                ->orderBy('log_date')
                ->get()
                ->keyBy(fn ($day) => \Carbon\Carbon::parse($day->log_date)->toDateString());

            $calendarTotals = [
                'total' => (float) $calendarDays->sum('total_kg'),
                'average' => $calendarDays->isNotEmpty() ? (float) $calendarDays->avg('total_kg') : 0.0,
                'days' => $calendarDays->count(),
                'missing' => $calendarDays->filter(fn ($day) => (int) $day->records < 6)->count(),
            ];
        }

        $consumptionLogs = FeedConsumptionLog::with(['cage', 'feedBatch', 'farmFeedEntry'])
            ->when(request('walkthrough4') === '1' && request('wt4_log'), fn ($q) => $q->whereKey((int) request('wt4_log')))
            ->when(request('walkthrough4') === '1' && request('wt4_farm'), fn ($q) => $q->where('farm_feed_entry_id', (int) request('wt4_farm')))
            ->when($cageId, fn ($q) => $q->where('cage_id', $cageId))
            ->when($dateStart, fn ($q) => $q->where('log_date', '>=', $dateStart))
            ->when($dateEnd, fn ($q) => $q->where('log_date', '<=', $dateEnd))
            ->orderByDesc('log_date')
            ->orderByDesc('log_time')
            ->paginate((int) config('tables.per_page', 25))
            ->withQueryString();

        // Read-only exposure for imported aggregate feed protein. It is source
        // data, not a feed batch or purchase and must not affect inventory/cost.
        $crudeProteinByCageDate = Schema::hasColumn('feed_consumption_logs', 'crude_protein_pct')
            ? FeedConsumptionLog::query()->when($cageId, fn ($q) => $q->where('cage_id', $cageId))
                ->when($dateStart, fn ($q) => $q->where('log_date', '>=', $dateStart))
                ->when($dateEnd, fn ($q) => $q->where('log_date', '<=', $dateEnd))
                ->whereNotNull('crude_protein_pct')->get(['cage_id', 'log_date', 'crude_protein_pct'])
                ->keyBy(fn ($row) => $row->cage_id.'-'.$row->log_date->toDateString())
            : collect();

        $reportingNow = ReportingDateService::now();

        // Same stale-data fallback as the dashboard feed cards: when nothing
        // was logged in the last 7 days, anchor "this week/month" to the
        // latest feed log so the cards summarize real history instead of
        // reading 0. Live behavior is unchanged when data is current.
        $feedRef = $this->feedReferenceDate($dateEnd, $cageId);
        $feedAsOf = $feedRef->toDateString() < $reportingNow->toDateString()
            ? $feedRef->format('m/d/Y')
            : null;

        $totalFeedWeek = $this->scopedConsumptionQuery(
            $cageId,
            $dateStart ?? $feedRef->copy()->subDays(6)->toDateString(),
            $dateEnd ?? $feedRef->toDateString()
        )
            ->sum('feed_consumed_kg');

        $activeCagesCount = $cageId ? 1 : Cage::where('is_active', 1)->count();
        $avgFeedPerCage = $activeCagesCount
            ? round($totalFeedWeek / max($activeCagesCount, 1) / 7, 1)
            : 0;

        $totalFeedCostMonth = $this->scopedConsumptionQuery(
            $cageId,
            $dateStart ?? $feedRef->copy()->startOfMonth()->toDateString(),
            $dateEnd ?? $feedRef->toDateString()
        )
            ->join('feed_batches', 'feed_consumption_logs.feed_batch_id', '=', 'feed_batches.id')
            ->selectRaw('SUM(feed_consumption_logs.feed_consumed_kg * feed_batches.unit_cost) as total')
            ->whereNotNull('feed_batches.unit_cost')
            ->value('total');

        $cages = Cage::where('is_active', 1)->orderBy('cage_code')->get();

        $requestedFcrCage = request('fcr_cage_id', $cageId ? (string) $cageId : null);
        $validFcrCages = array_merge(['all'], $cages->pluck('id')->map(fn ($id) => (string) $id)->all());
        $fcrCageId = in_array($requestedFcrCage, $validFcrCages, true)
            ? $requestedFcrCage
            : ($cageId ? (string) $cageId : 'all');
        $fcrGroupBy = request('fcr_group_by', 'week');
        if (! in_array($fcrGroupBy, ['day', 'week', 'month'])) {
            $fcrGroupBy = 'week';
        }

        $fcrData = $this->computeFcrData($fcrCageId, $fcrGroupBy, $feedRef, $dateStart, $dateEnd);
        $fcrData['fcrAsOf'] = $fcrData['fcrTimeline']->isNotEmpty()
            ? 'Historical FCR through '.Carbon::parse($fcrData['fcrTimeline']->max('period_start'))->format('m/d/Y')
            : $feedAsOf;
        return view('feed._live-data', array_merge(
            compact('batches', 'allBatches', 'consumptionLogs', 'avgCp', 'totalFeedWeek', 'avgFeedPerCage',
                      'totalFeedCostMonth', 'cages', 'preselectedCageId', 'feedAsOf', 'viewMode', 'today', 'month',
                      'monthStart', 'monthEnd', 'maxMonth', 'calendarDays', 'calendarTotals', 'crudeProteinByCageDate', 'filters'),
            $fcrData
        ));
    }

    /** Safe selectors for applying shared feed/FCR filters to all page tabs. */
    private function scopedConsumptionQuery(?int $cageId, ?string $from, ?string $to)
    {
        return FeedConsumptionLog::query()
            ->when($cageId, fn ($q) => $q->where('cage_id', $cageId))
            ->when($from, fn ($q) => $q->where('log_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('log_date', '<=', $to));
    }

    public function consumptionCalendarDay(Request $request, string $date)
    {
        try {
            $day = \Carbon\Carbon::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            abort(404);
        }
        abort_unless($day && $day->format('Y-m-d') === $date, 404);
        abort_unless($date <= ReportingDateService::reportingDateString(), 404);

        $preselectedCageId = (int) $request->query('cage_id') ?: null;
        $logs = FeedConsumptionLog::with(['cage', 'feedBatch', 'farmFeedEntry'])
            ->when($preselectedCageId, fn ($q) => $q->where('cage_id', $preselectedCageId))
            ->when($request->query('walkthrough4') === '1' && $request->query('wt4_log'), fn ($q) => $q->whereKey((int) $request->query('wt4_log')))
            ->when($request->query('walkthrough4') === '1' && $request->query('wt4_farm'), fn ($q) => $q->where('farm_feed_entry_id', (int) $request->query('wt4_farm')))
            ->where('log_date', $date)
            ->orderBy('log_time')
            ->orderBy('cage_id')
            ->get();

        $cages = Cage::orderBy('cage_code')->get(['id', 'cage_code']);

        return view('feed._consumption-calendar-day', compact('date', 'logs', 'cages'));
    }

    /**
     * AJAX endpoint: returns rendered FCR content HTML.
     */
    public function fcrData()
    {
        $filters = $this->feedFilters();
        $cageId = request('fcr_cage_id', request('cage_id', request('cage', 'all')));
        $activeIds = Cage::where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->all();
        if ($cageId !== 'all' && ! in_array((string) $cageId, $activeIds, true)) $cageId = 'all';
        if ($cageId === 'all' && ! request()->has('fcr_cage_id') && ! request()->has('cage_id') && ! request()->has('cage')) {
            $cageId = $activeIds[0] ?? 'all';
        }
        $groupBy = request('group_by', 'week');
        if (! in_array($groupBy, ['day', 'week', 'month'])) {
            $groupBy = 'week';
        }

        $dateStart = $filters['from'];
        $dateEnd = $filters['to'];
        $feedRef = $this->feedReferenceDate($dateEnd, $cageId === 'all' ? null : (int) $cageId);
        $fcrData = $this->computeFcrData($cageId, $groupBy, $feedRef, $dateStart, $dateEnd);
        $hasImportedHistory = Schema::hasColumn('feed_consumption_logs', 'source_key')
            && FeedConsumptionLog::where('source_key', 'like', 'production_data_clean:%')->exists();
        $latestFeedDate = FeedConsumptionLog::query()
            ->when($hasImportedHistory && ! $dateStart && ! $dateEnd, fn ($q) => $q->where('source_key', 'like', 'production_data_clean:%:feed'))
            ->when($hasImportedHistory && ($dateStart || $dateEnd), fn ($q) => $q->where(function ($sources) {
                $sources->whereNull('feed_consumption_logs.source_key')->orWhere('feed_consumption_logs.source_key', 'like', 'production_data_clean:%:feed');
            }))
            ->when($cageId !== 'all' && $cageId !== null, fn ($q) => $q->where('cage_id', (int) $cageId))
            ->when($cageId === 'all' || $cageId === null, fn ($q) => $q->whereHas('cage', fn ($c) => $c->where('is_active', 1)))
            ->when($dateEnd, fn ($q) => $q->where('log_date', '<=', $dateEnd))
            ->when($dateStart, fn ($q) => $q->where('log_date', '>=', $dateStart))
            ->max('log_date');
        $hasImportedFcrHistory = Schema::hasColumn('feed_consumption_logs', 'source_key')
            && FeedConsumptionLog::where('source_key', 'like', 'production_data_clean:%:feed')->exists();
        $fcrData['fcrAsOf'] = $fcrData['fcrTimeline']->isNotEmpty() && $latestFeedDate
            ? 'Historical FCR through '.Carbon::parse($latestFeedDate)->format('m/d/Y')
            : null;

        return view('feed._fcr-content', $fcrData);
    }

    /**
     * Latest-data fallback shared by liveData() and fcrData(): today when
     * feed data is current, otherwise the latest feed log date.
     */
    private function feedReferenceDate(?string $endDate = null, ?int $cageId = null): \Carbon\Carbon
    {
        $now = ReportingDateService::now();
        $latest = FeedConsumptionLog::when($cageId, fn ($q) => $q->where('cage_id', $cageId))
            ->when($endDate, fn ($q) => $q->where('log_date', '<=', $endDate))
            ->when(Schema::hasColumn('feed_consumption_logs', 'source_key'), fn ($q) => $q->where(function ($sources) {
                $sources->whereNull('feed_consumption_logs.source_key')
                    ->orWhere('feed_consumption_logs.source_key', 'like', 'production_data_clean:%:feed');
            }))->max('log_date');
        if ($latest && $latest < $now->copy()->subDays(7)->toDateString()) {
            return \Carbon\Carbon::parse($latest);
        }

        return $now;
    }

    /**
     * Shared FCR computation for both server-render and AJAX.
     *
     * $refEnd anchors the "current period" window (defaults to the reporting
     * day end); callers pass the latest-data fallback so FCR reflects the
     * newest history instead of an empty trailing window.
     */
    private function computeFcrData(string|null $cageId, string $groupBy, ?\Carbon\Carbon $refEnd = null, ?string $from = null, ?string $to = null): array
    {
        $periodDays = match ($groupBy) {
            'month' => 30,
            'week'   => 7,
            default  => 1,
        };

        // Historical imports can end months before the current system date.
        // Anchor the range to the latest date shared by feed and production,
        // so the headline ratio uses a period where its numerator and
        // denominator are both observed rather than a synthetic empty tail.
        $cageForRange = ($cageId !== 'all' && $cageId !== null)
            ? Cage::find((int) $cageId)
            : null;
        $hasImportedHistory = Schema::hasColumn('production_logs', 'source_key')
            && DB::table('production_logs')->where('source_key', 'like', 'production_data_clean:%')->exists();
        $hasLiveProduction = Schema::hasColumn('production_logs', 'source_key')
            ? DB::table('production_logs')->whereNull('source_key')->exists()
            : DB::table('production_logs')->exists();
        $latestFeed = FeedConsumptionLog::query()
            ->when($hasImportedHistory && ! $from && ! $to, fn ($q) => $q->where('source_key', 'like', 'production_data_clean:%:feed'))
            ->when((! $hasImportedHistory || $from || $to) && Schema::hasColumn('feed_consumption_logs', 'source_key'), fn ($q) => $q->where(function ($sources) {
                $sources->whereNull('feed_consumption_logs.source_key')
                    ->orWhere('feed_consumption_logs.source_key', 'like', 'production_data_clean:%:feed');
            }))
            ->when($cageForRange, fn ($q) => $q->where('cage_id', $cageForRange->id))
            ->when(! $cageForRange, fn ($q) => $q->whereHas('cage', fn ($c) => $c->where('is_active', 1)))
            ->when($to, fn ($q) => $q->where('log_date', '<=', $to))
            ->when($from, fn ($q) => $q->where('log_date', '>=', $from))
            ->max('log_date');
        $latestProductionQuery = DB::table('production_logs as pl')
            ->leftJoin('cage_slots as cs', 'cs.id', '=', 'pl.cage_slot_id')
            ->leftJoin('cages as imported_cage', function ($join) {
                $join->on('imported_cage.cage_code', '=', DB::raw("SUBSTRING_INDEX(SUBSTRING_INDEX(pl.source_key, ':', 3), ':', -1)"));
            })
            ->where('pl.is_demo', false)
            ->when($cageForRange, fn ($q) => $q->where(function ($scope) use ($cageForRange) {
                $scope->where('cs.cage_id', $cageForRange->id)
                    ->orWhere(function ($imported) use ($cageForRange) {
                        $imported->where('pl.source_key', 'like', 'production_data_clean:%:production')
                            ->where('imported_cage.id', $cageForRange->id);
                    });
            }))
            ->when(! $cageForRange, fn ($q) => $q->where(function ($scope) {
                $scope->whereExists(function ($active) {
                    $active->selectRaw('1')->from('cages as slot_cage')
                        ->whereColumn('slot_cage.id', 'cs.cage_id')->where('slot_cage.is_active', 1);
                })->orWhere(function ($imported) {
                    $imported->where('pl.source_key', 'like', 'production_data_clean:%:production')
                        ->where('imported_cage.is_active', 1);
                });
            }))
            ->when($to, fn ($q) => $q->where('pl.log_date', '<=', $to))
            ->when($from, fn ($q) => $q->where('pl.log_date', '>=', $from));
        if ($from || $to) {
            $latestProductionQuery->when(Schema::hasColumn('production_logs', 'source_key'), fn ($q) => $q->where(function ($sources) {
                $sources->whereNull('pl.source_key')->orWhere('pl.source_key', 'like', 'production_data_clean:%:production');
            }));
        } elseif ($hasImportedHistory) {
            $latestProductionQuery->where('pl.source_key', 'like', 'production_data_clean:%:production');
        } else {
            $latestProductionQuery->when(Schema::hasColumn('production_logs', 'source_key'), fn ($q) => $q->whereNull('pl.source_key'));
        }
        $latestProduction = $latestProductionQuery->max('pl.log_date');
        $sharedLatest = collect([$latestFeed, $latestProduction])->filter()->min();
        if ($sharedLatest) {
            $sharedDate = \Carbon\Carbon::parse($sharedLatest);
            if ($from || $to || ($hasImportedHistory && ! $hasLiveProduction) || ! $refEnd || $sharedDate->lt($refEnd)) {
                $refEnd = $sharedDate;
            }
        }

        $dayEnd = $refEnd
            ? $refEnd->copy()->endOfDay()
            : ReportingDateService::reportingDayEnd()->subSecond();
        $dayStart = $dayEnd->copy()->subDays($periodDays - 1);
        if ($from) $dayStart = Carbon::parse($from)->startOfDay();
        if ($to) $dayEnd = Carbon::parse($to)->endOfDay();

        // Bound the timeline fetch to recent periods only (the tab renders the
        // latest 5 and totals "shown periods"). Previously unbounded: every
        // production + feed row ever recorded was hydrated on each view.
        $timelineSince = $from ? Carbon::parse($from)->startOfDay() : match ($groupBy) {
            'month' => $dayEnd->copy()->subMonths(11)->startOfDay(),
            'week'  => $dayEnd->copy()->subWeeks(11)->startOfDay(),
            default => $dayEnd->copy()->subDays(29)->startOfDay(),
        };

        $label = null;
        $selectedCageId = null;
        $cage = null;

        if ($cageId === 'all' || $cageId === null) {
            if ($cageId === 'all') {
                $fcrCurrent  = FcrCalculator::forAllCages(
                    $dayStart,
                    $dayEnd
                );
                $fcrTimeline = FcrCalculator::timelineAll($groupBy, $timelineSince, $to);
                $label       = 'All Cages';
                $selectedCageId = 'all';
            } else {
                $fcrCurrent  = null;
                $fcrTimeline = collect();
                $label       = null;
                $selectedCageId = null;
            }
        } else {
            $selectedCageId = (int) $cageId;
            $cage = Cage::find($selectedCageId);
            if ($cage) {
                $fcrTimeline = FcrCalculator::timeline($cage, $groupBy, $timelineSince, $to);
                $fcrCurrent  = FcrCalculator::forCage(
                    $cage,
                    $dayStart,
                    $dayEnd
                );
                $label = $cage->cage_code;
            } else {
                $fcrTimeline = collect();
                $fcrCurrent  = null;
                $label       = null;
            }
        }

        if ($from || $to) {
            $fcrTimeline = $fcrTimeline->filter(function ($row) use ($from, $to, $groupBy) {
                $date = Carbon::parse($row['period_start'] ?? $row['period']);
                $periodEnd = match ($groupBy) {
                    'week' => $date->copy()->endOfWeek(),
                    'month' => $date->copy()->endOfMonth(),
                    default => $date->copy()->endOfDay(),
                };
                return (! $from || $periodEnd->toDateString() >= $from)
                    && (! $to || $date->toDateString() <= $to);
            })->values();
            $fcrCurrent = $cageId === 'all'
                ? FcrCalculator::forAllCages($dayStart, $dayEnd)
                : (($cage ?? null) ? FcrCalculator::forCage($cage, $dayStart, $dayEnd) : null);
        }

        // All-time header totals via SQL aggregates (no date bound, no model
        // hydration): identical row sets and weight math to the old unbounded
        // timeline sums, so the header cards read exactly as before.
        $fcrTotals = ['feed_kg' => 0.0, 'egg_mass_kg' => 0.0];
        if ($cageId === 'all' || ($cage ?? null)) {
            $fcrTotals = FcrCalculator::allTimeTotals($cageId === 'all' ? null : $cage);
            if ($from || $to) {
                $feedQuery = FeedConsumptionLog::query()
            ->when($cageId !== 'all', fn ($q) => $q->where('cage_id', $cage?->id))
                    ->when($cageId === 'all', fn ($q) => $q->whereHas('cage', fn ($c) => $c->where('is_active', 1)))
                    ->when(Schema::hasColumn('feed_consumption_logs', 'source_key'), fn ($q) => $q->where(function ($s) {
                        $s->whereNull('source_key')->orWhere('source_key', 'like', 'production_data_clean:%:feed');
                    }))
                    ->when($from, fn ($q) => $q->where('log_date', '>=', $from))
                    ->when($to, fn ($q) => $q->where('log_date', '<=', $to));
                $eggMass = FcrCalculator::eggMassForRange($cageId === 'all' ? null : $cage, $from, $to);
                $fcrTotals = ['feed_kg' => (float) $feedQuery->sum('feed_consumed_kg'), 'egg_mass_kg' => $eggMass];
            }
        }

        return [
            'fcrCurrent'    => $fcrCurrent,
            'fcrTimeline'   => $fcrTimeline,
            'fcrGroupBy'    => $groupBy,
            'fcrCageLabel'  => $label,
            'fcrSelectedId' => $selectedCageId,
            'fcrTotalFeedKg'    => $fcrTotals['feed_kg'],
            'fcrTotalEggMassKg' => $fcrTotals['egg_mass_kg'],
        ];
    }

    private function batchRules(): array
    {
        return [
            'brand' => 'nullable|string|max:100',
            'crude_protein' => 'required|numeric|min:0|max:100',
            'total_quantity_kg' => 'nullable|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'date_received' => 'sometimes|required|date',
            'low_stock_threshold' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }

    public function storeBatch(Request $request)
    {
        $validator = Validator::make($request->all(), $this->batchRules());

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return redirect()->back()
                ->with('reopen_add_batch', true)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        try {
            $batch = DB::transaction(function () use ($data, $request) {
                $batch = FeedBatch::create($data);
                FinancePostingService::syncBatchExpense($batch->refresh(), null, auth()->id());

                return $batch;
            });
        } catch (\DomainException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => ['batch' => $e->getMessage()]], 422);
            }

            return redirect()->back()
                ->with('reopen_add_batch', true)
                ->withErrors(['batch' => $e->getMessage()])
                ->withInput();
        }

        Note::saveFromSection($data['notes'] ?? null, 'Feed');

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'batch_code' => $batch->batch_code, 'batch_id' => $batch->id]);
        }

        return redirect()->back()
            ->with('success', "Feed batch {$batch->batch_code} added.");
    }

    public function updateBatch(Request $request, FeedBatch $feedBatch)
    {
        $validator = Validator::make($request->all(), $this->batchRules());

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return redirect()->back()
                ->with('reopen_edit_batch', $feedBatch->id)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();
        $oldNotes = trim((string) $feedBatch->notes);
        $oldTriple = [
            'unit_cost' => $feedBatch->unit_cost,
            'total_quantity_kg' => $feedBatch->total_quantity_kg,
            'date_received' => $feedBatch->date_received instanceof \DateTimeInterface
                ? $feedBatch->date_received->format('Y-m-d')
                : $feedBatch->date_received,
        ];

        try {
            DB::transaction(function () use ($feedBatch, $data, $oldTriple) {
                $feedBatch->update($data);
                FinancePostingService::syncBatchExpense($feedBatch->refresh(), $oldTriple, auth()->id());
            });
        } catch (\DomainException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => ['batch' => $e->getMessage()]], 422);
            }

            return redirect()->back()
                ->with('reopen_edit_batch', $feedBatch->id)
                ->withErrors(['batch' => $e->getMessage()])
                ->withInput();
        }

        // Only a changed note is copied (the edit box comes pre-filled).
        if (array_key_exists('notes', $data) && trim((string) $data['notes']) !== $oldNotes) {
            Note::saveFromSection($data['notes'], 'Feed');
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Feed batch updated.');
    }

    private function consumptionRules(): array
    {
        return [
            'cage_id' => 'required|exists:cages,id',
            'feed_batch_id' => 'required|exists:feed_batches,id',
            'log_date' => 'required|date|before_or_equal:'.\App\Services\ReportingDateService::reportingDateString(),
            'log_time' => 'required|date_format:H:i',
            'feed_consumed_kg' => 'required|numeric|min:0',
        ];
    }

    public function storeConsumption(Request $request)
    {
        $validator = Validator::make($request->all(), $this->consumptionRules());

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return redirect()->back()
                ->with('reopen_add_consumption', true)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        $log = FeedConsumptionLog::create(array_merge($data, [
            'source' => 'direct',
            'recorded_by' => auth()->id(),
        ]));

        $this->checkLowStock($data['feed_batch_id']);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'log_id' => $log->id]);
        }

        return redirect()->back()
            ->with('success', "Feed consumption logged for Cage " . Cage::find($data['cage_id'])->cage_code . ".");
    }

    public function updateConsumption(Request $request, FeedConsumptionLog $feedConsumptionLog)
    {
        if ($feedConsumptionLog->source !== 'direct') {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => ['source' => 'Distributed entries can only be edited via the whole-farm entry.']], 422);
            }
            return redirect()->back()->with('error', 'Distributed entries can only be edited via the whole-farm entry.');
        }

        $validator = Validator::make($request->all(), $this->consumptionRules());

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return redirect()->back()
                ->with('reopen_edit_consumption', $feedConsumptionLog->id)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        $feedConsumptionLog->update(array_merge($data, [
            'source' => 'direct',
        ]));

        $this->checkLowStock($data['feed_batch_id']);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()
            ->with('success', "Feed consumption updated for Cage " . Cage::find($data['cage_id'])->cage_code . ".");
    }

    public function destroyConsumption(FeedConsumptionLog $feedConsumptionLog)
    {
        if ($feedConsumptionLog->source !== 'direct') {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'errors' => ['source' => 'Distributed entries can only be removed by deleting the whole-farm entry.']], 422);
            }
            return redirect()->back()->with('error', 'Distributed entries can only be removed by deleting the whole-farm entry.');
        }

        $feedConsumptionLog->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Consumption log deleted.');
    }

    private function farmEntryRules(): array
    {
        return [
            'feed_batch_id' => 'required|exists:feed_batches,id',
            'log_date' => 'required|date|before_or_equal:'.\App\Services\ReportingDateService::reportingDateString(),
            'log_time' => 'required|date_format:H:i',
            'total_kg' => 'required|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
        ];
    }

    public function storeFarmFeedEntry(Request $request)
    {
        $validator = Validator::make($request->all(), $this->farmEntryRules());

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return redirect()->back()
                ->with('reopen_add_farm_entry', true)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        $batch = FeedBatch::find($data['feed_batch_id']);

        $entry = FarmFeedEntry::create([
            'feed_batch_id' => $data['feed_batch_id'],
            'log_date' => $data['log_date'],
            'log_time' => $data['log_time'] ?? null,
            'total_kg' => $data['total_kg'],
            'unit_cost' => $data['unit_cost'] ?? $batch->unit_cost,
        ]);

        $this->distributeFarmFeedEntry($entry);
        $this->checkLowStock($entry->feed_batch_id);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'farm_entry_id' => $entry->id]);
        }

        return redirect()->back()
            ->with('success', "Whole-farm feeding logged ({$entry->total_kg} kg distributed across active cages).");
    }

    public function updateFarmFeedEntry(Request $request, FarmFeedEntry $farmFeedEntry)
    {
        $validator = Validator::make($request->all(), $this->farmEntryRules());

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return redirect()->back()
                ->with('reopen_edit_farm_entry', $farmFeedEntry->id)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        $batch = FeedBatch::find($data['feed_batch_id']);

        $farmFeedEntry->update([
            'feed_batch_id' => $data['feed_batch_id'],
            'log_date' => $data['log_date'],
            'log_time' => $data['log_time'] ?? null,
            'total_kg' => $data['total_kg'],
            'unit_cost' => $data['unit_cost'] ?? $batch->unit_cost,
        ]);

        // Recreate distributed rows to reflect new totals/proportions.
        $farmFeedEntry->consumptionLogs()->delete();
        $this->distributeFarmFeedEntry($farmFeedEntry);
        $this->checkLowStock($farmFeedEntry->feed_batch_id);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()
            ->with('success', 'Whole-farm feeding updated and redistributed.');
    }

    public function destroyFarmFeedEntry(FarmFeedEntry $farmFeedEntry)
    {
        $farmFeedEntry->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Whole-farm feeding entry deleted.');
    }

    /**
     * Distribute a whole-farm feed entry across active cages proportionally by hen count.
     * Uses largest-remainder method so the sum of distributed rows exactly equals total_kg.
     */
    protected function distributeFarmFeedEntry(FarmFeedEntry $entry): void
    {
        $cages = Cage::where('is_active', 1)
            ->withCount(['hens as active_hens_count' => fn ($q) => $q->where('is_active', 1)])
            ->get()
            ->filter(fn ($c) => $c->active_hens_count > 0);

        $totalHens = $cages->sum('active_hens_count');

        if ($totalHens === 0 || $cages->isEmpty()) {
            return;
        }

        $totalCents = (int) round($entry->total_kg * 100);
        $shares = $cages->map(function (Cage $cage) use ($totalHens, $entry) {
            $exactKg = ($cage->active_hens_count / $totalHens) * $entry->total_kg;
            $baseCents = (int) floor($exactKg * 100);
            $remainder = ($exactKg * 100) - $baseCents;

            return [
                'cage' => $cage,
                'base_cents' => $baseCents,
                'remainder' => $remainder,
            ];
        });

        $distributedCents = $shares->sum('base_cents');
        $remainingCents = max(0, $totalCents - $distributedCents);

        $sorted = $shares->sortByDesc('remainder')->values();

        $rows = [];
        foreach ($sorted as $idx => $share) {
            $extra = $idx < $remainingCents ? 1 : 0;
            $kg = ($share['base_cents'] + $extra) / 100;

            $rows[] = [
                'cage_id' => $share['cage']->id,
                'feed_batch_id' => $entry->feed_batch_id,
                'log_date' => $entry->log_date,
                'log_time' => $entry->log_time?->format('H:i:s'),
                'feed_consumed_kg' => $kg,
                'source' => 'distributed',
                'farm_feed_entry_id' => $entry->id,
                'recorded_by' => auth()->id(),
            ];
        }

        FeedConsumptionLog::insert($rows);
    }

    public function checkDeleteBatch(FeedBatch $feedBatch)
    {
        $count = $feedBatch->consumptionLogs()->count();

        return response()->json([
            'can_delete' => $count === 0,
            'count' => $count,
        ]);
    }

    public function destroyBatch(FeedBatch $feedBatch)
    {
        if ($feedBatch->consumptionLogs()->exists()) {
            $count = $feedBatch->consumptionLogs()->count();

            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'errors' => ['batch' => "Cannot delete this batch — {$count} consumption log(s) reference it."]], 422);
            }

            return redirect()->back()->with('error', "Cannot delete this batch — {$count} consumption log(s) reference it. Remove those records first.");
        }

        DB::transaction(function () use ($feedBatch) {
            $feedBatch->delete();
            FinancePostingService::reverseBatchExpense($feedBatch, auth()->id());
        });

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Feed batch deleted.');
    }

    protected function checkLowStock(int $feedBatchId): void
    {
        $batch = FeedBatch::find($feedBatchId);

        if ($batch->total_quantity_kg === null || $batch->low_stock_threshold === null) {
            return;
        }

        if (! $batch->is_low_stock) {
            return;
        }

        $exists = Alert::where('alert_type', 'low_stock_feed')
            ->where('cage_id', null)
            ->where('is_read', 0)
            ->whereBetween('triggered_at', ReportingDateService::reportingDayWindow(ReportingDateService::reportingDateString()))
            ->exists();

        if ($exists) {
            return;
        }

        Alert::createDeduped([
            'cage_id' => null,
            'alert_type' => 'low_stock_feed',
            'message' => "Low stock: {$batch->batch_code} — {$batch->remaining_kg} kg remaining (threshold: {$batch->low_stock_threshold} kg)",
            'is_read' => 0,
            'triggered_at' => now(),
            'dedup_key' => Alert::dedupKey(null, 'low_stock_feed'),
            'alert_day' => ReportingDateService::reportingDateString(),
        ]);
    }
}
