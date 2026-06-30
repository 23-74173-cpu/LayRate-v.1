<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use App\Models\MortalityLog;
use Illuminate\Http\Request;

class ChickensController extends Controller
{
    public function index(Request $request)
    {
        $cageId = $request->query('cage_id');
        $breed = $request->query('breed');
        $isActive = $request->query('status', 'all');
        $search = $request->query('search');

        $hens = Hen::with(['cageSlot.cage'])
            ->when($cageId, fn($q) => $q->whereHas('cageSlot', fn($q) => $q->where('cage_id', $cageId)))
            ->when($breed, fn($q) => $q->where('breed', $breed))
            ->when($isActive !== 'all', fn($q) => $q->where('is_active', $isActive === 'active'))
            ->when($search, fn($q) => $q->where('tag_code', 'like', "%{$search}%"))
            ->orderBy('id')
            ->get();

        $hensByCage = $hens
            ->groupBy(fn($h) => $h->cage?->id)
            ->filter()
            ->sortBy(fn($group) => $group->first()->cage?->cage_code);

        $cages = Cage::with('cageSlots')->orderBy('cage_code')->get();
        $breeds = Hen::distinct()->pluck('breed')->filter()->sort()->values();

        $mortalityLogs = MortalityLog::with(['cage', 'recorder'])
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

        $tab = $request->query('tab', 'inventory');

        return view('chickens.index', compact(
            'hensByCage', 'cages', 'breeds', 'mortalityLogs', 'todayTotal', 'todayByCage', 'tab',
            'cageId', 'breed', 'isActive', 'search'
        ));
    }

    public function move(Request $request)
    {
        $data = $request->validate([
            'hen_ids'            => 'required|string',
            'destination_slot_id' => 'required|integer|exists:cage_slots,id',
        ]);

        $henIds = array_filter(array_map('intval', explode(',', $data['hen_ids'])));
        $henIds = array_unique($henIds);

        if (empty($henIds)) {
            return back()->withErrors(['hen_ids' => 'No hens selected.']);
        }

        $hens = Hen::whereIn('id', $henIds)->where('is_active', 1)->get();
        if ($hens->isEmpty()) {
            return back()->withErrors(['hen_ids' => 'No active hens selected.']);
        }

        $destinationSlot = CageSlot::with('cage')->findOrFail($data['destination_slot_id']);
        $destinationCage = $destinationSlot->cage;

        $toMove = $hens->count();
        if ($destinationSlot->remaining < $toMove) {
            return back()->withErrors([
                'destination_slot_id' => "Destination slot only has {$destinationSlot->remaining} space(s), but {$toMove} hens need to move.",
            ]);
        }

        foreach ($hens as $hen) {
            $sourceSlot = $hen->cageSlot;
            if ($sourceSlot) {
                $sourceSlot->decrement('current_occupancy');
            }
            $hen->update(['cage_slot_id' => $destinationSlot->id]);
            $destinationSlot->increment('current_occupancy');
        }

        return redirect()->back()
            ->with('success', "{$toMove} hen(s) moved to {$destinationCage->cage_code} slot {$destinationSlot->slot_number}.");
    }

    public function remove(Request $request)
    {
        $data = $request->validate([
            'hen_ids'            => 'required|string',
            'record_mortality'   => 'nullable|boolean',
            'reason'             => 'nullable|string|in:' . implode(',', MortalityLog::REASONS),
            'notes'              => 'nullable|string|max:1000',
        ]);

        $henIds = array_filter(array_map('intval', explode(',', $data['hen_ids'])));
        $henIds = array_unique($henIds);

        if (empty($henIds)) {
            return back()->withErrors(['hen_ids' => 'No hens selected.']);
        }

        $hens = Hen::whereIn('id', $henIds)->where('is_active', 1)->get();
        if ($hens->isEmpty()) {
            return back()->withErrors(['hen_ids' => 'No active hens selected.']);
        }

        $toRemove = $hens->count();
        $recordMortality = isset($data['record_mortality']) && $data['record_mortality'];

        $cageId = $hens->first()->cage?->id;

        foreach ($hens as $hen) {
            $sourceSlot = $hen->cageSlot;
            if ($sourceSlot) {
                $sourceSlot->decrement('current_occupancy');
            }
            $hen->update(['is_active' => false]);
        }

        if ($recordMortality && !empty($data['reason'])) {
            MortalityLog::create([
                'cage_id'      => $cageId,
                'log_date'     => now()->toDateString(),
                'count'        => $toRemove,
                'reason'       => $data['reason'],
                'notes'        => $data['notes'] ?? null,
                'recorded_by'  => auth()->id(),
            ]);
        }

        $mortalityNote = $recordMortality ? ' and recorded as mortality.' : '.';
        return redirect()->back()
            ->with('success', "{$toRemove} hen(s) removed{$mortalityNote}");
    }
}
