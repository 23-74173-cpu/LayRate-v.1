<?php

namespace App\Http\Requests;

use App\Models\HardwareItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHardwareItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_type'           => ['required', Rule::in(HardwareItem::DEVICE_TYPES)],
            'serial_number'         => ['required', 'string', 'max:100', Rule::unique('hardware_items', 'serial_number')->ignore($this->route('hardwareItem'))],
            'cage_id'               => 'nullable|exists:cages,id',
            'cage_slot_id'          => 'nullable|exists:cage_slots,id',
            'installation_date'     => 'nullable|date',
            'status'                => ['required', Rule::in(HardwareItem::STATUSES)],
            'last_calibration_date' => 'nullable|date',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->validated();
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
            // 'other': both nullable — no enforcement
        });
    }
}
