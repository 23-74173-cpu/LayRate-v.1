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
        // No default cages — created via the initial setup wizard.
        $cages = Cage::orderBy('cage_code')->get()->keyBy('cage_code');

        // ── CageSlots ──────────────────────────────────────────────
        // No default slots — created when cages are added.

        // ── HardwareItems ─────────────────────────────────────────
        // No default hardware — assigned when cages are set up.

        $this->command->info('Structural data seeded: device created.');
        $this->command->info('Device API key (for bridge): layrate-pi-dev-key-2026');
    }
}
