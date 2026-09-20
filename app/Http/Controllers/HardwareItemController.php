<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Device;
use App\Models\HardwareItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            'cage_slot_ids' => 'nullable|array',
            'cage_slot_ids.*' => 'exists:cage_slots,id',
            'device_id' => 'nullable|exists:devices,id',
            'installation_date' => 'nullable|date',
            'status' => ['required', Rule::in(HardwareItem::STATUSES)],
            'last_calibration_date' => 'nullable|date',
        ]);

        $validator->after(function ($validator) use ($request, $existing) {
            $data = $validator->validated();
            $deviceType = $data['device_type'] ?? null;
            $cageId = $data['cage_id'] ?? null;
            $cageSlotId = $data['cage_slot_id'] ?? null;
            $extraSlotIds = array_values(array_unique(array_filter($data['cage_slot_ids'] ?? [])));
            $status = $data['status'] ?? null;

            if ($status === 'spare') {
                if ($cageId !== null || $cageSlotId !== null || ! empty($extraSlotIds)) {
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
                if (in_array($cageSlotId, $extraSlotIds)) {
                    $validator->errors()->add('cage_slot_ids', 'Additional slots must not repeat the primary slot.');
                }
            } elseif (in_array($deviceType, ['DHT22', 'relay'])) {
                if ($cageId === null) {
                    $validator->errors()->add('cage_id', "{$deviceType} devices must be assigned to a cage.");
                }
                if ($cageSlotId !== null) {
                    $validator->errors()->add('cage_slot_id', "{$deviceType} devices must not be assigned to a specific slot.");
                }
                if (! empty($extraSlotIds)) {
                    $validator->errors()->add('cage_slot_ids', 'Additional slots are only supported for IR breakbeam sensors.');
                }

                if ($deviceType === 'DHT22' && $cageId && $status !== 'spare') {
                    $duplicateExists = HardwareItem::where('device_type', 'DHT22')
                        ->where('cage_id', $cageId)
                        ->where('status', 'active')
                        ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                        ->exists();
                    if ($duplicateExists) {
                        $validator->errors()->add('cage_id', 'This cage already has an active DHT22 sensor. Replace or deactivate the existing one first.');
                    }
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
        $faultyCount = (clone $query)->where('health_state', 'faulty')->count();

        $items = $query->with(['cage', 'cageSlot.cage', 'device', 'latestOccupancyReading', 'additionalSlots.cage'])
            ->orderBy('status')
            ->orderBy('serial_number')
            ->paginate(20)
            ->withQueryString();

        // Latest REAL reading per cage for the "Last Reading" column
        // (demo-quarantined; see EnvironmentalLog::latestRealPerCage).
        $cageIds = $items->getCollection()->map(fn ($i) => $i->cage?->id)->filter()->unique()->values();
        $latestReal = \App\Models\EnvironmentalLog::latestRealPerCage($cageIds);
        $items->getCollection()->each(function ($item) use ($latestReal) {
            if ($item->cage) {
                $item->cage->setRelation('latestEnvironmentLog', $latestReal->get($item->cage->id));
            }
        });

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
        $extraSlotIds = ($data['device_type'] ?? null) === 'IR_breakbeam' && ($data['status'] ?? null) !== 'spare'
            ? array_values(array_unique(array_filter($data['cage_slot_ids'] ?? [])))
            : [];
        unset($data['cage_slot_ids']);

        if ($data['status'] === 'spare') {
            $data['cage_id'] = null;
            $data['cage_slot_id'] = null;
        }

        HardwareItem::create($data)->additionalSlots()->sync($extraSlotIds);

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
        $extraSlotIds = ($data['device_type'] ?? null) === 'IR_breakbeam' && ($data['status'] ?? null) !== 'spare'
            ? array_values(array_unique(array_filter($data['cage_slot_ids'] ?? [])))
            : [];
        unset($data['cage_slot_ids']);

        if ($data['status'] === 'spare') {
            $data['cage_id'] = null;
            $data['cage_slot_id'] = null;
        }

        // Capture the pre-update identity so the OLD (serial_number,
        // device_id) pair's ingestion cache entry gets busted too — status,
        // serial_number, and device_id (reassignment to a different Pi) can
        // all change here, and any of them can make a cached lookup stale.
        $this->forgetIngestionCache($hardwareItem->serial_number, $hardwareItem->device_id);
        $oldSlotId = $hardwareItem->cage_slot_id;

        DB::transaction(function () use ($hardwareItem, $data, $extraSlotIds, $oldSlotId) {
            $hardwareItem->update($data);
            $hardwareItem->additionalSlots()->sync($extraSlotIds);

            // A reassigned sensor's own reading stream follows it: occupancy
            // readings are keyed by hardware_item_id, so repoint their slot
            // reference to the new primary slot. (DHT22 environmental logs
            // carry only a cage_id with no sensor link, so past climate rows
            // intentionally stay with the cage they were recorded in —
            // future readings land in the new cage via cage_id.)
            if ($hardwareItem->device_type === 'IR_breakbeam'
                && $hardwareItem->cage_slot_id
                && $oldSlotId !== $hardwareItem->cage_slot_id) {
                \App\Models\SensorOccupancyReading::where('hardware_item_id', $hardwareItem->id)
                    ->update(['cage_slot_id' => $hardwareItem->cage_slot_id]);
            }
        });

        $this->forgetIngestionCache($hardwareItem->serial_number, $hardwareItem->device_id);

        return redirect()->route('hardware.index')->with('success', 'Hardware item updated.');
    }

    public function destroy(HardwareItem $hardwareItem)
    {
        $this->forgetIngestionCache($hardwareItem->serial_number, $hardwareItem->device_id);

        $hardwareItem->delete();

        return redirect()->route('hardware.index')->with('success', 'Hardware item removed.');
    }

    private function forgetIngestionCache(string $serialNumber, ?int $deviceId): void
    {
        if ($deviceId === null) {
            return;
        }

        Cache::forget(HardwareItem::ingestionCacheKey($serialNumber, $deviceId));
    }
}
