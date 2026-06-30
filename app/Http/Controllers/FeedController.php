<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedController extends Controller
{
    public function index()
    {
        $batches = FeedBatch::orderByDesc('date_received')->get();

        $consumptionLogs = FeedConsumptionLog::with(['cage', 'feedBatch'])
            ->orderByDesc('log_date')
            ->limit(50)
            ->get();

        // Weekly summary stats
        $avgCp = $batches->avg('crude_protein');

        $totalFeedWeek = FeedConsumptionLog::where('log_date', '>=', now()->subDays(7))
            ->sum('feed_consumed_kg');

        $activeCagesCount = Cage::where('is_active', 1)->count();
        $avgFeedPerCage = $activeCagesCount
            ? round($totalFeedWeek / max($activeCagesCount, 1) / 7, 1)
            : 0;

        return view('feed', compact(
            'batches', 'consumptionLogs', 'avgCp', 'totalFeedWeek', 'avgFeedPerCage'
        ));
    }

    public function storeBatch(Request $request)
    {
        $data = $request->validate([
            'batch_code'    => 'required|string|max:50|unique:feed_batches',
            'crude_protein' => 'required|numeric|min:0|max:100',
            'date_received' => 'required|date',
            'notes'         => 'nullable|string',
        ]);

        FeedBatch::create($data);

        return redirect()->route('feed')->with('success', "Feed batch {$data['batch_code']} added.");
    }

    public function updateBatch(Request $request, FeedBatch $feedBatch)
    {
        $data = $request->validate([
            'crude_protein' => 'required|numeric|min:0|max:100',
            'notes'         => 'nullable|string',
        ]);

        $feedBatch->update($data);

        return redirect()->route('feed')->with('success', 'Feed batch updated.');
    }

    public function storeConsumption(Request $request)
    {
        $data = $request->validate([
            'cage_id'          => 'required|exists:cages,id',
            'feed_batch_id'    => 'required|exists:feed_batches,id',
            'log_date'         => 'required|date',
            'feed_consumed_kg' => 'required|numeric|min:0',
        ]);

        FeedConsumptionLog::updateOrCreate(
            ['cage_id' => $data['cage_id'], 'log_date' => $data['log_date']],
            array_merge($data, ['recorded_by' => auth()->id()])
        );

        return redirect()->route('feed')->with('success', 'Feed consumption logged.');
    }
}
