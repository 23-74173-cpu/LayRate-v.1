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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Services\ReportingDateService;

class MortalityController extends Controller
{
    public function index()
    {
        // active_hens_count feeds the client-side "count exceeds active hens
        // in this cage" check on the mortality form (red text + disabled
        // submit) — mirrors the server-side guard in store()/update() below.
        $cages = Cage::withCount(['hens as active_hens_count' => fn($q) => $q->where('is_active', 1)])
            ->orderBy('cage_code')->get();
        $logs  = MortalityLog::with(['cage', 'hens'])
            ->orderByDesc('log_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $today = ReportingDateService::reportingDateString();
        $todayTotal = MortalityLog::whereDate('log_date', $today)->sum('count');

        $todayByCage = MortalityLog::with('cage')
            ->whereDate('log_date', $today)
            ->get()
            ->groupBy(fn($l) => $l->cage?->cage_code ?? 'Deleted Cage')
            ->map(fn($g) => $g->sum('count'));

        $preselectedCageId = (int) request('cage_id');

        return view('mortality', compact('cages', 'logs', 'todayTotal', 'todayByCage', 'preselectedCageId'));
    }

    public function logs()
    {
        $cages = Cage::orderBy('cage_code')->get();
        $logs  = MortalityLog::with(['cage', 'hens'])
            ->orderByDesc('log_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('mortality._logs', compact('cages', 'logs'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cage_id'  => 'required|exists:cages,id',
            'log_date' => 'required|date|before_or_equal:'.\App\Services\ReportingDateService::reportingDateString(),
            'count'    => 'required|integer|min:1',
            'reason'   => 'required|in:' . implode(',', MortalityLog::REASONS),
            'notes'    => 'nullable|string|max:1000',
        ]);

        $error = null;
        $cageCode = Cage::where('id', $data['cage_id'])->value('cage_code');

        DB::transaction(function () use ($data, &$error) {
            $slots = CageSlot::with(['hens' => fn($q) => $q->where('is_active', 1)])
                ->where('cage_id', $data['cage_id'])
                ->lockForUpdate()
                ->get();

            $activeHens = $slots->flatMap(fn($slot) => $slot->hens)
                ->sortBy(fn($h) => [$h->cage_slot_id, $h->placement_date ?? $h->date_acquired])
                ->values();

            if ($activeHens->count() < $data['count']) {
                $error = "Only {$activeHens->count()} active hen(s) available, but {$data['count']} deaths recorded.";
                return;
            }

            $hensToDeactivate = $activeHens->take($data['count']);

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

                $pivot = new MortalityLogHen;
                $pivot->mortality_log_id = $log->id;
                $pivot->hen_id = $hen->id;
                $pivot->cage_slot_id = $hen->cage_slot_id;
                $pivot->save();
            }

            $this->decrementSlotOccupancy($hensToDeactivate);
        });

        if ($error) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => ['count' => [$error]]], 422);
            }
            return back()->withErrors(['count' => $error])->withInput();
        }

        $this->checkMortalitySpike($data['cage_id'], $data['log_date']);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'cage_code' => $cageCode, 'count' => $data['count']]);
        }

        return redirect()->back()
            ->with('success', 'Mortality record saved.');
    }

    public function update(Request $request, MortalityLog $mortalityLog)
    {
        $validator = Validator::make($request->all(), [
            'log_date' => 'required|date|before_or_equal:'.\App\Services\ReportingDateService::reportingDateString(),
            'count'    => 'required|integer|min:1',
            'reason'   => 'required|in:' . implode(',', MortalityLog::REASONS),
            'notes'    => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->with('reopen_edit_mortality', $mortalityLog->id)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();
        $oldCount = $mortalityLog->count;
        $newCount = (int) $data['count'];

        // Pre-flight check for increase: ensure enough active hens exist
        $hensToDeactivate = null;
        if ($newCount > $oldCount) {
            $diff = $newCount - $oldCount;

            $linkedHenIds = MortalityLogHen::where('mortality_log_id', $mortalityLog->id)
                ->pluck('hen_id')
                ->toArray();

            $cage = Cage::with(['cageSlots.hens' => fn($q) => $q->where('is_active', 1)])
                ->findOrFail($mortalityLog->cage_id);

            $activeHens = $cage->cageSlots->flatMap(fn($slot) => $slot->hens)
                ->reject(fn($h) => in_array($h->id, $linkedHenIds))
                ->sortBy(fn($h) => [$h->cage_slot_id, $h->placement_date ?? $h->date_acquired])
                ->values();

            if ($activeHens->count() < $diff) {
                return redirect()->back()
                    ->with('reopen_edit_mortality', $mortalityLog->id)
                    ->withErrors([
                        'count' => "Only {$activeHens->count()} remaining active hen(s) available in " . ($mortalityLog->cage?->cage_code ?? 'Deleted Cage') . ", but {$diff} additional deaths recorded.",
                    ])->withInput();
            }

            $hensToDeactivate = $activeHens->take($diff);
        }

        DB::transaction(function () use ($data, $mortalityLog, $oldCount, $newCount, $hensToDeactivate) {
            $mortalityLog->update([
                'log_date' => $data['log_date'],
                'count'    => $newCount,
                'reason'   => $data['reason'],
                'notes'    => $data['notes'] ?? null,
            ]);

            if ($newCount > $oldCount) {
                foreach ($hensToDeactivate as $hen) {
                    $hen->update(['is_active' => false]);
                    $pivot = new MortalityLogHen;
                    $pivot->mortality_log_id = $mortalityLog->id;
                    $pivot->hen_id = $hen->id;
                    $pivot->cage_slot_id = $hen->cage_slot_id;
                    $pivot->save();
                }
                $this->decrementSlotOccupancy($hensToDeactivate);

            } elseif ($newCount < $oldCount) {
                $diff = $oldCount - $newCount;

                $pivotRows = MortalityLogHen::where('mortality_log_id', $mortalityLog->id)
                    ->orderByDesc('id')
                    ->take($diff)
                    ->get();

                foreach ($pivotRows as $pivot) {
                    $hen = Hen::find($pivot->hen_id);

                    if ($hen === null) {
                        Log::warning("MortalityLogHen#{$pivot->id} references hen_id {$pivot->hen_id} which no longer exists. Skipping reactivation.");
                        continue;
                    }

                    $hen->update(['is_active' => true]);
                    CageSlot::where('id', $pivot->cage_slot_id)->increment('current_occupancy');
                    $pivot->delete();
                }
            }
        });

        $this->checkMortalitySpike($mortalityLog->cage_id, $data['log_date']);

        return redirect()->back()
            ->with('success', 'Mortality record updated.');
    }

    public function destroy(MortalityLog $mortalityLog)
    {
        DB::transaction(function () use ($mortalityLog) {
            $pivotRows = MortalityLogHen::where('mortality_log_id', $mortalityLog->id)
                ->lockForUpdate()
                ->get();

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
        });

        return redirect()->back()
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
            CageSlot::where('id', $slotId)
                ->where('current_occupancy', '>', 0)
                ->decrement('current_occupancy', $decrement);
        }
    }
}
