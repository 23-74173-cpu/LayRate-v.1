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
        $date       = $request->get('date', now()->toDateString());
        $cageFilter = $request->get('cage', 'all');

        $cages = Cage::where('is_active', 1)->orderBy('cage_code')->get();

        $slotsQuery = CageSlot::with([
                'cage',
                'hens'           => fn($q) => $q->where('is_active', 1),
                'productionLogs' => fn($q) => $q->where('log_date', $date),
            ])
            ->where('current_occupancy', '>', 0)
            ->whereHas('cage', fn($q) => $q->where('is_active', 1));

        if ($cageFilter !== 'all') {
            $slotsQuery->whereHas('cage', fn($q) => $q->where('cage_code', $cageFilter));
        }

        $slots = $slotsQuery->get()
            ->sortBy([['cage.cage_code', 'asc'], ['slot_number', 'asc']])
            ->map(function ($slot) {
                $slot->today_egg_count = $slot->productionLogs->first()?->egg_count ?? 0;
                return $slot;
            });

        $logs = ProductionLog::with(['cageSlot.cage', 'overriddenBy', 'recorder'])
            ->orderByDesc('log_date')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('egg-logging', compact('cages', 'slots', 'date', 'cageFilter', 'logs'));
    }

    public function verifyOverride(Request $request)
    {
        $data = $request->validate([
            'slot_id'  => 'required|exists:cage_slots,id',
            'pin'      => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $user = auth()->user();

        CageSlot::whereHas('cage', fn($q) => $q->where('is_active', 1))
            ->findOrFail($data['slot_id']);

        if ($user->override_pin_hash !== null) {
            if (! $data['pin'] || ! Hash::check($data['pin'], $user->override_pin_hash)) {
                return response()->json(['ok' => false, 'error' => 'Incorrect PIN.'], 422);
            }
        } else {
            if (! $data['password'] || ! Hash::check($data['password'], $user->password)) {
                return response()->json(['ok' => false, 'error' => 'Incorrect password.'], 422);
            }
        }

        session()->put("override_verified.{$data['slot_id']}", now()->timestamp);

        return response()->json([
            'ok'              => true,
            'needs_pin_setup' => $user->override_pin_hash === null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'log_date'     => 'required|date|before_or_equal:today',
            'cage_slot_id' => 'required|exists:cage_slots,id',
            'egg_count'    => 'required|integer|min:0',
            'hen_count'    => 'required|integer|min:1',
            'notes'        => 'nullable|string',
        ]);

        $slot = CageSlot::findOrFail($data['cage_slot_id']);
        $existing = ProductionLog::where('cage_slot_id', $data['cage_slot_id'])
            ->where('log_date', $data['log_date'])
            ->first();

        $payload = [
            'egg_count'   => $data['egg_count'],
            'hen_count'   => $data['hen_count'],
            'hdep'        => round(($data['egg_count'] / $data['hen_count']) * 100, 2),
            'recorded_by' => auth()->id(),
            'notes'       => $data['notes'] ?? 'Manual entry',
        ];

        if ($slot->has_sensor && $data['log_date'] === now()->toDateString()) {
            $valueChanged = ! $existing || (int) $existing->egg_count !== (int) $data['egg_count'];
            $verifiedAt   = session("override_verified.{$data['cage_slot_id']}");
            $stillValid   = $verifiedAt && (now()->timestamp - $verifiedAt) <= 600;

            if ($valueChanged && ! $stillValid) {
                return back()->withInput()->withErrors([
                    'egg_count' => "This slot's reading is sensor-locked. Use the override option to change it.",
                ]);
            }

            if ($valueChanged) {
                $payload['overridden_by_user_id'] = auth()->id();
                $payload['overridden_at']          = now();
                session()->forget("override_verified.{$data['cage_slot_id']}");
            }
        }

        ProductionLog::updateOrCreate(
            ['cage_slot_id' => $data['cage_slot_id'], 'log_date' => $data['log_date']],
            $payload
        );

        return redirect()->route('egg-logging', request()->only('date', 'cage'))->with('success', 'Production log saved.');
    }

    public function destroy(ProductionLog $productionLog)
    {
        $productionLog->delete();
        return redirect()->route('egg-logging')->with('success', 'Log deleted.');
    }
}
