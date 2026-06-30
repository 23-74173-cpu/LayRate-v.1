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

    public function toggleSensor(Cage $cage, CageSlot $slot)
    {
        if ($slot->cage_id !== $cage->id) {
            abort(404);
        }

        $slot->update(['has_sensor' => !$slot->has_sensor]);

        return back();
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
