<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use App\Models\ProductionLog;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\Alert;
use App\Models\Forecast;
use App\Models\MortalityLog;
use Illuminate\Http\Request;

class CageController extends Controller
{
    public function index()
    {
        $cages = Cage::with([
            'cageSlots',
            'hens' => fn($q) => $q->where('is_active', 1)->orderBy('id'),
        ])->orderBy('cage_code')->get();

        return view('cages.index', compact('cages'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cage_code'               => 'required|string|max:50|unique:cages',
            'location'                => 'nullable|string|max:100',
            'rows'                   => 'required|integer|min:1|max:10',
            'slots_per_row'          => 'required|integer|min:1|max:10',
            'max_chickens_per_slot'  => 'required|integer|min:1|max:10',
        ]);

        $totalCapacity = (int) $data['rows'] * (int) $data['slots_per_row'] * (int) $data['max_chickens_per_slot'];

        $cage = Cage::create([
            'cage_code'              => strtoupper($data['cage_code']),
            'location'               => $data['location'] ?? '',
            'rows'                   => $data['rows'],
            'slots_per_row'          => $data['slots_per_row'],
            'max_chickens_per_slot'  => $data['max_chickens_per_slot'],
            'total_capacity'         => $totalCapacity,
            'is_active'              => 1,
        ]);

        $this->createSlotsForCage($cage);

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} added with {$cage->rows}×{$cage->slots_per_row} = {$cage->total_capacity} capacity.");
    }

    public function update(Request $request, Cage $cage)
    {
        $data = $request->validate([
            'location'               => 'nullable|string|max:100',
            'rows'                  => 'nullable|integer|min:1|max:10',
            'slots_per_row'         => 'nullable|integer|min:1|max:10',
            'max_chickens_per_slot' => 'nullable|integer|min:1|max:10',
            'is_active'             => 'nullable|boolean',
        ]);

        if ($cage->is_active && isset($data['is_active']) && !$data['is_active']) {
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

        $updateData = array_intersect_key($data, array_flip(['location', 'rows', 'slots_per_row', 'max_chickens_per_slot', 'is_active']));

        if (isset($updateData['rows']) || isset($updateData['slots_per_row']) || isset($updateData['max_chickens_per_slot'])) {
            $newRows    = $updateData['rows']                   ?? $cage->rows;
            $newSlots   = $updateData['slots_per_row']          ?? $cage->slots_per_row;
            $newMax     = $updateData['max_chickens_per_slot']  ?? $cage->max_chickens_per_slot;
            $updateData['total_capacity'] = $newRows * $newSlots * $newMax;
            $this->resizeSlots($cage, $newRows, $newSlots, $newMax);
        }

        $cage->update($updateData);

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} updated.");
    }

    public function destroy(Cage $cage)
    {
        return redirect()->route('cages.index');
    }

    public function slotsJson(Cage $cage)
    {
        return response()->json($cage->cageSlots->map(fn($s) => [
            'id' => $s->id,
            'slot_number' => $s->slot_number,
            'row_number' => $s->row_number,
            'column_number' => $s->column_number,
            'current_occupancy' => $s->current_occupancy,
            'has_sensor' => $s->has_sensor,
        ]));
    }

    public function toggleSensor(Cage $cage, CageSlot $slot)
    {
        if ($slot->cage_id !== $cage->id) {
            abort(404);
        }

        $slot->update(['has_sensor' => !$slot->has_sensor]);

        return back();
    }

    public function hensJson(CageSlot $slot)
    {
        $hens = $slot->hens()->where('is_active', 1)->get()->map(fn($h) => [
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
                'has_sensor' => $slot->has_sensor,
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
        $sensorSlotCount = $cage->cageSlots()->where('has_sensor', true)->count();
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
        $newRows  = $data['rows']  ?? $cage->rows;
        $newSlots = $data['slots_per_row'] ?? $cage->slots_per_row;
        $newMax   = $data['max_chickens_per_slot'] ?? $cage->max_chickens_per_slot;

        $currentMaxSlot = $cage->rows * $cage->slots_per_row;
        $newMaxSlot    = $newRows * $newSlots;

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

        $sensorOrphaned = $orphanedSlots->where('has_sensor', true)->count();
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

        if (!empty($issues)) {
            $errors = ['resize' => 'Cannot shrink grid — ' . implode(' ', $issues) . ' Adjust the grid dimensions first.'];
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
            if (!$slot) {
                $slot = CageSlot::create([
                    'cage_id' => $cage->id,
                    'slot_number' => $s,
                    'row_number' => $row,
                    'column_number' => $col,
                    'current_occupancy' => 0,
                    'has_sensor' => false,
                    'sensor_device_id' => null,
                ]);
            }

            if ($slot->current_occupancy > $newMax) {
                $slot->update(['current_occupancy' => $newMax]);
            }
        }
    }

    public function bulkAdd(Request $request)
    {
        $cages = Cage::with(['cageSlots', 'hens' => fn($q) => $q->where('is_active', 1)])
            ->where('is_active', 1)
            ->orderBy('cage_code')
            ->get();

        $selectedCage = null;
        if ($request->has('cage_id')) {
            $selectedCage = $cages->firstWhere('id', $request->cage_id);
        }

        return view('cages.bulk-add', compact('cages', 'selectedCage'));
    }

    public function storeBulkAdd(Request $request)
    {
        $data = $request->validate([
            'cage_id'            => 'required|integer|exists:cages,id',
            'breed'              => 'required|string|max:50',
            'age_weeks'          => 'required|integer|min:0|max:200',
            'chickens_per_slot'  => 'required|integer|min:1|max:10',
            'slot_ids'           => 'required|string',
        ]);

        $cage = Cage::with('cageSlots')->findOrFail($data['cage_id']);
        $slotIds = array_filter(array_map('intval', explode(',', $data['slot_ids'])));

        if (empty($slotIds)) {
            return back()->withErrors(['slot_ids' => 'Please select at least one slot.']);
        }

        $now = now();
        $placementDate = $now->toDateString();

        $created = 0;
        foreach ($slotIds as $slotId) {
            $slot = $cage->cageSlots->firstWhere('id', $slotId);
            if (!$slot) {
                continue;
            }

            $remaining = $cage->max_chickens_per_slot - $slot->current_occupancy;
            if ($remaining <= 0) {
                continue;
            }

            $toAdd = min((int) $data['chickens_per_slot'], $remaining);

            for ($i = 0; $i < $toAdd; $i++) {
                Hen::create([
                    'cage_slot_id'           => $slot->id,
                    'tag_code'               => null,
                    'date_acquired'          => $now->toDateTimeString(),
                    'flock_age_weeks'        => $data['age_weeks'],
                    'placement_date'         => $placementDate,
                    'age_at_placement_weeks' => $data['age_weeks'],
                    'breed'                  => $data['breed'],
                    'is_active'              => true,
                ]);
                $created++;
            }

            $slot->update(['current_occupancy' => $slot->current_occupancy + $toAdd]);
        }

        return redirect()->route('cages.index')
            ->with('success', "{$created} hen(s) added to {$cage->cage_code}.");
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
                'has_sensor' => false,
                'sensor_device_id' => null,
            ]);
        }
    }
}
