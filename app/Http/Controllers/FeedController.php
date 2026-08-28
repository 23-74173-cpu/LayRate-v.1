<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\FarmFeedEntry;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Services\FcrCalculator;
use App\Services\ReportingDateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FeedController extends Controller
{
    public function index()
    {
        return view('feed', ['preselectedCageId' => (int) request('cage_id') ?: null]);
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
        // Full, unpaginated list — needed for the avg CP% stat and to populate
        // the consumption/farm-entry modal <select> dropdowns with every batch,
        // not just whichever page the table happens to be on.
        $allBatches = FeedBatch::orderByDesc('date_received')->get();
        $avgCp = $allBatches->avg('crude_protein');

        $batches = FeedBatch::orderByDesc('date_received')->paginate(15)->withQueryString();

        $preselectedCageId = (int) request('cage_id') ?: null;

        $consumptionLogs = FeedConsumptionLog::with(['cage', 'feedBatch', 'farmFeedEntry'])
            ->when($preselectedCageId, fn ($q) => $q->where('cage_id', $preselectedCageId))
            ->orderByDesc('log_date')
            ->orderByDesc('log_time')
            ->paginate(20)
            ->withQueryString();

        $reportingNow = ReportingDateService::now();
        $totalFeedWeek = FeedConsumptionLog::where('log_date', '>=', $reportingNow->copy()->subDays(7)->toDateString())
            ->sum('feed_consumed_kg');

        $activeCagesCount = Cage::where('is_active', 1)->count();
        $avgFeedPerCage = $activeCagesCount
            ? round($totalFeedWeek / max($activeCagesCount, 1) / 7, 1)
            : 0;

        $totalFeedCostMonth = FeedConsumptionLog::where('feed_consumption_logs.log_date', '>=', $reportingNow->copy()->startOfMonth()->toDateString())
            ->join('feed_batches', 'feed_consumption_logs.feed_batch_id', '=', 'feed_batches.id')
            ->selectRaw('SUM(feed_consumption_logs.feed_consumed_kg * feed_batches.unit_cost) as total')
            ->whereNotNull('feed_batches.unit_cost')
            ->value('total');

        $cages = Cage::where('is_active', 1)->orderBy('cage_code')->get();

        $fcrCageId = request('fcr_cage_id', $preselectedCageId ? (string) $preselectedCageId : null);
        $fcrGroupBy = request('fcr_group_by', 'week');
        if (! in_array($fcrGroupBy, ['day', 'week', 'month'])) {
            $fcrGroupBy = 'week';
        }

        // Default to first cage when no FCR selection is provided
        if ($fcrCageId === null) {
            $firstCage = $cages->first();
            $fcrCageId = $firstCage ? (string) $firstCage->id : null;
        }

        $fcrData = $this->computeFcrData($fcrCageId, $fcrGroupBy);

        return view('feed._live-data', array_merge(
            compact('batches', 'allBatches', 'consumptionLogs', 'avgCp', 'totalFeedWeek', 'avgFeedPerCage',
                     'totalFeedCostMonth', 'cages', 'preselectedCageId'),
            $fcrData
        ));
    }

    /**
     * AJAX endpoint: returns rendered FCR content HTML.
     */
    public function fcrData()
    {
        $cageId = request('cage_id', 'all');
        $groupBy = request('group_by', 'week');
        if (! in_array($groupBy, ['day', 'week', 'month'])) {
            $groupBy = 'week';
        }

        $fcrData = $this->computeFcrData($cageId, $groupBy);

        return view('feed._fcr-content', $fcrData);
    }

    /**
     * Shared FCR computation for both server-render and AJAX.
     */
    private function computeFcrData(string|null $cageId, string $groupBy): array
    {
        $periodDays = match ($groupBy) {
            'month' => 30,
            'week'   => 7,
            default  => 1,
        };

        $label = null;
        $selectedCageId = null;

        if ($cageId === 'all' || $cageId === null) {
            if ($cageId === 'all') {
                $fcrCurrent  = FcrCalculator::forAllCages(
                    ReportingDateService::reportingDayStart()->subDays($periodDays - 1),
                    ReportingDateService::reportingDayEnd()->subSecond()
                );
                $fcrTimeline = FcrCalculator::timelineAll($groupBy);
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
                $fcrTimeline = FcrCalculator::timeline($cage, $groupBy);
                $fcrCurrent  = FcrCalculator::forCage(
                    $cage,
                    ReportingDateService::reportingDayStart()->subDays($periodDays - 1),
                    ReportingDateService::reportingDayEnd()->subSecond()
                );
                $label = $cage->cage_code;
            } else {
                $fcrTimeline = collect();
                $fcrCurrent  = null;
                $label       = null;
            }
        }

        return [
            'fcrCurrent'    => $fcrCurrent,
            'fcrTimeline'   => $fcrTimeline,
            'fcrGroupBy'    => $groupBy,
            'fcrCageLabel'  => $label,
            'fcrSelectedId' => $selectedCageId,
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
        $batch = FeedBatch::create($data);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'batch_code' => $batch->batch_code]);
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
        $feedBatch->update($data);

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
            'log_date' => 'required|date',
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
            return response()->json(['success' => true]);
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
            'log_date' => 'required|date',
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
            return response()->json(['success' => true]);
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

        $feedBatch->delete();

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
