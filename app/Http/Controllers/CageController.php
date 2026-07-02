<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\CageTransfer;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\Hen;
use App\Models\MortalityLog;
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

        return view('cages.index', compact('cages', 'nextCageCode', 'gridRows', 'gridCols'));
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
            'slots.*.sensor_device_id' => 'nullable|string|max:50',
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

        if (isset($data['slots']) && is_array($data['slots'])) {
            foreach ($data['slots'] as $slotId => $slotData) {
                $slot = $cage->cageSlots()->find($slotId);
                if ($slot) {
                    $hasSensor = (bool) ($slotData['has_sensor'] ?? false);
                    $deviceId = ! empty($slotData['sensor_device_id']) ? $slotData['sensor_device_id'] : null;
                    if ($hasSensor && $deviceId) {
                        HardwareItem::updateOrCreate(
                            ['cage_slot_id' => $slot->id, 'device_type' => 'IR_breakbeam'],
                            ['serial_number' => $deviceId, 'cage_id' => null, 'status' => 'active', 'installation_date' => now()]
                        );
                    } elseif (! $hasSensor) {
                        HardwareItem::where('cage_slot_id', $slot->id)
                            ->where('device_type', 'IR_breakbeam')
                            ->update(['status' => 'removed']);
                    }
                }
            }
        }

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} updated.");
    }

    public function destroy(Cage $cage)
    {
        return redirect()->route('cages.index');
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

        return response()->json([
            'slot' => [
                'id' => $slot->id,
                'slot_number' => $slot->slot_number,
                'row_number' => $slot->row_number,
                'column_number' => $slot->column_number,
                'current_occupancy' => $slot->current_occupancy,
                'has_sensor' => $slot->hasBreakbeam(),
                'remaining' => $slot->remaining,
            ],
            'hens' => $hens,
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
        $cages = Cage::with(['cageSlots'])
            ->where('is_active', 1)
            ->orderBy('cage_code')
            ->get();

        $selectedCage = null;
        if ($request->has('cage_id')) {
            $selectedCage = $cages->firstWhere('id', $request->cage_id);
        }

        // Fetch unplaced hens live from inventory
        $unplacedHens = Hen::whereNull('cage_slot_id')
            ->where('is_active', 1)
            ->orderBy('id')
            ->get();

        $unplacedBreeds = $unplacedHens->pluck('breed')->unique()->sort()->values();

        // Pre-selected hen IDs (deep-link from Chickens Inventory)
        $preselectedIds = [];
        if ($request->has('hen_ids')) {
            $preselectedIds = array_filter(array_map('intval', explode(',', $request->hen_ids)));
            $preselectedIds = $unplacedHens->whereIn('id', $preselectedIds)->pluck('id')->toArray();
        }

        return view('cages.bulk-add', compact('cages', 'selectedCage', 'unplacedHens', 'unplacedBreeds', 'preselectedIds'));
    }

    public function storeBulkAdd(Request $request)
    {
        $data = $request->validate([
            'hen_ids'            => 'required|string',
            'cage_id'            => 'required|integer|exists:cages,id',
            'mode'               => 'required|in:manual,auto',
            'slot_ids'           => 'required_if:mode,manual|string|nullable',
            'chickens_per_slot'  => 'required_if:mode,auto|integer|min:1|max:10|nullable',
        ]);

        $henIds = array_filter(array_map('intval', explode(',', $data['hen_ids'])));
        $henIds = array_unique($henIds);

        if (empty($henIds)) {
            return back()->withErrors(['hen_ids' => 'No hens selected.']);
        }

        // Only unplaced, active hens can be placed
        $hens = Hen::whereIn('id', $henIds)
            ->whereNull('cage_slot_id')
            ->where('is_active', 1)
            ->get();

        if ($hens->isEmpty()) {
            return back()->withErrors(['hen_ids' => 'No valid unplaced hens found for the selected IDs.']);
        }

        $cage = Cage::with('cageSlots')->findOrFail($data['cage_id']);
        $toPlace = $hens->count();

        if ($data['mode'] === 'manual') {
            $slotIds = array_filter(array_map('intval', explode(',', $data['slot_ids'] ?? '')));
            $slotIds = array_unique($slotIds);

            if (empty($slotIds)) {
                return back()->withErrors(['slot_ids' => 'Please select at least one slot.']);
            }

            // Get selected slots that belong to this cage
            $slots = $cage->cageSlots->whereIn('id', $slotIds);
            if ($slots->isEmpty()) {
                return back()->withErrors(['slot_ids' => 'No valid slots found for this cage.']);
            }

            // Validate total capacity
            $totalRemaining = $slots->sum(fn($s) => $s->remaining);
            if ($totalRemaining < $toPlace) {
                return back()->withErrors([
                    'slot_ids' => "Selected slots have {$totalRemaining} space(s) available, but {$toPlace} hens need placement.",
                ]);
            }

            $placed = 0;
            DB::transaction(function () use ($hens, $slots, $cage, &$placed) {
                $henIter = $hens->values();
                $idx = 0;
                foreach ($slots as $slot) {
                    $capacity = $slot->remaining;
                    for ($i = 0; $i < $capacity && $idx < $henIter->count(); $i++) {
                        $hen = $henIter[$idx];
                        $hen->update(['cage_slot_id' => $slot->id]);
                        $slot->increment('current_occupancy');
                        CageTransfer::create([
                            'hen_id'           => $hen->id,
                            'from_cage_slot_id' => null,
                            'to_cage_slot_id'   => $slot->id,
                            'transfer_date'    => today(),
                            'reason'           => 'Initial placement',
                            'recorded_by'      => auth()->id(),
                        ]);
                        $placed++;
                        $idx++;
                    }
                }
            });

            return redirect()->route('cages.index')
                ->with('success', "{$placed} hen(s) placed into {$cage->cage_code}.");
        }

        // Auto mode: distribute evenly across all available slots in the cage
        $perSlot = (int) $data['chickens_per_slot'];

        $availableSlots = $cage->cageSlots->filter(fn($s) => $s->remaining > 0);
        if ($availableSlots->isEmpty()) {
            return back()->withErrors(['cage_id' => 'No available slots in this cage.']);
        }

        $totalRemaining = $availableSlots->sum(fn($s) => $s->remaining);
        if ($totalRemaining < $toPlace) {
            return back()->withErrors([
                'cage_id' => "Cage {$cage->cage_code} has {$totalRemaining} space(s) available, but {$toPlace} hens need placement.",
            ]);
        }

        $placed = 0;
        DB::transaction(function () use ($hens, $availableSlots, $perSlot, $cage, &$placed) {
            $henIter = $hens->values();
            $idx = 0;
            foreach ($availableSlots as $slot) {
                $capacity = min($perSlot, $slot->remaining);
                for ($i = 0; $i < $capacity && $idx < $henIter->count(); $i++) {
                    $hen = $henIter[$idx];
                    $hen->update(['cage_slot_id' => $slot->id]);
                    $slot->increment('current_occupancy');
                    CageTransfer::create([
                        'hen_id'           => $hen->id,
                        'from_cage_slot_id' => null,
                        'to_cage_slot_id'   => $slot->id,
                        'transfer_date'    => today(),
                        'reason'           => 'Initial placement (auto-distribute)',
                        'recorded_by'      => auth()->id(),
                    ]);
                    $placed++;
                    $idx++;
                }
            }
        });

        return redirect()->route('cages.index')
            ->with('success', "{$placed} hen(s) placed into {$cage->cage_code}.");
    }

    public function printLabel(Cage $cage)
    {
        $cage->load(['cageSlots', 'hens' => fn ($q) => $q->where('is_active', 1)->orderBy('id')]);

        $hensBySlot = $cage->hens->groupBy('cage_slot_id');

        return view('cages.print-label', compact('cage', 'hensBySlot'));
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
