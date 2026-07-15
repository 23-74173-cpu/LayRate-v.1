<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\FarmFeedEntry;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Services\FcrCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedController extends Controller
{
    public function index()
    {
        return view('feed', ['preselectedCageId' => (int) request('cage_id') ?: null]);
    }

    public function liveData()
    {
        $batches = FeedBatch::orderByDesc('date_received')->get();

        $preselectedCageId = (int) request('cage_id') ?: null;

        $consumptionLogs = FeedConsumptionLog::with(['cage', 'feedBatch', 'farmFeedEntry'])
            ->when($preselectedCageId, fn ($q) => $q->where('cage_id', $preselectedCageId))
            ->orderByDesc('log_date')
            ->orderBy('log_time')
            ->paginate(20)
            ->withQueryString();

        $avgCp = $batches->avg('crude_protein');

        $totalFeedWeek = FeedConsumptionLog::where('log_date', '>=', now()->subDays(7))
            ->sum('feed_consumed_kg');

        $activeCagesCount = Cage::where('is_active', 1)->count();
        $avgFeedPerCage = $activeCagesCount
            ? round($totalFeedWeek / max($activeCagesCount, 1) / 7, 1)
            : 0;

        $totalFeedCostMonth = FeedConsumptionLog::where('feed_consumption_logs.log_date', '>=', now()->startOfMonth())
            ->join('feed_batches', 'feed_consumption_logs.feed_batch_id', '=', 'feed_batches.id')
            ->selectRaw('SUM(feed_consumption_logs.feed_consumed_kg * feed_batches.unit_cost) as total')
            ->whereNotNull('feed_batches.unit_cost')
            ->value('total');

        $cages = Cage::where('is_active', 1)->orderBy('cage_code')->get();

        $fcrCageId = (int) request('fcr_cage_id') ?: $preselectedCageId ?: $cages->value('id');
        $fcrGroupBy = request('fcr_group_by', 'week');
        if (! in_array($fcrGroupBy, ['day', 'week', 'month'])) {
            $fcrGroupBy = 'week';
        }

        $fcrCage = $fcrCageId ? Cage::find($fcrCageId) : null;
        $fcrTimeline = $fcrCage ? FcrCalculator::timeline($fcrCage, $fcrGroupBy) : collect();

        $fcrPeriodDays = match ($fcrGroupBy) {
            'month' => 30,
            'week' => 7,
            default => 1,
        };
        $fcrCurrent = $fcrCage
            ? FcrCalculator::forCage($fcrCage, now()->subDays($fcrPeriodDays - 1)->startOfDay(), now()->endOfDay())
            : null;

        return view('feed._live-data', compact(
            'batches', 'consumptionLogs', 'avgCp', 'totalFeedWeek', 'avgFeedPerCage',
            'totalFeedCostMonth', 'cages',
            'preselectedCageId',
            'fcrCage', 'fcrCageId', 'fcrGroupBy', 'fcrTimeline', 'fcrCurrent',
        ));
    }

    public function storeBatch(Request $request)
    {
        $data = $request->validate([
            'brand' => 'nullable|string|max:100',
            'crude_protein' => 'required|numeric|min:0|max:100',
            'total_quantity_kg' => 'nullable|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'date_received' => 'required|date',
            'low_stock_threshold' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $batch = FeedBatch::create($data);

        return redirect()->route('feed')
            ->with('success', "Feed batch {$batch->batch_code} added.");
    }

    public function updateBatch(Request $request, FeedBatch $feedBatch)
    {
        $data = $request->validate([
            'brand' => 'nullable|string|max:100',
            'crude_protein' => 'required|numeric|min:0|max:100',
            'total_quantity_kg' => 'nullable|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'low_stock_threshold' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $feedBatch->update($data);

        return redirect()->route('feed')->with('success', 'Feed batch updated.');
    }

    public function storeConsumption(Request $request)
    {
        $data = $request->validate([
            'cage_id' => 'required|exists:cages,id',
            'feed_batch_id' => 'required|exists:feed_batches,id',
            'log_date' => 'required|date',
            'log_time' => 'nullable|date_format:H:i',
            'feed_consumed_kg' => 'required|numeric|min:0',
        ]);

        $log = FeedConsumptionLog::create(array_merge($data, [
            'source' => 'direct',
            'recorded_by' => auth()->id(),
        ]));

        $this->checkLowStock($data['feed_batch_id']);

        return redirect()->route('feed')
            ->with('success', "Feed consumption logged for Cage " . Cage::find($data['cage_id'])->cage_code . ".");
    }

    public function updateConsumption(Request $request, FeedConsumptionLog $feedConsumptionLog)
    {
        if ($feedConsumptionLog->source !== 'direct') {
            return redirect()->back()->with('error', 'Distributed entries can only be edited via the whole-farm entry.');
        }

        $data = $request->validate([
            'cage_id' => 'required|exists:cages,id',
            'feed_batch_id' => 'required|exists:feed_batches,id',
            'log_date' => 'required|date',
            'log_time' => 'nullable|date_format:H:i',
            'feed_consumed_kg' => 'required|numeric|min:0',
        ]);

        $feedConsumptionLog->update(array_merge($data, [
            'source' => 'direct',
        ]));

        $this->checkLowStock($data['feed_batch_id']);

        return redirect()->route('feed')
            ->with('success', "Feed consumption updated for Cage " . Cage::find($data['cage_id'])->cage_code . ".");
    }

    public function destroyConsumption(FeedConsumptionLog $feedConsumptionLog)
    {
        if ($feedConsumptionLog->source !== 'direct') {
            return redirect()->back()->with('error', 'Distributed entries can only be removed by deleting the whole-farm entry.');
        }

        $feedConsumptionLog->delete();

        return redirect()->route('feed')->with('success', 'Consumption log deleted.');
    }

    public function storeFarmFeedEntry(Request $request)
    {
        $data = $request->validate([
            'feed_batch_id' => 'required|exists:feed_batches,id',
            'log_date' => 'required|date',
            'log_time' => 'nullable|date_format:H:i',
            'total_kg' => 'required|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

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

        return redirect()->route('feed')
            ->with('success', "Whole-farm feeding logged ({$entry->total_kg} kg distributed across active cages).");
    }

    public function updateFarmFeedEntry(Request $request, FarmFeedEntry $farmFeedEntry)
    {
        $data = $request->validate([
            'feed_batch_id' => 'required|exists:feed_batches,id',
            'log_date' => 'required|date',
            'log_time' => 'nullable|date_format:H:i',
            'total_kg' => 'required|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

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

        return redirect()->route('feed')
            ->with('success', 'Whole-farm feeding updated and redistributed.');
    }

    public function destroyFarmFeedEntry(FarmFeedEntry $farmFeedEntry)
    {
        $farmFeedEntry->delete();

        return redirect()->route('feed')->with('success', 'Whole-farm feeding entry deleted.');
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
                'log_time' => $entry->log_time,
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

            return redirect()->back()->with('error', "Cannot delete this batch — {$count} consumption log(s) reference it. Remove those records first.");
        }

        $feedBatch->delete();

        return redirect()->route('feed')->with('success', 'Feed batch deleted.');
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

        $exists = Alert::where('alert_type', 'low_stock')
            ->where('cage_id', null)
            ->where('is_read', 0)
            ->whereDate('triggered_at', today())
            ->exists();

        if ($exists) {
            return;
        }

        Alert::create([
            'cage_id' => null,
            'alert_type' => 'low_stock',
            'message' => "Low stock: {$batch->batch_code} — {$batch->remaining_kg} kg remaining (threshold: {$batch->low_stock_threshold} kg)",
            'is_read' => 0,
            'triggered_at' => now(),
        ]);
    }
}
