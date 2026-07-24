<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Device;
use App\Models\HardwareItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HardwareItemController extends Controller
{
    public function index()
    {
        $cages = Cage::orderBy('cage_code')->get();
        $cageSlots = CageSlot::with('cage')->orderBy('cage_id')->orderBy('slot_number')->get();
        $devices = Device::withCount('hardwareItems')->orderBy('name')->get();

        $editItem = null;
        if ($editItemId = session('reopen_edit_hardware')) {
            $editItem = HardwareItem::find($editItemId);
        }

        return view('hardware.index', compact('cages', 'cageSlots', 'devices', 'editItem'));
    }

    private function hardwareValidator(Request $request, ?HardwareItem $existing = null): \Illuminate\Validation\Validator
    {
        $serialRules = ['required', 'string', 'max:100'];
        $serialRules[] = Rule::unique('hardware_items', 'serial_number')
            ->ignore($existing?->id);

        $validator = Validator::make($request->all(), [
            'device_type' => ['required', Rule::in(HardwareItem::DEVICE_TYPES)],
            'serial_number' => $serialRules,
            'cage_id' => 'nullable|exists:cages,id',
            'cage_slot_id' => 'nullable|exists:cage_slots,id',
            'device_id' => 'nullable|exists:devices,id',
            'installation_date' => 'nullable|date',
            'status' => ['required', Rule::in(HardwareItem::STATUSES)],
            'last_calibration_date' => 'nullable|date',
        ]);

        $validator->after(function ($validator) use ($request) {
            $data = $validator->validated();
            $deviceType = $data['device_type'] ?? null;
            $cageId = $data['cage_id'] ?? null;
            $cageSlotId = $data['cage_slot_id'] ?? null;
            $status = $data['status'] ?? null;

            if ($status === 'spare') {
                if ($cageId !== null || $cageSlotId !== null) {
                    $validator->errors()->add('status', 'Spare devices must not be assigned to a cage or slot.');
                }
                return;
            }

            if ($deviceType === 'IR_breakbeam') {
                if ($cageSlotId === null) {
                    $validator->errors()->add('cage_slot_id', 'IR breakbeam sensors must be assigned to a cage slot.');
                }
                if ($cageId !== null) {
                    $validator->errors()->add('cage_id', 'IR breakbeam sensors must not be assigned to a cage directly.');
                }
            } elseif (in_array($deviceType, ['DHT22', 'relay'])) {
                if ($cageId === null) {
                    $validator->errors()->add('cage_id', "{$deviceType} devices must be assigned to a cage.");
                }
                if ($cageSlotId !== null) {
                    $validator->errors()->add('cage_slot_id', "{$deviceType} devices must not be assigned to a specific slot.");
                }
            }
        });

        return $validator;
    }

    public function liveData(Request $request)
    {
        $query = HardwareItem::query();

        // Summary counts must reflect every hardware item, not just the
        // current page — computed before pagination narrows the query.
        $breakbeamCount = (clone $query)->where('device_type', 'IR_breakbeam')->where('status', 'active')->count();
        $dht22Count = (clone $query)->where('device_type', 'DHT22')->where('status', 'active')->count();
        $activeCount = (clone $query)->where('status', 'active')->count();
        $faultyCount = (clone $query)->where('status', 'faulty')->count();

        $items = $query->with(['cage.latestEnvironmentLog', 'cageSlot.cage', 'device', 'latestOccupancyReading'])
            ->orderBy('status')
            ->orderBy('serial_number')
            ->paginate(20)
            ->withQueryString();

        return view('hardware._live-data', compact(
            'items', 'breakbeamCount', 'dht22Count', 'activeCount', 'faultyCount'
        ));
    }

    public function store(Request $request)
    {
        $validator = $this->hardwareValidator($request);

        if ($validator->fails()) {
            return redirect()->route('hardware.index')
                ->with('reopen_add_hardware', true)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        if ($data['status'] === 'spare') {
            $data['cage_id'] = null;
            $data['cage_slot_id'] = null;
        }

        HardwareItem::create($data);

        return redirect()->route('hardware.index')->with('success', 'Hardware item added.');
    }

    public function update(Request $request, HardwareItem $hardwareItem)
    {
        $validator = $this->hardwareValidator($request, $hardwareItem);

        if ($validator->fails()) {
            return redirect()->route('hardware.index')
                ->with('reopen_edit_hardware', $hardwareItem->id)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

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
