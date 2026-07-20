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
        $sort = $request->query('sort', '');
        $preselectedCageId = (int) $request->query('cage_id');

        $cages = Cage::with('cageSlots')->orderBy('cage_code')->get();
        $breeds = Hen::distinct()->pluck('breed')->filter()->sort()->values();

        $todayTotal = MortalityLog::whereDate('log_date', today())->sum('count');

        $todayByCage = MortalityLog::with('cage')
            ->whereDate('log_date', today())
            ->get()
            ->groupBy(fn($l) => $l->cage?->cage_code ?? 'Deleted Cage')
            ->map(fn($g) => $g->sum('count'));

        $tab = $request->query('tab', 'inventory');

        return view('chickens.index', compact(
            'cages', 'breeds', 'todayTotal', 'todayByCage', 'tab',
            'cageId', 'breed', 'isActive', 'search', 'sort', 'preselectedCageId'
        ));
    }

    protected function applySort($query, ?string $sort): void
    {
        match ($sort) {
            'chicken_id_desc' => $query->orderBy('chicken_id', 'desc')->orderBy('id'),
            'breed_asc'       => $query->orderBy('breed')->orderBy('chicken_id')->orderBy('id'),
            'breed_desc'      => $query->orderBy('breed', 'desc')->orderBy('chicken_id')->orderBy('id'),
            'age_asc'         => $query->orderBy('flock_age_weeks')->orderBy('chicken_id')->orderBy('id'),
            'age_desc'        => $query->orderBy('flock_age_weeks', 'desc')->orderBy('chicken_id')->orderBy('id'),
            'date_asc'        => $query->orderBy('date_acquired')->orderBy('chicken_id')->orderBy('id'),
            'date_desc'       => $query->orderBy('date_acquired', 'desc')->orderBy('chicken_id')->orderBy('id'),
            default           => $query->orderBy('chicken_id')->orderBy('id'),
        };
    }

    public function inventoryList(Request $request)
    {
        $cageId = $request->query('cage_id');
        $breed = $request->query('breed');
        $isActive = $request->query('status', 'all');
        $search = $request->query('search');
        $sort = $request->query('sort', '');

        $hens = Hen::with(['cageSlot.cage'])
            ->when($cageId, fn($q) => $q->whereHas('cageSlot', fn($q) => $q->where('cage_id', $cageId)))
            ->when($breed, fn($q) => $q->where('breed', $breed))
            ->when($isActive !== 'all', fn($q) => $q->where('is_active', $isActive === 'active'))
            ->when($search, fn($q) => $q->where('tag_code', 'like', "%{$search}%"))
            ->when($cageId === null, fn($q) => $q->whereNotNull('cage_slot_id'))
            ->when(true, fn($q) => $this->applySort($q, $sort))
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
                ->when(true, fn($q) => $this->applySort($q, $sort))
                ->get();
        }

        $unplacedCount = $unplacedHens->count();

        return response()
            ->view('chickens._inventory-list', compact('hensByCage', 'unplacedHens', 'unplacedCount'))
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
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
        $data['sex'] = 'hen';

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
                    'chicken_id'             => $chickenId,
                    'breed'                  => $data['breed'],
                    'sex'                    => $data['sex'],
                    'source'                 => $data['source'] ?? null,
                    'date_acquired'          => $data['date_acquired'],
                    'age_at_placement_weeks' => $data['age_at_placement_weeks'],
                    'flock_age_weeks'        => $data['age_at_placement_weeks'],
                    'initial_health_status'  => $data['initial_health_status'] ?? null,
                    'notes'                  => $data['notes'] ?? null,
                    'is_active'              => true,
                ]);
                $created[] = $hen;
                $next++;
            }

            return [$created, $firstId, $next - 1];
        });

        [$created, $firstId, $lastId] = $hens;
        $count = count($created);

        session()->forget('show_no_chickens_modal');

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
            'hen_id'    => 'required|string',
            'cull_date' => 'required|date',
            'reason'    => 'required|in:low_production,illness,aggression,age,other',
            'notes'     => 'nullable|string|max:1000',
        ]);

        $henIds = array_filter(array_map('intval', explode(',', $data['hen_id'])));
        $henIds = array_unique($henIds);

        if (empty($henIds)) {
            return back()->withErrors(['hen_id' => 'No valid hens specified.']);
        }

        $culled = [];
        $errors = [];
        foreach ($henIds as $henId) {
            DB::transaction(function () use ($henId, $data, &$culled, &$errors) {
                $hen = Hen::with('cageSlot')->find($henId);
                if (!$hen || !$hen->is_active) {
                    $errors[] = "Hen #{$henId} not found or already inactive.";
                    return;
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
                $culled[] = $hen->chicken_id;
            });
        }

        if (empty($culled)) {
            return back()->withErrors(['hen_id' => implode(' ', $errors)]);
        }

        $msg = count($culled) . ' hen(s) culled';
        if (!empty($errors)) {
            $msg .= '. ' . implode(' ', $errors);
        }
        return redirect()->back()->with('success', $msg . '.');
    }

    public function storeRemoval(Request $request)
    {
        $data = $request->validate([
            'hen_id'       => 'required|string',
            'removal_date' => 'required|date',
            'reason'       => 'required|string|max:100',
            'destination'  => 'nullable|string|max:200',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $henIds = array_filter(array_map('intval', explode(',', $data['hen_id'])));
        $henIds = array_unique($henIds);

        if (empty($henIds)) {
            return back()->withErrors(['hen_id' => 'No valid hens specified.']);
        }

        $removed = [];
        $errors = [];
        foreach ($henIds as $henId) {
            DB::transaction(function () use ($henId, $data, &$removed, &$errors) {
                $hen = Hen::with('cageSlot')->find($henId);
                if (!$hen || !$hen->is_active) {
                    $errors[] = "Hen #{$henId} not found or already inactive.";
                    return;
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
                $removed[] = $hen->chicken_id;
            });
        }

        if (empty($removed)) {
            return back()->withErrors(['hen_id' => implode(' ', $errors)]);
        }

        $msg = count($removed) . ' hen(s) removed';
        if (!empty($errors)) {
            $msg .= '. ' . implode(' ', $errors);
        }
        return redirect()->back()->with('success', $msg . '.');
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

        $toMove = $hens->count();
        $transferDate = $data['transfer_date'] ?? today()->toDateString();
        $transferReason = $data['transfer_reason'] ?? null;

        $error = null;
        $destCode = null;
        $destSlotNumber = null;

        DB::transaction(function () use ($hens, $data, $toMove, $transferDate, $transferReason, &$error, &$destCode, &$destSlotNumber) {
            $destSlot = CageSlot::with('cage')->where('id', $data['destination_slot_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $destCode = $destSlot->cage->cage_code;
            $destSlotNumber = $destSlot->slot_number;

            if ($destSlot->remaining < $toMove) {
                $error = "Destination slot only has {$destSlot->remaining} space(s), but {$toMove} hens need to move.";
                return;
            }

            $sourceSlotIds = $hens->pluck('cage_slot_id')->filter()->unique()->values()->toArray();
            if (! empty($sourceSlotIds)) {
                CageSlot::whereIn('id', $sourceSlotIds)->lockForUpdate()->get();
            }

            foreach ($hens as $hen) {
                $fromSlotId = $hen->cage_slot_id;

                $sourceSlot = $hen->cageSlot;
                if ($sourceSlot) {
                    $sourceSlot->decrement('current_occupancy');
                }
                $hen->cage_slot_id = $destSlot->id;
                $hen->save();
                $destSlot->increment('current_occupancy');

                CageTransfer::create([
                    'hen_id'            => $hen->id,
                    'from_cage_slot_id'  => $fromSlotId,
                    'to_cage_slot_id'    => $destSlot->id,
                    'transfer_date'     => $transferDate,
                    'reason'            => $transferReason,
                    'recorded_by'       => auth()->id(),
                ]);
            }
        });

        if ($error) {
            return back()->withErrors(['destination_slot_id' => $error]);
        }

        return redirect()->back()
            ->with('success', "{$toMove} hen(s) moved to {$destCode} slot {$destSlotNumber}.");
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

        $hens = Hen::with('cageSlot.cage')->whereIn('id', $henIds)->where('is_active', 1)->get();
        if ($hens->isEmpty()) {
            return back()->withErrors(['hen_ids' => 'No active hens selected.']);
        }

        $recordMortality = isset($data['record_mortality']) && $data['record_mortality'];

        $unplacedCount = 0;
        $removedCount = 0;
        $mortalityCount = 0;
        $errors = [];
        $henIdsDeactivated = [];

        foreach ($hens as $hen) {
            DB::transaction(function () use ($hen, $data, $recordMortality, &$errors, &$removedCount, &$unplacedCount, &$mortalityCount, &$henIdsDeactivated) {
                $hen->refresh();

                if (!$hen->is_active) {
                    $errors[] = "Hen #{$hen->chicken_id} already inactive.";
                    return;
                }

                $sourceSlot = $hen->cageSlot;
                $wasPlaced = false;
                if ($sourceSlot) {
                    $sourceSlot->decrement('current_occupancy');
                    $wasPlaced = true;
                } else {
                    $unplacedCount++;
                }

                $hen->is_active = false;
                if ($recordMortality && !empty($data['reason']) && $wasPlaced) {
                    $hen->deactivation_cause = 'mortality';
                    $mortalityCount++;
                }
                $hen->save();
                $henIdsDeactivated[] = $hen->id;
                $removedCount++;
            });
        }

        $mortalityCageIds = [];

        if ($mortalityCount > 0) {
            $mortalityHens = Hen::whereIn('id', $henIdsDeactivated)
                ->where('deactivation_cause', 'mortality')
                ->with('cageSlot.cage')
                ->get();

            $mortalityByCage = $mortalityHens->groupBy(fn($h) => $h->cageSlot->cage_id);

            DB::transaction(function () use ($mortalityByCage, $data, &$mortalityCageIds) {
                $mortalityCageIds = [];
                $henIdsToClear = [];

                foreach ($mortalityByCage as $cageId => $cageHens) {
                    $log = MortalityLog::create([
                        'cage_id'     => $cageId,
                        'log_date'    => now()->toDateString(),
                        'count'       => $cageHens->count(),
                        'reason'      => $data['reason'],
                        'notes'       => $data['notes'] ?? null,
                        'recorded_by' => auth()->id(),
                    ]);

                    foreach ($cageHens as $hen) {
                        $pivot = new MortalityLogHen;
                        $pivot->mortality_log_id = $log->id;
                        $pivot->hen_id = $hen->id;
                        $pivot->cage_slot_id = $hen->cage_slot_id;
                        $pivot->save();
                        $henIdsToClear[] = $hen->id;
                    }

                    $mortalityCageIds[] = $cageId;
                }

                Hen::whereIn('id', $henIdsToClear)->update(['deactivation_cause' => null]);
            });

            foreach (array_unique($mortalityCageIds) as $cageId) {
                $this->checkMortalitySpike($cageId, now()->toDateString());
            }
        }

        $parts = ["{$removedCount} hen(s) removed"];
        if ($mortalityCount > 0) {
            $parts[] = "{$mortalityCount} recorded as mortality";
        }
        if ($unplacedCount > 0) {
            $parts[] = "{$unplacedCount} unplaced hen(s) skipped mortality logging";
        }
        if (!empty($errors)) {
            $parts[] = implode(' ', $errors);
        }

        return redirect()->back()->with('success', implode('. ', $parts) . '.');
    }
}
