<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHardwareItemRequest;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\HardwareItem;

class HardwareItemController extends Controller
{
    public function index()
    {
        $items = HardwareItem::with(['cage', 'cageSlot.cage'])
            ->orderByDesc('status')
            ->orderBy('serial_number')
            ->get();

        $breakbeamCount = $items->where('device_type', 'IR_breakbeam')->where('status', 'active')->count();
        $dht22Count = $items->where('device_type', 'DHT22')->where('status', 'active')->count();
        $activeCount = $items->where('status', 'active')->count();
        $faultyCount = $items->where('status', 'faulty')->count();

        $cages = Cage::orderBy('cage_code')->get();
        $cageSlots = CageSlot::with('cage')->orderBy('cage_id')->orderBy('slot_number')->get();

        return view('hardware.index', compact(
            'items', 'breakbeamCount', 'dht22Count', 'activeCount', 'faultyCount', 'cages', 'cageSlots'
        ));
    }

    public function store(StoreHardwareItemRequest $request)
    {
        $data = $request->validated();

        if ($data['status'] === 'spare') {
            $data['cage_id'] = null;
            $data['cage_slot_id'] = null;
        }

        HardwareItem::create($data);

        return redirect()->route('hardware.index')->with('success', 'Hardware item added.');
    }

    public function update(StoreHardwareItemRequest $request, HardwareItem $hardwareItem)
    {
        $data = $request->validated();

        if ($data['status'] === 'spare') {
            $data['cage_id'] = null;
            $data['cage_slot_id'] = null;
        }

        $hardwareItem->update($data);

        return redirect()->route('hardware.index')->with('success', 'Hardware item updated.');
    }

    public function destroy(HardwareItem $hardwareItem)
    {
        $hardwareItem->delete();

        return redirect()->route('hardware.index')->with('success', 'Hardware item removed.');
    }
}
