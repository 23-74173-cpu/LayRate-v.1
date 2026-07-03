<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\HardwareItem;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\Note;
use App\Models\ProductionLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CageController extends Controller
{
    public function index()
    {
        $gridRows = (int) Setting::get('farm_grid_rows', 4);
        $gridCols = (int) Setting::get('farm_grid_cols', 4);

        $cages = Cage::with([
            'cageSlots',
            'hens' => fn ($q) => $q->where('is_active', 1)->orderBy('id'),
        ])->orderBy('cage_code')->get();

        $nextCageCode = $this->generateCageCode();

        // Available (spare) sensors per type — hardware inventory stock minus assigned
        $spareCounts = HardwareItem::where('status', 'spare')
            ->selectRaw('device_type, COUNT(*) as c')
            ->groupBy('device_type')
            ->pluck('c', 'device_type');

        return view('cages.index', compact('cages', 'nextCageCode', 'gridRows', 'gridCols', 'spareCounts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'rows' => 'required|integer|min:1|max:10',
            'slots_per_row' => 'required|integer|min:1|max:10',
            'max_chickens_per_slot' => 'required|integer|min:1|max:10',
        ]);

        $cageCode = $this->generateCageCode();

        $totalCapacity = (int) $data['rows'] * (int) $data['slots_per_row'] * (int) $data['max_chickens_per_slot'];

        $cage = Cage::create([
            'cage_code' => $cageCode,
            'rows' => $data['rows'],
            'slots_per_row' => $data['slots_per_row'],
            'max_chickens_per_slot' => $data['max_chickens_per_slot'],
            'total_capacity' => $totalCapacity,
            'is_active' => 1,
        ]);

        $this->createSlotsForCage($cage);

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} added with {$cage->rows}×{$cage->slots_per_row} = {$cage->total_capacity} capacity.");
    }

    public function update(Request $request, Cage $cage)
    {
        $data = $request->validate([
            'rows' => 'nullable|integer|min:1|max:10',
            'slots_per_row' => 'nullable|integer|min:1|max:10',
            'max_chickens_per_slot' => 'nullable|integer|min:1|max:10',
            'is_active' => 'nullable|boolean',
            'slots' => 'nullable|array',
            'slots.*.has_sensor' => 'nullable|boolean',
            'dht22_count' => 'nullable|integer|min:0|max:20',
        ]);

        if ($cage->is_active && isset($data['is_active']) && ! $data['is_active']) {
            $occupiedSlots = $cage->cageSlots()->where('current_occupancy', '>', 0)->count();
            if ($occupiedSlots > 0) {
                return back()->withInput()->withErrors([
                    'is_active' => "Cannot deactivate cage with {$occupiedSlots} occupied slot(s). Rehome or remove hens first.",
                ]);
            }
        }

        $resizeBlocked = $this->checkResizeSafety($cage, $data);
        if ($resizeBlocked) {
            return redirect()->route('cages.index')
                ->with('edit_cage_id', $cage->id)
                ->withInput()
                ->withErrors($resizeBlocked);
        }

        $updateData = array_intersect_key($data, array_flip(['rows', 'slots_per_row', 'max_chickens_per_slot', 'is_active']));

        if (isset($updateData['rows']) || isset($updateData['slots_per_row']) || isset($updateData['max_chickens_per_slot'])) {
            $newRows = $updateData['rows'] ?? $cage->rows;
            $newSlots = $updateData['slots_per_row'] ?? $cage->slots_per_row;
            $newMax = $updateData['max_chickens_per_slot'] ?? $cage->max_chickens_per_slot;
            $updateData['total_capacity'] = $newRows * $newSlots * $newMax;
            $this->resizeSlots($cage, $newRows, $newSlots, $newMax);
        }

        $cage->update($updateData);

        // ── Counting sensors (IR break beam), per slot ──
        // Assignments draw from spare inventory; unchecking returns the sensor
        // to inventory (status 'spare', unassigned). Device IDs auto-generated.
        if (isset($data['slots']) && is_array($data['slots'])) {
            $slotsNeedingSensor = [];
            foreach ($data['slots'] as $slotId => $slotData) {
                $slot = $cage->cageSlots()->find($slotId);
                if (! $slot) {
                    continue;
                }

                $hasSensor = (bool) ($slotData['has_sensor'] ?? false);
                $existing = HardwareItem::where('cage_slot_id', $slot->id)
                    ->where('device_type', 'IR_breakbeam')
                    ->whereIn('status', ['active', 'faulty'])
                    ->first();

                if ($hasSensor && ! $existing) {
                    $slotsNeedingSensor[] = $slot;
                } elseif (! $hasSensor && $existing) {
                    $existing->update(['status' => 'spare', 'cage_id' => null, 'cage_slot_id' => null]);
                }
            }

            if (count($slotsNeedingSensor) > 0) {
                $spareIr = HardwareItem::where('device_type', 'IR_breakbeam')->where('status', 'spare')->count();
                if (count($slotsNeedingSensor) > $spareIr) {
                    return back()->withInput()->withErrors([
                        'slots' => 'Only '.$spareIr.' IR break-beam sensor(s) available in inventory, but '.count($slotsNeedingSensor).' requested.',
                    ]);
                }
                foreach ($slotsNeedingSensor as $slot) {
                    $this->assignSpareSensor('IR_breakbeam', null, $slot->id);
                }
            }
        }

        // ── Temperature & humidity sensors (DHT22), cage level ──
        if (isset($data['dht22_count'])) {
            $target = (int) $data['dht22_count'];
            $current = HardwareItem::where('cage_id', $cage->id)
                ->where('device_type', 'DHT22')
                ->whereIn('status', ['active', 'faulty'])
                ->orderByDesc('id')
                ->get();

            if ($target > $current->count()) {
                $needed = $target - $current->count();
                $spareDht = HardwareItem::where('device_type', 'DHT22')->where('status', 'spare')->count();
                if ($needed > $spareDht) {
                    return back()->withInput()->withErrors([
                        'dht22_count' => "Only {$spareDht} DHT22 sensor(s) available in inventory, but {$needed} more requested.",
                    ]);
                }
                for ($i = 0; $i < $needed; $i++) {
                    $this->assignSpareSensor('DHT22', $cage->id, null);
                }
            } elseif ($target < $current->count()) {
                $current->take($current->count() - $target)->each(
                    fn ($item) => $item->update(['status' => 'spare', 'cage_id' => null, 'cage_slot_id' => null])
                );
            }
        }

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} updated.");
    }

    /**
     * Assign the oldest spare sensor of the given type, stamping it with the
     * next globally sequential device ID for that type (IRBBS_n / DHT22_n).
     * IDs are never reused; gaps left by removals are acceptable.
     */
    private function assignSpareSensor(string $deviceType, ?int $cageId, ?int $cageSlotId): ?HardwareItem
    {
        return DB::transaction(function () use ($deviceType, $cageId, $cageSlotId) {
            $item = HardwareItem::where('device_type', $deviceType)
                ->where('status', 'spare')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $item) {
                return null;
            }

            $item->update([
                'serial_number' => $this->nextDeviceId($deviceType),
                'cage_id' => $cageId,
                'cage_slot_id' => $cageSlotId,
                'status' => 'active',
                'installation_date' => now(),
            ]);

            return $item;
        });
    }

    private function nextDeviceId(string $deviceType): string
    {
        $prefix = $deviceType === 'IR_breakbeam' ? 'IRBBS' : $deviceType;

        $max = HardwareItem::where('serial_number', 'like', $prefix.'\_%')
            ->pluck('serial_number')
            ->reduce(function (int $carry, string $serial) use ($prefix) {
                return preg_match('/^'.preg_quote($prefix, '/').'_(\d+)$/', $serial, $m)
                    ? max($carry, (int) $m[1])
                    : $carry;
            }, 0);

        return $prefix.'_'.($max + 1);
    }

    public function destroy(Request $request, Cage $cage)
    {
        $data = $request->validate([
            'hens_action'    => 'required|in:move,delete',
            'return_sensors' => 'nullable|boolean',
        ]);

        $cageCode = $cage->cage_code;

        DB::transaction(function () use ($request, $data, $cage) {
            $slotIds = $cage->cageSlots()->pluck('id');

            if ($data['hens_action'] === 'move') {
                // Unplace hens: they return to the chicken inventory
                Hen::whereIn('cage_slot_id', $slotIds)->update(['cage_slot_id' => null]);
            }
            // 'delete': hens are removed by the slot FK cascade below

            if ($request->boolean('return_sensors', true)) {
                HardwareItem::where(function ($q) use ($slotIds, $cage) {
                    $q->whereIn('cage_slot_id', $slotIds)->orWhere('cage_id', $cage->id);
                })->update(['status' => 'spare', 'cage_id' => null, 'cage_slot_id' => null]);
            }
            // otherwise the FK cascade deletes the assigned hardware items

            $cage->cageSlots()->delete();
            $cage->delete();
        });

        return response()->json(['success' => true, 'message' => "Cage {$cageCode} deleted."]);
    }

    public function deleteInfo(Cage $cage)
    {
        $slotIds = $cage->cageSlots()->pluck('id');

        return response()->json([
            'cage_code'   => $cage->cage_code,
            'hens'        => Hen::whereIn('cage_slot_id', $slotIds)->where('is_active', 1)->count(),
            'sensors'     => HardwareItem::where(function ($q) use ($slotIds, $cage) {
                $q->whereIn('cage_slot_id', $slotIds)->orWhere('cage_id', $cage->id);
            })->whereIn('status', ['active', 'faulty'])->count(),
            'production_logs' => ProductionLog::whereIn('cage_slot_id', $slotIds)->count(),
            'env_logs'    => EnvironmentalLog::where('cage_id', $cage->id)->count(),
            'feed_logs'   => FeedConsumptionLog::where('cage_id', $cage->id)->count(),
            'mortality_logs' => MortalityLog::where('cage_id', $cage->id)->count(),
        ]);
    }

    public function sensorInfo(Cage $cage)
    {
        $dht22 = HardwareItem::where('cage_id', $cage->id)
            ->where('device_type', 'DHT22')
            ->whereIn('status', ['active', 'faulty'])
            ->orderBy('id')
            ->get(['id', 'serial_number', 'status']);

        return response()->json([
            'dht22' => $dht22,
            'spare' => [
                'IR_breakbeam' => HardwareItem::where('device_type', 'IR_breakbeam')->where('status', 'spare')->count(),
                'DHT22'        => HardwareItem::where('device_type', 'DHT22')->where('status', 'spare')->count(),
            ],
        ]);
    }

    public function label(Cage $cage)
    {
        $cage->load(['cageSlots', 'hens' => fn ($q) => $q->where('is_active', 1)]);

        return view('cages.label', ['cage' => $cage]);
    }

    public function updatePosition(Request $request, Cage $cage)
    {
        $data = $request->validate([
            'location_row'    => 'nullable|integer|min:0',
            'location_column' => 'nullable|integer|min:0',
        ]);

        $row = $data['location_row'] ?? null;
        $col = $data['location_column'] ?? null;

        if ($row !== null || $col !== null) {
            $gridRows = (int) Setting::get('farm_grid_rows', 4);
            $gridCols = (int) Setting::get('farm_grid_cols', 4);

            if ($row < 0 || $row >= $gridRows || $col < 0 || $col >= $gridCols) {
                return response()->json(['success' => false, 'message' => 'Position out of bounds'], 422)->header('Content-Type', 'application/json');
            }

            $occupied = Cage::where('id', '!=', $cage->id)
                ->where('location_row', $row)
                ->where('location_column', $col)
                ->exists();

            if ($occupied) {
                return response()->json(['success' => false, 'message' => 'Cell occupied'], 422)->header('Content-Type', 'application/json');
            }
        }

        $cage->update([
            'location_row'    => $row,
            'location_column' => $col,
        ]);

        return response()->json(['success' => true])->header('Content-Type', 'application/json');
    }

    public function batchUpdatePosition(Request $request)
    {
        $data = $request->validate([
            'positions'                   => 'required|array|min:1',
            'positions.*.id'              => 'required|integer|distinct|exists:cages,id',
            'positions.*.location_row'    => 'nullable|integer|min:0',
            'positions.*.location_column' => 'nullable|integer|min:0',
        ]);

        $gridRows = (int) Setting::get('farm_grid_rows', 4);
        $gridCols = (int) Setting::get('farm_grid_cols', 4);

        // Compute the final layout (current positions + requested changes) so
        // occupancy is checked against the whole batch, not one cage at a time.
        $final = Cage::query()->get(['id', 'location_row', 'location_column'])
            ->keyBy('id')
            ->map(fn ($c) => ['row' => $c->location_row, 'col' => $c->location_column]);

        foreach ($data['positions'] as $entry) {
            $row = $entry['location_row'] ?? null;
            $col = $entry['location_column'] ?? null;

            if (($row === null) !== ($col === null)) {
                return response()->json(['success' => false, 'message' => 'Row and column must both be set or both be empty'], 422);
            }

            if ($row !== null && ($row >= $gridRows || $col >= $gridCols)) {
                return response()->json(['success' => false, 'message' => 'Position out of bounds'], 422);
            }

            $final[$entry['id']] = ['row' => $row, 'col' => $col];
        }

        $seen = [];
        foreach ($final as $pos) {
            if ($pos['row'] === null) {
                continue;
            }
            $key = $pos['row'].'-'.$pos['col'];
            if (isset($seen[$key])) {
                return response()->json(['success' => false, 'message' => 'Two cages assigned to the same cell'], 422);
            }
            $seen[$key] = true;
        }

        DB::transaction(function () use ($data) {
            // Two-phase apply: vacate every moved cage first so swaps can't
            // transiently collide on the (location_row, location_column) unique index.
            $ids = array_column($data['positions'], 'id');
            Cage::whereIn('id', $ids)->update(['location_row' => null, 'location_column' => null]);

            foreach ($data['positions'] as $entry) {
                Cage::whereKey($entry['id'])->update([
                    'location_row'    => $entry['location_row'] ?? null,
                    'location_column' => $entry['location_column'] ?? null,
                ]);
            }
        });

        return response()->json(['success' => true]);
    }

    public function slotsJson(Cage $cage)
    {
        return response()->json($cage->cageSlots->load('hardwareItems')->map(fn ($s) => [
            'id' => $s->id,
            'slot_number' => $s->slot_number,
            'row_number' => $s->row_number,
            'column_number' => $s->column_number,
            'current_occupancy' => $s->current_occupancy,
            'has_sensor' => $s->hasBreakbeam(),
            'sensor_device_id' => optional($s->hardwareItems->where('device_type', 'IR_breakbeam')->where('status', 'active')->first())?->serial_number ?? '',
        ]));
    }

    public function hensJson(CageSlot $slot)
    {
        $hens = $slot->hens()->where('is_active', 1)->get()->map(fn ($h) => [
            'id' => $h->id,
            'tag_code' => $h->tag_code,
            'breed' => $h->breed,
            'current_age_weeks' => $h->current_age_weeks,
            'flock_age_weeks' => $h->flock_age_weeks,
            'is_active' => $h->is_active,
        ]);

        // Today's egg status for this slot (item 16)
        $todayLog = ProductionLog::where('cage_slot_id', $slot->id)
            ->whereDate('log_date', today())
            ->first();
        $occupancy = (int) $slot->current_occupancy;
        if ($todayLog === null) {
            $eggStatus = 'No eggs logged today';
        } elseif ($occupancy > 0 && $todayLog->egg_count >= $occupancy) {
            $eggStatus = "All laid — {$todayLog->egg_count} egg(s) collected today";
        } else {
            $eggStatus = "{$todayLog->egg_count} of {$occupancy} eggs collected today";
        }

        // Notes tagged to this cage (item 16)
        $notes = Note::where('cage_id', $slot->cage_id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($n) => [
                'body' => $n->body,
                'created_at' => $n->created_at->format('M j, g:i A'),
            ]);

        return response()->json([
            'slot' => [
                'id' => $slot->id,
                'slot_number' => $slot->slot_number,
                'row_number' => $slot->row_number,
                'column_number' => $slot->column_number,
                'current_occupancy' => $slot->current_occupancy,
                'has_sensor' => $slot->hasBreakbeam(),
                'remaining' => $slot->remaining,
                'egg_status' => $eggStatus,
            ],
            'hens' => $hens,
            'notes' => $notes,
            'cage' => [
                'id' => $slot->cage->id,
                'cage_code' => $slot->cage->cage_code,
                'max_chickens_per_slot' => $slot->cage->max_chickens_per_slot,
            ],
        ]);
    }

    public function deleteConfirm(Cage $cage)
    {
        $slotCount = $cage->cageSlots()->count();
        $sensorSlotCount = $cage->cageSlots()->whereHas('hardwareItems', fn ($q) => $q->where('device_type', 'IR_breakbeam')->where('status', 'active'))->count();
        $henCount = $cage->hens()->count();
        $productionLogCount = ProductionLog::whereIn('cage_slot_id', $cage->cageSlots()->pluck('id'))->count();
        $envLogCount = EnvironmentalLog::where('cage_id', $cage->id)->count();
        $feedLogCount = FeedConsumptionLog::where('cage_id', $cage->id)->count();
        $mortalityCount = MortalityLog::where('cage_id', $cage->id)->count();
        $alertCount = Alert::where('cage_id', $cage->id)->count();

        return view('cages.confirm-delete', compact(
            'cage', 'slotCount', 'sensorSlotCount', 'henCount',
            'productionLogCount', 'envLogCount', 'feedLogCount', 'mortalityCount', 'alertCount'
        ));
    }

    public function forceDestroy(Cage $cage)
    {
        $cage->cageSlots()->delete();
        $cage->delete();

        return redirect()->route('cages.index')->with('success', 'Cage and all associated slots deleted.');
    }

    private function checkResizeSafety(Cage $cage, array $data): ?array
    {
        $newRows = $data['rows'] ?? $cage->rows;
        $newSlots = $data['slots_per_row'] ?? $cage->slots_per_row;
        $newMax = $data['max_chickens_per_slot'] ?? $cage->max_chickens_per_slot;

        $currentMaxSlot = $cage->rows * $cage->slots_per_row;
        $newMaxSlot = $newRows * $newSlots;

        if ($newMaxSlot >= $currentMaxSlot) {
            return null;
        }

        $orphanedSlots = $cage->cageSlots()
            ->where('slot_number', '>', $newMaxSlot)
            ->get();

        $issues = [];

        $occupiedOrphaned = $orphanedSlots->where('current_occupancy', '>', 0)->count();
        if ($occupiedOrphaned > 0) {
            $issues[] = "{$occupiedOrphaned} slot(s) have hens and would be removed.";
        }

        $sensorOrphaned = $orphanedSlots->load('hardwareItems')->filter(fn ($s) => $s->hasBreakbeam())->count();
        if ($sensorOrphaned > 0) {
            $issues[] = "{$sensorOrphaned} sensor-equipped slot(s) would be removed.";
        }

        if ($newMax < $cage->max_chickens_per_slot) {
            $overCapacitySlots = $cage->cageSlots()
                ->where('current_occupancy', '>', $newMax)
                ->where('slot_number', '<=', $newMaxSlot)
                ->count();
            if ($overCapacitySlots > 0) {
                $issues[] = "{$overCapacitySlots} slot(s) have more hens than the new max of {$newMax} per slot.";
            }
        }

        if (! empty($issues)) {
            $errors = ['resize' => 'Cannot shrink grid — '.implode(' ', $issues).' Adjust the grid dimensions first.'];

            return $errors;
        }

        return null;
    }

    private function resizeSlots(Cage $cage, int $newRows, int $newSlots, int $newMax): void
    {
        $newTotal = $newRows * $newSlots;
        $currentTotal = $cage->rows * $cage->slots_per_row;

        $cage->cageSlots()->where('slot_number', '>', $newTotal)->delete();

        for ($s = 1; $s <= $newTotal; $s++) {
            $row = (int) ceil($s / $newSlots);
            $col = $s - ($row - 1) * $newSlots;

            $slot = $cage->cageSlots()->where('slot_number', $s)->first();
            if (! $slot) {
                $slot = CageSlot::create([
                    'cage_id' => $cage->id,
                    'slot_number' => $s,
                    'row_number' => $row,
                    'column_number' => $col,
                    'current_occupancy' => 0,
                ]);
            }

            if ($slot->current_occupancy > $newMax) {
                $slot->update(['current_occupancy' => $newMax]);
            }
        }
    }

    public function bulkAdd(Request $request)
    {
        $cages = Cage::with(['cageSlots', 'hens' => fn ($q) => $q->where('is_active', 1)])
            ->where('is_active', 1)
            ->orderBy('cage_code')
            ->get();

        $selectedCage = null;
        if ($request->has('cage_id')) {
            $selectedCage = $cages->firstWhere('id', $request->cage_id);
        }

        // Chicken inventory: unplaced active hens, available per breed (item 22)
        $availableByBreed = Hen::whereNull('cage_slot_id')
            ->where('is_active', 1)
            ->selectRaw('breed, COUNT(*) as c')
            ->groupBy('breed')
            ->pluck('c', 'breed');

        return view('cages.bulk-add', compact('cages', 'selectedCage', 'availableByBreed'));
    }

    public function storeBulkAdd(Request $request)
    {
        $data = $request->validate([
            'cage_id' => 'required|integer|exists:cages,id',
            'breed' => 'required|string|max:50',
            'chickens_per_slot' => 'required|integer|min:1|max:10',
            'slot_ids' => 'required|string',
        ]);

        $cage = Cage::with('cageSlots')->findOrFail($data['cage_id']);
        $slotIds = array_filter(array_map('intval', explode(',', $data['slot_ids'])));

        if (empty($slotIds)) {
            return back()->withErrors(['slot_ids' => 'Please select at least one slot.']);
        }

        // How many placements are requested across the selected slots
        $needed = 0;
        $slotPlan = [];
        foreach ($slotIds as $slotId) {
            $slot = $cage->cageSlots->firstWhere('id', $slotId);
            if (! $slot) {
                continue;
            }
            $remaining = $cage->max_chickens_per_slot - $slot->current_occupancy;
            if ($remaining <= 0) {
                continue;
            }
            $toAdd = min((int) $data['chickens_per_slot'], $remaining);
            $slotPlan[] = ['slot' => $slot, 'count' => $toAdd];
            $needed += $toAdd;
        }

        if ($needed === 0) {
            return back()->withErrors(['slot_ids' => 'The selected slots have no remaining capacity.']);
        }

        // Draw from the chicken inventory (unplaced active hens of this breed)
        $available = Hen::whereNull('cage_slot_id')
            ->where('is_active', 1)
            ->where('breed', $data['breed'])
            ->orderBy('id')
            ->limit($needed)
            ->get();

        if ($available->count() < $needed) {
            return back()->withInput()->withErrors([
                'breed' => "Only {$available->count()} unplaced {$data['breed']} hen(s) available in the chicken inventory, but {$needed} needed for the selected slots.",
            ]);
        }

        $placementDate = now()->toDateString();
        $placed = 0;
        $pool = $available->values();

        DB::transaction(function () use ($slotPlan, $pool, $placementDate, &$placed) {
            $i = 0;
            foreach ($slotPlan as $entry) {
                for ($n = 0; $n < $entry['count']; $n++) {
                    $hen = $pool[$i++];
                    $hen->update([
                        'cage_slot_id' => $entry['slot']->id,
                        'placement_date' => $placementDate,
                    ]);
                    $placed++;
                }
                $entry['slot']->update([
                    'current_occupancy' => $entry['slot']->current_occupancy + $entry['count'],
                ]);
            }
        });

        return redirect()->route('cages.index')
            ->with('success', "{$placed} hen(s) placed in {$cage->cage_code} from inventory.");
    }

    private function createSlotsForCage(Cage $cage): void
    {
        for ($s = 1; $s <= $cage->rows * $cage->slots_per_row; $s++) {
            $row = (int) ceil($s / $cage->slots_per_row);
            $col = $s - ($row - 1) * $cage->slots_per_row;
            CageSlot::create([
                'cage_id' => $cage->id,
                'slot_number' => $s,
                'row_number' => $row,
                'column_number' => $col,
                'current_occupancy' => 0,
            ]);
        }
    }

    private function generateCageCode(): string
    {
        $existingCodes = Cage::orderBy('id')->pluck('cage_code')->toArray();
        $maxNumber = 0;

        foreach ($existingCodes as $code) {
            if (preg_match('/^CAGE-([A-Z]+)$/i', $code, $matches)) {
                $num = $this->letterToNumber($matches[1]);
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        $nextNumber = $maxNumber + 1;
        $letters = $this->numberToLetter($nextNumber);

        return 'CAGE-'.$letters;
    }

    private function letterToNumber(string $letters): int
    {
        $number = 0;
        $length = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $number = $number * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return $number;
    }

    private function numberToLetter(int $number): string
    {
        $result = '';
        while ($number > 0) {
            $number--;
            $result = chr(($number % 26) + ord('A')).$result;
            $number = intdiv($number, 26);
        }

        return $result;
    }
}
