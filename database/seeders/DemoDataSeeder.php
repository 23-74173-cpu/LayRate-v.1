<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EnvironmentalLog;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\Forecast;
use App\Models\HardwareItem;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * ⚠️  DEV ONLY — NEVER run on a production (Pi) deployment.
 *
 * Seeds mock/demo data: cages, cage_slots, hens, feed batches,
 * production logs, environmental logs, feed consumption, alerts,
 * mortality records, forecasts, and hardware items.
 *
 * All values are fabricated for UI testing and local development.
 * Hardcoded cage-code references (CAGE-A, etc.) assume the standard
 * 4-cage naming convention from the seed data.
 *
 * Production guard: aborts immediately if app()->environment('production').
 * This is also enforced by DatabaseSeeder, which never calls this class.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('DemoDataSeeder cannot run in production. Aborting.');
            $this->command->error('This seeder creates mock data and is for local dev only.');

            return;
        }

        // ── Feed Batches ──────────────────────────────────────────
        $feedBatches = [
            ['batch_code' => 'F-001', 'crude_protein' => 17.50, 'date_received' => '2026-03-01', 'notes' => 'Layer mash - standard'],
            ['batch_code' => 'F-002', 'crude_protein' => 16.80, 'date_received' => '2026-03-15', 'notes' => 'Layer pellet - supplier B'],
            ['batch_code' => 'F-003', 'crude_protein' => 18.00, 'date_received' => '2026-03-28', 'notes' => 'Protein-boosted mix'],
        ];
        foreach ($feedBatches as $fb) {
            FeedBatch::firstOrCreate(['batch_code' => $fb['batch_code']], $fb);
        }
        $batches = FeedBatch::orderBy('date_received')->get()->keyBy('batch_code');

        // ── Cages ─────────────────────────────────────────────────
        $cagesData = [
            ['cage_code' => 'CAGE-A', 'location' => '', 'rows' => 3, 'slots_per_row' => 5, 'max_chickens_per_slot' => 4, 'total_capacity' => 60, 'is_active' => 1],
            ['cage_code' => 'CAGE-B', 'location' => '', 'rows' => 3, 'slots_per_row' => 5, 'max_chickens_per_slot' => 4, 'total_capacity' => 60, 'is_active' => 1],
            ['cage_code' => 'CAGE-C', 'location' => '', 'rows' => 3, 'slots_per_row' => 5, 'max_chickens_per_slot' => 4, 'total_capacity' => 60, 'is_active' => 1],
            ['cage_code' => 'CAGE-D', 'location' => '', 'rows' => 3, 'slots_per_row' => 5, 'max_chickens_per_slot' => 4, 'total_capacity' => 60, 'is_active' => 0],
        ];
        foreach ($cagesData as $cd) {
            Cage::firstOrCreate(['cage_code' => $cd['cage_code']], $cd);
        }
        $cages = Cage::orderBy('cage_code')->get()->keyBy('cage_code');

        // Grab an operator user for recorded_by (created by UserSeeder).
        $user = User::where('email', 'operator@layrate.local')->first();
        if (! $user) {
            $this->command->warn('Operator user not found — falling back to first admin user.');
            $user = User::where('role', 'admin')->first();
        }
        if (! $user) {
            $this->command->error('No users found. Run DatabaseSeeder first before DemoDataSeeder.');

            return;
        }

        // ── Hens data per cage (breed, flock info) ────────────────
        $hensData = [
            'CAGE-A' => ['flock_age_weeks' => 28, 'breed' => 'ISA Brown',             'date_acquired' => '2025-10-18', 'tag_code' => 'FLOCK-A-2025', 'placement_date' => '2025-10-18', 'age_at_placement_weeks' => 0],
            'CAGE-B' => ['flock_age_weeks' => 34, 'breed' => 'Lohmann Brown-Classic', 'date_acquired' => '2025-09-06', 'tag_code' => 'FLOCK-B-2025', 'placement_date' => '2025-09-06', 'age_at_placement_weeks' => 0],
            'CAGE-C' => ['flock_age_weeks' => 52, 'breed' => 'Dekalb White',          'date_acquired' => '2025-04-19', 'tag_code' => 'FLOCK-C-2025', 'placement_date' => '2025-04-19', 'age_at_placement_weeks' => 0],
            'CAGE-D' => ['flock_age_weeks' => 18, 'breed' => 'ISA Brown',             'date_acquired' => '2025-12-13', 'tag_code' => 'FLOCK-D-2026', 'placement_date' => '2025-12-13', 'age_at_placement_weeks' => 0],
        ];

        // ── IR breakbeam sensor placement (slot numbers, 1-indexed) ──
        $sensorSlots = [
            'CAGE-A' => [1, 5, 6, 10],
        ];

        // ── CageSlots + Hens + HardwareItems ──────────────────────
        foreach ($cages as $cage) {
            for ($row = 1; $row <= $cage->rows; $row++) {
                for ($col = 1; $col <= $cage->slots_per_row; $col++) {
                    $slotNumber = ($row - 1) * $cage->slots_per_row + $col;
                    $isSensor = isset($sensorSlots[$cage->cage_code]) && in_array($slotNumber, $sensorSlots[$cage->cage_code]);
                    $slot = CageSlot::firstOrCreate(
                        ['cage_id' => $cage->id, 'slot_number' => $slotNumber],
                        [
                            'row_number' => $row,
                            'column_number' => $col,
                            'current_occupancy' => $cage->is_active ? $cage->max_chickens_per_slot : 0,
                        ]
                    );

                    if ($isSensor) {
                        HardwareItem::firstOrCreate(
                            ['cage_slot_id' => $slot->id, 'device_type' => 'IR_breakbeam'],
                            ['serial_number' => "SN-CAGE{$cage->id}-SLOT{$slotNumber}", 'status' => 'active']
                        );
                    }

                    if ($cage->is_active && isset($hensData[$cage->cage_code])) {
                        $hd = $hensData[$cage->cage_code];
                        for ($h = 1; $h <= $cage->max_chickens_per_slot; $h++) {
                            $hen = Hen::firstOrCreate(
                                ['tag_code' => "{$cage->cage_code}-SLOT{$slotNumber}-HEN{$h}"],
                                [
                                    'flock_age_weeks' => $hd['flock_age_weeks'],
                                    'breed' => $hd['breed'],
                                    'date_acquired' => $hd['date_acquired'],
                                    'placement_date' => $hd['placement_date'],
                                    'age_at_placement_weeks' => $hd['age_at_placement_weeks'],
                                    'is_active' => 1,
                                ]
                            );
                            if ($hen->wasRecentlyCreated) {
                                $hen->cage_slot_id = $slot->id;
                                $hen->save();
                            }
                        }
                    }
                }
            }
        }

        // ── Production Logs (14 days) ─────────────────────────────
        $slotEggCounts = [
            'CAGE-A' => ['egg_count' => 6, 'hdep' => 85.83],
            'CAGE-B' => ['egg_count' => 5, 'hdep' => 72.50],
            'CAGE-C' => ['egg_count' => 3, 'hdep' => 58.33],
            'CAGE-D' => ['egg_count' => 0, 'hdep' => 0.00],
        ];

        $cageSlots = CageSlot::with('cage')->get()->groupBy(fn ($s) => $s->cage->cage_code);
        for ($i = 0; $i < 14; $i++) {
            $date = now()->subDays($i)->toDateString();
            foreach ($cageSlots as $code => $slots) {
                $base = $slotEggCounts[$code] ?? $slotEggCounts['CAGE-D'];
                foreach ($slots as $slot) {
                    if (! $slot->cage->is_active) {
                        continue;
                    }
                    $variation = ($i % 3) * 0.3;
                    $eggsPerSlot = max(0, $base['egg_count'] - (int) ($i % 3));
                    $log = ProductionLog::firstOrNew(
                        ['cage_slot_id' => $slot->id, 'log_date' => $date]
                    );
                    $log->cage_slot_id = $slot->id;
                    $log->recorded_by = $user->id;
                    $isSensor = $i % 2 === 0;
                    $log->fill([
                        'egg_count' => $eggsPerSlot,
                        'hen_count' => $slot->current_occupancy,
                        'hdep' => max(0, round($base['hdep'] - $variation, 2)),
                        'notes' => $isSensor ? 'IR sensor synced' : 'Manual check',
                        'logged_via' => $isSensor ? 'sensor' : 'manual',
                    ]);
                    $log->save();
                }
            }
        }

        // ── Environmental Logs (24 readings, 2h apart) ────────────
        // Idempotent: uses firstOrCreate with cage_id + recorded_at.
        for ($h = 0; $h < 24; $h++) {
            $ts = now()->subHours($h * 2);
            $envBase = [
                'CAGE-A' => ['temp' => 28.9, 'hum' => 68.1],
                'CAGE-B' => ['temp' => 28.7, 'hum' => 70.0],
                'CAGE-C' => ['temp' => 29.2, 'hum' => 71.0],
                'CAGE-D' => ['temp' => 27.9, 'hum' => 66.6],
            ];
            foreach ($envBase as $code => $base) {
                $v = ($h % 5) * 0.2;
                EnvironmentalLog::firstOrCreate(
                    ['cage_id' => $cages[$code]->id, 'recorded_at' => $ts],
                    [
                        'temperature_c' => round($base['temp'] - $v, 1),
                        'humidity_pct' => round($base['hum'] - $v * 0.5, 1),
                    ]
                );
            }
        }

        // ── Feed Consumption Logs (7 days) ────────────────────────
        $feedConsumption = ['CAGE-A' => 11.4, 'CAGE-B' => 12.4, 'CAGE-C' => 13.4];
        for ($i = 0; $i < 7; $i++) {
            $date = now()->subDays($i)->toDateString();
            $batchKey = $i < 3 ? 'F-003' : ($i < 5 ? 'F-002' : 'F-001');
            foreach ($feedConsumption as $code => $kg) {
                FeedConsumptionLog::firstOrCreate(
                    ['cage_id' => $cages[$code]->id, 'log_date' => $date],
                    [
                        'feed_batch_id' => $batches[$batchKey]->id,
                        'feed_consumed_kg' => round($kg + ($i % 3) * 0.2, 1),
                        'recorded_by' => $user->id,
                    ]
                );
            }
        }

        // ── Alerts ────────────────────────────────────────────────
        Alert::firstOrCreate(
            ['cage_id' => $cages['CAGE-C']->id, 'alert_type' => 'humidity_high'],
            ['message' => 'Humidity at 71% — above 70% threshold', 'is_read' => 0, 'triggered_at' => now()->subHours(2)]
        );
        Alert::firstOrCreate(
            ['cage_id' => $cages['CAGE-B']->id, 'alert_type' => 'humidity_watch'],
            ['message' => 'Humidity at 70% — at threshold boundary', 'is_read' => 0, 'triggered_at' => now()->subHours(5)]
        );

        // ── Mortality Logs ────────────────────────────────────────
        $mortalitySamples = [
            ['cage' => 'CAGE-C', 'days_ago' => 0, 'count' => 1, 'reason' => 'Heat Stress', 'notes' => 'Found near water trough, high temp recorded that day'],
            ['cage' => 'CAGE-A', 'days_ago' => 1, 'count' => 1, 'reason' => 'Unknown',     'notes' => null],
            ['cage' => 'CAGE-C', 'days_ago' => 2, 'count' => 2, 'reason' => 'Disease',     'notes' => 'Respiratory symptoms observed in surrounding hens'],
            ['cage' => 'CAGE-B', 'days_ago' => 3, 'count' => 1, 'reason' => 'Injury',      'notes' => 'Likely pecking injury — isolated others'],
            ['cage' => 'CAGE-C', 'days_ago' => 5, 'count' => 1, 'reason' => 'Disease',     'notes' => null],
            ['cage' => 'CAGE-A', 'days_ago' => 7, 'count' => 1, 'reason' => 'Unknown',     'notes' => null],
            ['cage' => 'CAGE-D', 'days_ago' => 9, 'count' => 1, 'reason' => 'Other',       'notes' => 'Cage D flock still in low-production phase'],
        ];
        foreach ($mortalitySamples as $ms) {
            MortalityLog::firstOrCreate(
                ['cage_id' => $cages[$ms['cage']]->id, 'log_date' => now()->subDays($ms['days_ago'])->toDateString(), 'reason' => $ms['reason']],
                ['count' => $ms['count'], 'notes' => $ms['notes'], 'recorded_by' => $user->id]
            );
        }

        // ── Forecasts ─────────────────────────────────────────────
        $today = now()->toDateString();
        for ($i = 1; $i <= 7; $i++) {
            $v = (($i % 3) - 1) * 0.3;
            Forecast::firstOrCreate(
                ['cage_id' => $cages['CAGE-A']->id, 'forecast_date' => $today, 'target_date' => now()->addDays($i)->toDateString()],
                ['predicted_egg_count' => round(120.0 + $v, 2)]
            );
        }

        $this->command->info('Demo data seeded successfully.');
    }
}
