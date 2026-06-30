<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BulkAddController extends Controller
{
    public function show(Cage $cage)
    {
        $slots = $cage->slots()->orderBy('slot_number')->get();

        return view('bulk-add', compact('cage', 'slots'));
    }

    public function store(Request $request, Cage $cage)
    {
        $data = $request->validate([
            'slot_ids'                  => 'required|array|min:1|max:500',
            'slot_ids.*'                => 'integer|exists:cage_slots,id',
            'breed'                     => 'required|in:ISA Brown,Lohmann Brown-Classic,Dekalb White,Hy-Line Brown,Novogen Brown',
            'age_at_placement_weeks'    => 'required|integer|min:0',
            'chickens_per_slot'         => 'required|integer|min:1',
        ]);

        $slots = CageSlot::whereIn('id', $data['slot_ids'])->where('cage_id', $cage->id)->get();

        if ($slots->count() !== count($data['slot_ids'])) {
            return back()->withInput()->withErrors(['slot_ids' => 'One or more selected slots do not belong to this cage.']);
        }

        $perSlot = $data['chickens_per_slot'];
        $overflow = $slots->filter(fn($s) => $s->current_occupancy + $perSlot > $cage->max_chickens_per_slot);

        if ($overflow->isNotEmpty()) {
            $names = $overflow->map(fn($s) => "{$s->label} ({$s->current_occupancy}/{$cage->max_chickens_per_slot})")->implode(', ');
            return back()->withInput()->withErrors([
                'chickens_per_slot' => "{$perSlot} per slot would overflow: {$names}. Lower the count or deselect those slots.",
            ]);
        }

        $placementDate = now();

        DB::transaction(function () use ($slots, $data, $placementDate, $perSlot) {
            foreach ($slots as $slot) {
                Hen::create([
                    'cage_slot_id'           => $slot->id,
                    'breed'                  => $data['breed'],
                    'placement_date'         => $placementDate,
                    'age_at_placement_weeks' => $data['age_at_placement_weeks'],
                    'flock_age_weeks'        => $data['age_at_placement_weeks'],
                    'is_active'              => 1,
                ]);

                $slot->increment('current_occupancy', $perSlot);
            }
        });

        return redirect()->route('cages.index')->with('success', "Added chickens to " . $slots->count() . " slot(s) in {$cage->cage_code}.");
    }
}
