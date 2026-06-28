<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\Hen;
use App\Models\ProductionLog;
use Illuminate\Http\Request;

class CageController extends Controller
{
    public function index()
    {
        $cages = Cage::with([
            'latestProduction',
            'hens' => fn($q) => $q->where('is_active', 1)->orderBy('id'),
        ])->orderBy('cage_code')->get();

        return view('cages.index', compact('cages'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cage_code'              => 'required|string|max:50|unique:cages',
            'location'               => 'nullable|string|max:100',
            'capacity'               => 'nullable|integer|min:1',
            'breed'                  => 'nullable|string',
            'age_at_placement_weeks' => 'nullable|integer|min:0',
        ]);

        $cage = Cage::create([
            'cage_code' => strtoupper($data['cage_code']),
            'location'  => $data['location'] ?? '',
            'capacity'  => $data['capacity'] ?? 120,
            'is_active' => 1,
        ]);

        if (! empty($data['breed'])) {
            Hen::create([
                'cage_id'                 => $cage->id,
                'placement_date'          => now(),
                'age_at_placement_weeks'  => $data['age_at_placement_weeks'] ?? 0,
                'flock_age_weeks'         => 0,
                'breed'                   => $data['breed'],
            ]);
        }

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} added.");
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
