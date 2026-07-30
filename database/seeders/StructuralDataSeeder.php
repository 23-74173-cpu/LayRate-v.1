<?php

namespace Database\Seeders;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Device;
use App\Models\HardwareItem;
use Illuminate\Database\Seeder;

/**
 * DEPLOYMENT-SAFE — seeds cages, cage_slots, hardware items, and device.
 *
 * Creates the physical farm structure: cages, their slots, and any
 * sensor hardware (DHT22, IR breakbeam) assigned to them.
 * Also creates a Device record for the Raspberry Pi bridge.
 *
 * All operations are idempotent (firstOrCreate).
 * Safe to run in any environment, including production.
 */
class StructuralDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── Device ─────────────────────────────────────────────────
        $device = Device::firstOrCreate(
            ['name' => 'Raspberry Pi'],
            [
                'api_key_hash' => bcrypt('layrate-pi-dev-key-2026'),
                'is_active' => true,
            ]
        );

        // ── Cages ──────────────────────────────────────────────────
        $cagesData = [
            ['cage_code' => 'CAGE-A', 'location' => '', 'rows' => 3, 'slots_per_row' => 5, 'max_chickens_per_slot' => 4, 'total_capacity' => 60, 'is_active' => 1],
            ['cage_code' => 'CAGE-B', 'location' => '', 'rows' => 3, 'slots_per_row' => 5, 'max_chickens_per_slot' => 4, 'total_capacity' => 60, 'is_active' => 1],
            ['cage_code' => 'CAGE-C', 'location' => '', 'rows' => 3, 'slots_per_row' => 5, 'max_chickens_per_slot' => 4, 'total_capacity' => 60, 'is_active' => 1],
            ['cage_code' => 'CAGE-D', 'location' => '', 'rows' => 3, 'slots_per_row' => 5, 'max_chickens_per_slot' => 4, 'total_capacity' => 60, 'is_active' => 0],
            ['cage_code' => 'CAGE-T', 'location' => 'Test cage', 'rows' => 1, 'slots_per_row' => 1, 'max_chickens_per_slot' => 4, 'total_capacity' => 4, 'is_active' => 1],
        ];
        foreach ($cagesData as $cd) {
            Cage::firstOrCreate(['cage_code' => $cd['cage_code']], $cd);
        }
        $cages = Cage::orderBy('cage_code')->get()->keyBy('cage_code');

        // ── CageSlots ──────────────────────────────────────────────
        foreach ($cages as $cage) {
            for ($row = 1; $row <= $cage->rows; $row++) {
                for ($col = 1; $col <= $cage->slots_per_row; $col++) {
                    $slotNumber = ($row - 1) * $cage->slots_per_row + $col;
                    CageSlot::firstOrCreate(
                        ['cage_id' => $cage->id, 'slot_number' => $slotNumber],
                        [
                            'row_number' => $row,
                            'column_number' => $col,
                            'current_occupancy' => 0,
                        ]
                    );
                }
            }
        }

        // ── HardwareItems (sensor-equipped slots) ──────────────────
        $sensorSlots = [
            'CAGE-A' => [1, 5, 6, 10],
            'CAGE-T' => [1],
        ];

        foreach ($sensorSlots as $cageCode => $slots) {
            $cage = $cages[$cageCode];
            foreach ($slots as $slotNumber) {
                $slot = CageSlot::where('cage_id', $cage->id)
                    ->where('slot_number', $slotNumber)
                    ->first();
                if (! $slot) {
                    continue;
                }

                $serial = $cageCode === 'CAGE-T'
                    ? 'IRBBS-001'
                    : "SN-CAGE{$cage->id}-SLOT{$slotNumber}";

                HardwareItem::updateOrCreate(
                    ['cage_slot_id' => $slot->id, 'device_type' => 'IR_breakbeam'],
                    [
                        'serial_number' => $serial,
                        'device_id' => $device->id,
                        'cage_id' => $cage->id,
                        'status' => 'active',
                    ]
                );
            }
        }

        // ── DHT22 for CAGE-T ──────────────────────────────────────
        HardwareItem::updateOrCreate(
            ['serial_number' => 'DHT22-001'],
            [
                'device_type' => 'DHT22',
                'device_id' => $device->id,
                'cage_id' => $cages['CAGE-T']->id,
                'cage_slot_id' => null,
                'status' => 'active',
            ]
        );

        $this->command->info('Structural data seeded: cages, slots, hardware items, and device.');
        $this->command->info('Device API key (for bridge): layrate-pi-dev-key-2026');
    }
}
