<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Services\FcrCalculator;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function index()
    {
        // Pre-selected cage passed from dashboard card navigation (read-only)
        return view('feed', ['preselectedCageId' => (int) request('cage_id') ?: null]);
    }

    public function liveData()
    {
        $batches = FeedBatch::orderByDesc('date_received')->get();

        // Pre-selected cage passed from dashboard card navigation (read-only filter)
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

        // FCR section state.
        $fcrCageId = (int) request('fcr_cage_id') ?: $preselectedCageId ?: Cage::where('is_active', 1)->orderBy('cage_code')->value('id');
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

        $fcrCages = Cage::where('is_active', 1)->orderBy('cage_code')->get();

        return view('feed._live-data', compact(
            'batches', 'consumptionLogs', 'avgCp', 'totalFeedWeek', 'avgFeedPerCage', 'preselectedCageId',
            'fcrCage', 'fcrCageId', 'fcrGroupBy', 'fcrTimeline', 'fcrCurrent', 'fcrCages'
        ));
    }

    public function storeBatch(Request $request)
    {
        $data = $request->validate([
            'batch_code' => 'required|string|max:50|unique:feed_batches',
            'crude_protein' => 'required|numeric|min:0|max:100',
            'date_received' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        FeedBatch::create($data);

        return redirect()->route('feed')->with('success', "Feed batch {$data['batch_code']} added.");
    }

    public function updateBatch(Request $request, FeedBatch $feedBatch)
    {
        $data = $request->validate([
            'crude_protein' => 'required|numeric|min:0|max:100',
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

        FeedConsumptionLog::updateOrCreate(
            ['cage_id' => $data['cage_id'], 'log_date' => $data['log_date']],
            array_merge($data, ['recorded_by' => auth()->id()])
        );

        return redirect()->route('feed')->with('success', 'Feed consumption logged.');
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
        $feedConsumptionLog->delete();

        return redirect()->route('feed')->with('success', 'Consumption log deleted.');
    }
}
