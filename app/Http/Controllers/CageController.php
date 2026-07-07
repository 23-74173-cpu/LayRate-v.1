<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\CageTransfer;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\MortalityLog;
use App\Models\Note;
use App\Models\ProductionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CageController extends Controller
{
    public function index()
    {
        $cages = Cage::with(['slots' => fn($q) => $q->orderBy('slot_number')])
            ->orderBy('cage_code')
            ->get()
            ->each(function ($cage) {
                $slotIds = $cage->slots->pluck('id');
                $cage->has_history = ProductionLog::whereIn('cage_slot_id', $slotIds)->exists()
                    || EnvironmentalLog::where('cage_id', $cage->id)->exists()
                    || FeedConsumptionLog::where('cage_id', $cage->id)->exists()
                    || MortalityLog::where('cage_id', $cage->id)->exists()
                    || Alert::where('cage_id', $cage->id)->exists();
            });

        return view('cages.index', compact('cages'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cage_code'             => 'required|string|max:50|unique:cages',
            'location'              => 'nullable|string|max:100',
            'rows'                  => 'required|integer|min:1|max:26',
            'slots_per_row'         => 'required|integer|min:1|max:50',
            'max_chickens_per_slot' => 'required|integer|min:1|max:20',
        ]);

        $cage = Cage::create([
            'cage_code'             => strtoupper($data['cage_code']),
            'location'              => $data['location'] ?? '',
            'rows'                  => $data['rows'],
            'slots_per_row'         => $data['slots_per_row'],
            'max_chickens_per_slot' => $data['max_chickens_per_slot'],
            'is_active'             => 1,
        ]);

        $slotNumber = 1;
        for ($row = 1; $row <= $cage->rows; $row++) {
            for ($col = 1; $col <= $cage->slots_per_row; $col++) {
                CageSlot::create([
                    'cage_id'       => $cage->id,
                    'row_number'    => $row,
                    'column_number' => $col,
                    'slot_number'   => $slotNumber++,
                ]);
            }
        }

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} created with {$cage->slots()->count()} slots. Add chickens to it from the cage's \"Bulk Add Chickens\" button below.");
    }

    public function update(Request $request, Cage $cage)
    {
        $data = $request->validate([
            'cage_code'             => 'required|string|max:50|unique:cages,cage_code,' . $cage->id,
            'location'              => 'nullable|string|max:100',
            'rows'                  => 'required|integer|min:1|max:26',
            'slots_per_row'         => 'required|integer|min:1|max:50',
            'max_chickens_per_slot' => 'required|integer|min:1|max:20',
            'is_active'             => 'nullable|boolean',
        ]);

        $newRows         = $data['rows'];
        $newSlotsPerRow  = $data['slots_per_row'];
        $newMaxPerSlot   = $data['max_chickens_per_slot'];

        $slotsToRemove = $cage->slots()
            ->where(function ($q) use ($newRows, $newSlotsPerRow) {
                $q->where('row_number', '>', $newRows)
                  ->orWhere('column_number', '>', $newSlotsPerRow);
            })
            ->get();

        $blockedRemove = $slotsToRemove->filter(fn($s) => $s->current_occupancy > 0 || $s->has_sensor);

        if ($blockedRemove->isNotEmpty()) {
            $names = $blockedRemove->map(fn($s) => "{$s->label} ({$s->current_occupancy} chickens" . ($s->has_sensor ? ', sensor' : '') . ')')->implode(', ');
            return back()->withInput()->withErrors([
                'rows' => "Cannot shrink grid — slot(s) {$names} have chickens or a sensor. Remove or reassign them first.",
            ]);
        }

        if ($newMaxPerSlot < $cage->max_chickens_per_slot) {
            $overCapacity = $cage->slots()->where('current_occupancy', '>', $newMaxPerSlot)->get();
            if ($overCapacity->isNotEmpty()) {
                $names = $overCapacity->map(fn($s) => "{$s->label} ({$s->current_occupancy} chickens)")->implode(', ');
                return back()->withInput()->withErrors([
                    'max_chickens_per_slot' => "Cannot lower max per slot — slot(s) {$names} already exceed that count.",
                ]);
            }
        }

        $cage->slots()
            ->where(function ($q) use ($newRows, $newSlotsPerRow) {
                $q->where('row_number', '>', $newRows)
                  ->orWhere('column_number', '>', $newSlotsPerRow);
            })
            ->delete();

        $existingPositions = $cage->slots()->get()->map(fn($s) => "{$s->row_number}-{$s->column_number}")->flip();
        for ($row = 1; $row <= $newRows; $row++) {
            for ($col = 1; $col <= $newSlotsPerRow; $col++) {
                if (! isset($existingPositions["{$row}-{$col}"])) {
                    CageSlot::create(['cage_id' => $cage->id, 'row_number' => $row, 'column_number' => $col, 'slot_number' => 0]);
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

        // Renumber every remaining slot sequentially (row-major) so slot_number stays consistent after any resize.
        $n = 1;
        foreach ($cage->slots()->orderBy('row_number')->orderBy('column_number')->get() as $slot) {
            $slot->update(['slot_number' => $n++]);
        }

        $cage->update([
            'cage_code'             => strtoupper($data['cage_code']),
            'location'              => $data['location'] ?? '',
            'rows'                  => $newRows,
            'slots_per_row'         => $newSlotsPerRow,
            'max_chickens_per_slot' => $newMaxPerSlot,
            'is_active'             => $request->boolean('is_active'),
        ]);

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} updated.");
    }

    public function destroy(Request $request, Cage $cage)
    {
        $hardBlocked = $cage->slots()
            ->where(fn($q) => $q->where('current_occupancy', '>', 0)->orWhere('has_sensor', true))
            ->exists();

        if ($hardBlocked) {
            return back()->withErrors([
                'cage' => "Cannot delete {$cage->cage_code} — it has occupied or sensor-equipped slots. Clear them first.",
            ]);
        }

        $slotIds = $cage->slots()->pluck('id');
        $hasHistory = ProductionLog::whereIn('cage_slot_id', $slotIds)->exists()
            || EnvironmentalLog::where('cage_id', $cage->id)->exists()
            || FeedConsumptionLog::where('cage_id', $cage->id)->exists()
            || MortalityLog::where('cage_id', $cage->id)->exists()
            || Alert::where('cage_id', $cage->id)->exists();

        if ($hasHistory && $request->input('confirm_code') !== $cage->cage_code) {
            return back()->withErrors([
                'cage' => "{$cage->cage_code} has historical records. Type its exact code to confirm deletion.",
            ]);
        }

        $cage->delete();

        return redirect()->route('cages.index')->with('success', 'Cage deleted.');
    }

    private function placeHenInSlot(Hen $hen, CageSlot $slot, string $reason): void
    {
        $hen->cage_slot_id = $slot->id;
        $hen->placement_date = today();
        $hen->save();
        $slot->increment('current_occupancy');
        CageTransfer::create([
            'hen_id'           => $hen->id,
            'from_cage_slot_id' => null,
            'to_cage_slot_id'   => $slot->id,
            'transfer_date'    => today(),
            'reason'           => $reason,
            'recorded_by'      => auth()->id(),
        ]);
    }

    public function removeCell(Request $request): JsonResponse
    {
        $data = $request->validate([
            'row' => 'required|integer|min:0',
            'col' => 'required|integer|min:0',
        ]);

        $row = (int) $data['row'];
        $col = (int) $data['col'];

        $cage = Cage::where('location_row', $row)
            ->where('location_column', $col)
            ->first();

        if ($cage) {
            $cage->update(['location_row' => null, 'location_column' => null]);
            return response()->json(['success' => true]);
        }

        // Empty cell — attempt grid shrink
        $gridRows = (int) Setting::get('farm_grid_rows', 4);
        $gridCols = (int) Setting::get('farm_grid_cols', 4);

        $isEdge = ($col === $gridCols - 1 || $row === $gridRows - 1);
        if (!$isEdge) {
            return response()->json([
                'success' => false,
                'message' => 'Only empty cells at the grid\'s edge (last row or last column) can be removed, since removing one from the middle would require repositioning other cages.',
            ], 422);
        }

        if ($col === $gridCols - 1) {
            $lastColOccupied = Cage::where('location_column', $gridCols - 1)->whereNotNull('location_row')->exists();
            if ($lastColOccupied) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot shrink the grid — the last column still contains cages. Move or remove them first.',
                ], 422);
            }
            Setting::set('farm_grid_cols', max(1, $gridCols - 1));
            $gridCols = max(1, $gridCols - 1);
        }

        if ($row === $gridRows - 1) {
            $lastRowOccupied = Cage::where('location_row', $gridRows - 1)->whereNotNull('location_row')->exists();
            if ($lastRowOccupied) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot shrink the grid — the last row still contains cages. Move or remove them first.',
                ], 422);
            }
            Setting::set('farm_grid_rows', max(1, $gridRows - 1));
        }

        return response()->json(['success' => true]);
    }
}
