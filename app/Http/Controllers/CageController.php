<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use App\Models\ProductionLog;
use Illuminate\Http\Request;

class CageController extends Controller
{
    public function index()
    {
        $cages = Cage::with(['slots' => fn($q) => $q->orderBy('slot_number')])
            ->orderBy('cage_code')
            ->get();

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
            'location'               => 'nullable|string|max:100',
            'capacity'               => 'nullable|integer|min:1',
            'is_active'              => 'nullable|boolean',
            'has_sensor'             => 'nullable|boolean',
            'sensor_device_id'       => 'nullable|string|max:100',
            'breed'                  => 'nullable|string',
            'age_at_placement_weeks' => 'nullable|integer|min:0',
        ]);

        $cage->update(array_intersect_key($data, array_flip(['location', 'capacity', 'is_active', 'has_sensor', 'sensor_device_id'])));

        if ($request->filled('breed')) {
            $hen = Hen::firstOrNew(['cage_id' => $cage->id, 'is_active' => 1]);
            if (! $hen->exists) {
                $hen->placement_date = now();
            }
            $hen->cage_id                 = $cage->id;
            $hen->is_active                = 1;
            $hen->breed                    = $data['breed'];
            $hen->age_at_placement_weeks   = $data['age_at_placement_weeks'] ?? 0;
            $hen->save();
        }

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} updated.");
    }

    public function destroy(Cage $cage)
    {
        $cage->delete();
        return redirect()->route('cages.index')->with('success', 'Cage deleted.');
    }
}
