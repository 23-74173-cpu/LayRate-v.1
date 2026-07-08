<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Services\FcrCalculator;
use Illuminate\Http\Request;

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

        $consumptionLogs = FeedConsumptionLog::with(['cage', 'feedBatch'])
            ->when($preselectedCageId, fn ($q) => $q->where('cage_id', $preselectedCageId))
            ->orderByDesc('log_date')
            ->paginate(20)
            ->withQueryString();

        $avgCp = $batches->avg('crude_protein');

        $totalFeedWeek = FeedConsumptionLog::where('log_date', '>=', now()->subDays(7))
            ->sum('feed_consumed_kg');

        $activeCagesCount = Cage::where('is_active', 1)->count();
        $avgFeedPerCage = $activeCagesCount
            ? round($totalFeedWeek / max($activeCagesCount, 1) / 7, 1)
            : 0;

        $totalFeedCostMonth = FeedConsumptionLog::where('log_date', '>=', now()->startOfMonth())
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
            'feed_consumed_kg' => 'required|numeric|min:0',
        ]);

        $log = FeedConsumptionLog::updateOrCreate(
            ['cage_id' => $data['cage_id'], 'log_date' => $data['log_date']],
            array_merge($data, ['recorded_by' => auth()->id()])
        );

        $this->checkLowStock($data['feed_batch_id']);

        $verb = $log->wasRecentlyCreated ? 'logged' : 'updated';

        return redirect()->route('feed')
            ->with('success', "Feed consumption {$verb} for Cage " . Cage::find($data['cage_id'])->cage_code . ".");
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

    public function destroyConsumption(FeedConsumptionLog $feedConsumptionLog)
    {
        $batchId = $feedConsumptionLog->feed_batch_id;
        $feedConsumptionLog->delete();

        return redirect()->route('feed')->with('success', 'Consumption log deleted.');
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
