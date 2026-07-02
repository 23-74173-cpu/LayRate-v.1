<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\ProductionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EggLoggingController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->toDateString();
        $cageFilter = $request->query('cage_id');

        $cages = Cage::where('is_active', 1)->orderBy('cage_code')->get();

        $slotQuery = CageSlot::with([
            'cage',
            'hens' => fn($q) => $q->where('is_active', 1),
        ])->whereHas('cage', fn($q) => $q->where('is_active', 1))
          ->orderBy('cage_id')
          ->orderBy('slot_number');

        if ($cageFilter) {
            $slotQuery->where('cage_id', $cageFilter);
        }

        $cageSlots = $slotQuery->get();

        $slotIds = $cageSlots->pluck('id');
        $todayLogs = ProductionLog::whereIn('cage_slot_id', $slotIds)
            ->where('log_date', $today)
            ->get()
            ->keyBy('cage_slot_id');

        $cageSlots->each(function ($slot) use ($todayLogs) {
            $slot->today_egg_count = $todayLogs->get($slot->id)?->egg_count ?? 0;
        });

        $todayTotal = ProductionLog::where('log_date', $today)
            ->when($cageFilter, fn($q) => $q->whereHas('cageSlot', fn($s) => $s->where('cage_id', $cageFilter)))
            ->sum('egg_count');

        $todayByCage = ProductionLog::with('cageSlot.cage')
            ->where('log_date', $today)
            ->get()
            ->groupBy(fn($l) => $l->cageSlot?->cage?->cage_code)
            ->map(fn($g) => $g->sum('egg_count'));

        $selectedCage = $cageFilter ? $cages->firstWhere('id', $cageFilter) : null;

        return view('egg-logging', compact(
            'cageSlots', 'cages', 'cageFilter', 'todayTotal', 'todayByCage', 'selectedCage'
        ));
    }

    public function recentLogs(Request $request)
    {
        $cageFilter = $request->query('cage_id');

        $cages = Cage::where('is_active', 1)->orderBy('cage_code')->get();

        $logsQuery = ProductionLog::with(['cageSlot.cage', 'overriddenBy', 'recorder'])
            ->orderByDesc('log_date')
            ->orderByDesc('created_at');

        if ($cageFilter) {
            $logsQuery->whereHas('cageSlot', fn($q) => $q->where('cage_id', $cageFilter));
        }

        $logs = $logsQuery->paginate(20)->withQueryString();

        return view('eggs.recent-logs', compact('logs', 'cages', 'cageFilter'));
    }

    public function logs(Request $request)
    {
        $cageFilter = $request->query('cage_id');

        $logsQuery = ProductionLog::with(['cageSlot.cage', 'overriddenBy', 'recorder'])
            ->orderByDesc('log_date')
            ->orderByDesc('created_at');

        if ($cageFilter) {
            $logsQuery->whereHas('cageSlot', fn($q) => $q->where('cage_id', $cageFilter));
        }

        $logs = $logsQuery->paginate(20)->withQueryString();

        return view('egg-logging._logs', compact('logs', 'cageFilter'));
    }

    public function verifyOverride(Request $request)
    {
        $data = $request->validate([
            'cage_slot_id' => 'required|exists:cage_slots,id',
            'pin'          => 'nullable|string',
            'password'     => 'nullable|string',
        ]);

        $user = auth()->user();

        if ($user->override_pin_hash !== null) {
            if (! $data['pin'] || ! Hash::check($data['pin'], $user->override_pin_hash)) {
                return response()->json(['ok' => false, 'error' => 'Incorrect PIN.'], 422);
            }
        } else {
            if (! $data['password'] || ! Hash::check($data['password'], $user->password)) {
                return response()->json(['ok' => false, 'error' => 'Incorrect password.'], 422);
            }
        }

        session()->put("override_verified_slot.{$data['cage_slot_id']}", now()->timestamp);

        return response()->json([
            'ok'              => true,
            'needs_pin_setup' => $user->override_pin_hash === null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'log_date'    => 'required|date',
            'cage_slot_id' => 'required|exists:cage_slots,id',
            'egg_count'   => 'required|integer|min:0',
            'hen_count'   => 'required|integer|min:1',
            'notes'       => 'nullable|string',
        ]);

        $slot = CageSlot::with('cage')->findOrFail($data['cage_slot_id']);

        $payload = [
            'cage_slot_id' => $slot->id,
            'egg_count'    => $data['egg_count'],
            'hen_count'    => $data['hen_count'],
            'hdep'         => round(($data['egg_count'] / $data['hen_count']) * 100, 2),
            'recorded_by'  => auth()->id(),
            'notes'        => $data['notes'] ?? 'Manual entry',
        ];

        ProductionLog::updateOrCreate(
            ['cage_slot_id' => $slot->id, 'log_date' => $data['log_date']],
            $payload
        );

        return redirect()->route('eggs.logging')->with('success', 'Production log saved.');
    }

    public function update(Request $request, ProductionLog $productionLog)
    {
        $data = $request->validate([
            'log_date'  => 'required|date',
            'egg_count' => 'required|integer|min:0',
            'hen_count' => 'required|integer|min:1',
            'notes'     => 'nullable|string',
        ]);

        $productionLog->update([
            'log_date'  => $data['log_date'],
            'egg_count' => $data['egg_count'],
            'hen_count' => $data['hen_count'],
            'hdep'      => round(($data['egg_count'] / $data['hen_count']) * 100, 2),
            'notes'     => $data['notes'] ?? null,
        ]);

        return redirect()->route('eggs.logging')->with('success', 'Production log updated.');
    }

    public function destroy(ProductionLog $productionLog)
    {
        $productionLog->delete();
        return redirect()->route('eggs.logging')->with('success', 'Log deleted.');
    }
}
