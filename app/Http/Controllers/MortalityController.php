<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\MortalityLogHen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MortalityController extends Controller
{
    public function index()
    {
        $cages = Cage::orderBy('cage_code')->get();
        $logs  = MortalityLog::with(['cage', 'hens'])
            ->orderByDesc('log_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $todayTotal = MortalityLog::whereDate('log_date', today())->sum('count');

        $todayByCage = MortalityLog::with('cage')
            ->whereDate('log_date', today())
            ->get()
            ->groupBy(fn($l) => $l->cage->cage_code)
            ->map(fn($g) => $g->sum('count'));

        $preselectedCageId = (int) request('cage_id');

        return view('mortality', compact('cages', 'logs', 'todayTotal', 'todayByCage', 'preselectedCageId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cage_id'  => 'required|exists:cages,id',
            'log_date' => 'required|date',
            'count'    => 'required|integer|min:1',
            'reason'   => 'required|in:' . implode(',', MortalityLog::REASONS),
            'notes'    => 'nullable|string|max:1000',
        ]);

        $cage = Cage::with(['cageSlots.hens' => fn($q) => $q->where('is_active', 1)])->findOrFail($data['cage_id']);

        $activeHens = $cage->cageSlots->flatMap(fn($slot) => $slot->hens)
            ->sortBy(fn($h) => [$h->cage_slot_id, $h->placement_date ?? $h->date_acquired])
            ->values();

        if ($activeHens->count() < $data['count']) {
            return back()->withErrors([
                'count' => "Only {$activeHens->count()} active hen(s) available in {$cage->cage_code}, but {$data['count']} deaths recorded.",
            ])->withInput();
        }

        $hensToDeactivate = $activeHens->take($data['count']);

        DB::transaction(function () use ($data, $hensToDeactivate) {
            $log = MortalityLog::create([
                'cage_id'     => $data['cage_id'],
                'log_date'    => $data['log_date'],
                'count'       => $data['count'],
                'reason'      => $data['reason'],
                'notes'       => $data['notes'] ?? null,
                'recorded_by' => auth()->id(),
            ]);

            foreach ($hensToDeactivate as $hen) {
                $hen->update(['is_active' => false]);

                MortalityLogHen::create([
                    'mortality_log_id' => $log->id,
                    'hen_id'           => $hen->id,
                    'cage_slot_id'     => $hen->cage_slot_id,
                ]);
            }

            $this->decrementSlotOccupancy($hensToDeactivate);
        });

        $this->checkMortalitySpike($data['cage_id'], $data['log_date']);

        return redirect()->route('mortality.index')
            ->with('success', 'Mortality record saved.');
    }

    public function destroy(MortalityLog $mortalityLog)
    {
        $pivotRows = MortalityLogHen::where('mortality_log_id', $mortalityLog->id)->get();

        foreach ($pivotRows as $pivot) {
            $hen = Hen::find($pivot->hen_id);

            if ($hen === null) {
                Log::warning("MortalityLogHen#{$pivot->id} references hen_id {$pivot->hen_id} which no longer exists. Skipping reactivation.");
                continue;
            }

            $hen->update(['is_active' => true]);

            CageSlot::where('id', $pivot->cage_slot_id)->increment('current_occupancy');
        }

        MortalityLogHen::where('mortality_log_id', $mortalityLog->id)->delete();
        $mortalityLog->delete();

        return redirect()->route('mortality.index')
            ->with('success', 'Record deleted.');
    }

    /**
     * Decrement current_occupancy on each affected cage_slot.
     * Separated into its own method for testability.
     */
    public function decrementSlotOccupancy(iterable $hens): void
    {
        $slotCounts = [];

        foreach ($hens as $hen) {
            $slotId = $hen->cage_slot_id;
            $slotCounts[$slotId] = ($slotCounts[$slotId] ?? 0) + 1;
        }

        foreach ($slotCounts as $slotId => $decrement) {
            CageSlot::where('id', $slotId)->decrement('current_occupancy', $decrement);
        }
    }

    /**
     * Check if same-day same-cage mortality total exceeds threshold and create alert.
     * TODO: Move threshold (3) to settings table for operator configurability.
     */
    private function checkMortalitySpike(int $cageId, string $logDate): void
    {
        $cageDailyTotal = MortalityLog::where('cage_id', $cageId)
            ->whereDate('log_date', $logDate)
            ->sum('count');

        if ($cageDailyTotal < 3) {
            return;
        }

        $exists = Alert::where('cage_id', $cageId)
            ->where('alert_type', 'mortality_spike')
            ->where('is_read', 0)
            ->whereDate('triggered_at', $logDate)
            ->exists();

        if ($exists) {
            return;
        }

        Alert::create([
            'cage_id'      => $cageId,
            'alert_type'   => 'mortality_spike',
            'message'      => "{$cageDailyTotal} hen(s) died on {$logDate} — mortality spike detected",
            'is_read'      => 0,
            'triggered_at' => now(),
        ]);
    }
}
