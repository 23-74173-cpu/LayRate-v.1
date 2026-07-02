<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\MortalityLogHen;
use App\Models\CageTransfer;
use App\Models\CullingLog;
use App\Models\Removal;
use App\Models\HealthEvent;
use App\Models\WeightCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChickensController extends Controller
{
    public function index(Request $request)
    {
        $cageId = $request->query('cage_id');
        $breed = $request->query('breed');
        $isActive = $request->query('status', 'all');
        $search = $request->query('search');

        $cages = Cage::with('cageSlots')->orderBy('cage_code')->get();
        $breeds = Hen::distinct()->pluck('breed')->filter()->sort()->values();

        $todayTotal = MortalityLog::whereDate('log_date', today())->sum('count');

        $todayByCage = MortalityLog::with('cage')
            ->whereDate('log_date', today())
            ->get()
            ->groupBy(fn($l) => $l->cage->cage_code)
            ->map(fn($g) => $g->sum('count'));

        $tab = $request->query('tab', 'inventory');

        return view('chickens.index', compact(
            'cages', 'breeds', 'todayTotal', 'todayByCage', 'tab',
            'cageId', 'breed', 'isActive', 'search'
        ));
    }

    public function inventoryList(Request $request)
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
            ->when($cageId === null, fn($q) => $q->whereNotNull('cage_slot_id')) // hide unplaced unless explicitly queried
            ->orderBy('id')
            ->get();

        $hensByCage = $hens
            ->groupBy(fn($h) => $h->cage?->id)
            ->filter()
            ->sortBy(fn($group) => $group->first()->cage?->cage_code);

        // Separate unplaced hens (cage_slot_id = null) — only when not filtering by cage
        $unplacedHens = collect();
        if (! $cageId) {
            $unplacedHens = Hen::with(['cageSlot.cage'])
                ->whereNull('cage_slot_id')
                ->when($breed, fn($q) => $q->where('breed', $breed))
                ->when($isActive !== 'all', fn($q) => $q->where('is_active', $isActive === 'active'))
                ->when($search, fn($q) => $q->where('tag_code', 'like', "%{$search}%"))
                ->orderBy('id')
                ->get();
        }

        $unplacedCount = $unplacedHens->count();

        return view('chickens._inventory-list', compact('hensByCage', 'unplacedHens', 'unplacedCount'));
    }

    public function mortalityRecords(Request $request)
    {
        $mortalityLogs = MortalityLog::with(['cage', 'recorder'])
            ->orderByDesc('log_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('chickens._mortality-records', compact('mortalityLogs'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'breed'              => 'required|string|in:ISA Brown,Lohmann Brown-Classic,Dekalb White,Hy-Line Brown,Novogen Brown',
            'source'             => 'nullable|string|max:200',
            'date_acquired'      => 'required|date',
            'age_at_placement_weeks' => 'required|integer|min:0|max:200',
            'initial_health_status' => 'nullable|string|max:100',
            'notes'              => 'nullable|string|max:1000',
            'quantity'           => 'required|integer|min:1|max:100',
        ]);

        $quantity = (int) $data['quantity'];
        $year = now()->format('Y');
        $prefix = "CHK-{$year}-%";

        $hens = DB::transaction(function () use ($quantity, $data, $year, $prefix) {
            $last = Hen::where('chicken_id', 'like', $prefix)
                ->lockForUpdate()
                ->orderBy('chicken_id', 'desc')
                ->value('chicken_id');

            $next = $last ? (int) substr($last, -5) + 1 : 1;
            $firstId = $next;

            $created = [];
            for ($i = 0; $i < $quantity; $i++) {
                $chickenId = sprintf("CHK-%s-%05d", $year, $next);
                $hen = Hen::create([
                    'chicken_id'            => $chickenId,
                    'breed'                 => $data['breed'],
                    'sex'                   => 'hen',
                    'source'                => $data['source'] ?? null,
                    'date_acquired'         => $data['date_acquired'],
                    'age_at_placement_weeks' => $data['age_at_placement_weeks'],
                    'initial_health_status' => $data['initial_health_status'] ?? null,
                    'notes'                 => $data['notes'] ?? null,
                    'is_active'             => true,
                ]);
                $created[] = $hen;
                $next++;
            }

            return [$created, $firstId, $next - 1];
        });

        [$created, $firstId, $lastId] = $hens;
        $count = count($created);

        if ($count === 1) {
            return redirect()->back()->with('success', "1 hen registered: CHK-{$year}-" . str_pad($firstId, 5, '0', STR_PAD_LEFT) . ".");
        }

        return redirect()->back()->with('success', "{$count} hens registered: CHK-{$year}-" . str_pad($firstId, 5, '0', STR_PAD_LEFT) . " to CHK-{$year}-" . str_pad($lastId, 5, '0', STR_PAD_LEFT) . ".");
    }

    public function storeHealthEvent(Request $request)
    {
        $data = $request->validate([
            'hen_id'      => 'required|integer|exists:hens,id',
            'event_date'  => 'required|date',
            'event_type'  => 'required|in:sick,treated,recovered',
            'description' => 'nullable|string|max:255',
            'notes'       => 'nullable|string|max:1000',
        ]);

        HealthEvent::create([
            'hen_id'      => $data['hen_id'],
            'event_date'  => $data['event_date'],
            'event_type'  => $data['event_type'],
            'description' => $data['description'] ?? null,
            'notes'       => $data['notes'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Health event logged.');
    }

    public function storeWeightCheck(Request $request)
    {
        $data = $request->validate([
            'hen_id'     => 'required|integer|exists:hens,id',
            'check_date' => 'required|date',
            'weight_kg'  => 'required|numeric|min:0|max:20',
            'notes'      => 'nullable|string|max:1000',
        ]);

        WeightCheck::create([
            'hen_id'     => $data['hen_id'],
            'check_date' => $data['check_date'],
            'weight_kg'  => $data['weight_kg'],
            'notes'      => $data['notes'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Weight recorded.');
    }

    public function storeCulling(Request $request)
    {
        $data = $request->validate([
            'hen_id'    => 'required|integer|exists:hens,id',
            'cull_date' => 'required|date',
            'reason'    => 'required|in:low_production,illness,aggression,age,other',
            'notes'     => 'nullable|string|max:1000',
        ]);

        $hen = Hen::with('cageSlot')->findOrFail($data['hen_id']);
        if (!$hen->is_active) {
            return back()->withErrors(['hen_id' => 'This hen is already inactive.']);
        }

        if ($hen->cageSlot) {
            $hen->cageSlot->decrement('current_occupancy');
        }
        $hen->update(['is_active' => false]);

        CullingLog::create([
            'hen_id'      => $hen->id,
            'cull_date'   => $data['cull_date'],
            'reason'      => $data['reason'],
            'notes'       => $data['notes'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', "{$hen->chicken_id} culled and removed from active inventory.");
    }

    public function storeRemoval(Request $request)
    {
        $data = $request->validate([
            'hen_id'      => 'required|integer|exists:hens,id',
            'removal_date' => 'required|date',
            'reason'      => 'required|string|max:100',
            'destination' => 'nullable|string|max:200',
            'notes'       => 'nullable|string|max:1000',
        ]);

        $hen = Hen::with('cageSlot')->findOrFail($data['hen_id']);
        if (!$hen->is_active) {
            return back()->withErrors(['hen_id' => 'This hen is already inactive.']);
        }

        if ($hen->cageSlot) {
            $hen->cageSlot->decrement('current_occupancy');
        }
        $hen->update(['is_active' => false]);

        Removal::create([
            'hen_id'       => $hen->id,
            'removal_date' => $data['removal_date'],
            'reason'       => $data['reason'],
            'destination'  => $data['destination'] ?? null,
            'notes'        => $data['notes'] ?? null,
            'recorded_by'  => auth()->id(),
        ]);

        return redirect()->back()->with('success', "{$hen->chicken_id} removed from active inventory.");
    }

    public function cullingRecords(Request $request)
    {
        $cullingLogs = CullingLog::with(['hen.cageSlot.cage', 'recorder'])
            ->orderByDesc('cull_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('chickens._culling-records', compact('cullingLogs'));
    }

    public function removalRecords(Request $request)
    {
        $removalLogs = Removal::with(['hen.cageSlot.cage', 'recorder'])
            ->orderByDesc('removal_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('chickens._removal-records', compact('removalLogs'));
    }

    public function move(Request $request)
    {
        $data = $request->validate([
            'hen_ids'             => 'required|string',
            'destination_slot_id' => 'required|integer|exists:cage_slots,id',
            'move_count'          => 'nullable|integer|min:1',
            'transfer_date'       => 'nullable|date',
            'transfer_reason'     => 'nullable|string|max:200',
        ]);

        $henIds = array_filter(array_map('intval', explode(',', $data['hen_ids'])));
        $henIds = array_unique($henIds);

        if (isset($data['move_count'])) {
            $henIds = array_slice($henIds, 0, (int) $data['move_count']);
        }

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

        $transferDate = $data['transfer_date'] ?? today()->toDateString();
        $transferReason = $data['transfer_reason'] ?? null;

        foreach ($hens as $hen) {
            $fromSlotId = $hen->cage_slot_id;

            $sourceSlot = $hen->cageSlot;
            if ($sourceSlot) {
                $sourceSlot->decrement('current_occupancy');
            }
            $hen->update(['cage_slot_id' => $destinationSlot->id]);
            $destinationSlot->increment('current_occupancy');

            CageTransfer::create([
                'hen_id'           => $hen->id,
                'from_cage_slot_id' => $fromSlotId,
                'to_cage_slot_id'   => $destinationSlot->id,
                'transfer_date'    => $transferDate,
                'reason'           => $transferReason,
                'recorded_by'      => auth()->id(),
            ]);
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
            $log = MortalityLog::create([
                'cage_id'      => $cageId,
                'log_date'     => now()->toDateString(),
                'count'        => $toRemove,
                'reason'       => $data['reason'],
                'notes'        => $data['notes'] ?? null,
                'recorded_by'  => auth()->id(),
            ]);

            foreach ($hens as $hen) {
                MortalityLogHen::create([
                    'mortality_log_id' => $log->id,
                    'hen_id'           => $hen->id,
                    'cage_slot_id'     => $hen->cage_slot_id,
                ]);
            }
        }

        $mortalityNote = $recordMortality ? ' and recorded as mortality.' : '.';
        return redirect()->back()
            ->with('success', "{$toRemove} hen(s) removed{$mortalityNote}");
    }
}
