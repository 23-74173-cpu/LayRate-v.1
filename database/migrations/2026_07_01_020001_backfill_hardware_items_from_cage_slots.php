<?php

use App\Models\CageSlot;
use App\Models\HardwareItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $slots = CageSlot::whereNotNull('sensor_device_id')
            ->where('has_sensor', true)
            ->get();

        foreach ($slots as $slot) {
            HardwareItem::create([
                'device_type'       => 'IR_breakbeam',
                'serial_number'     => $slot->sensor_device_id,
                'cage_id'           => null,
                'cage_slot_id'      => $slot->id,
                'installation_date' => null,
                'status'            => 'active',
                'last_calibration_date' => null,
            ]);
        }
    }

    public function down(): void
    {
        HardwareItem::where('device_type', 'IR_breakbeam')
            ->whereNull('cage_id')
            ->whereNotNull('cage_slot_id')
            ->delete();
    }
};
