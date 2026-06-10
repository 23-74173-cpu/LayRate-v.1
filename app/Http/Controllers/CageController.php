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
            'cage_code' => 'required|string|max:50|unique:cages',
            'location'  => 'nullable|string|max:100',
            'capacity'  => 'nullable|integer|min:1',
            'breed'     => 'nullable|string',
        ]);

        $cage = Cage::create([
            'cage_code' => strtoupper($data['cage_code']),
            'location'  => $data['location'] ?? '',
            'capacity'  => $data['capacity'] ?? 120,
            'is_active' => 1,
        ]);

        if (! empty($data['breed'])) {
            Hen::create([
                'cage_id'         => $cage->id,
                'flock_age_weeks' => 0,
                'breed'           => $data['breed'],
            ]);
        }

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} added.");
    }

    public function update(Request $request, Cage $cage)
    {
        $data = $request->validate([
            'location' => 'nullable|string|max:100',
            'capacity' => 'nullable|integer|min:1',
            'is_active'=> 'nullable|boolean',
        ]);

        $cage->update($data);

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} updated.");
    }

    public function destroy(Cage $cage)
    {
        $cage->delete();
        return redirect()->route('cages.index')->with('success', 'Cage deleted.');
    }
}
