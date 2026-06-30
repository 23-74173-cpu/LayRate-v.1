<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EnvironmentalLog;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\Forecast;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@layrate.local'],
            ['name' => 'Farm Admin', 'password' => Hash::make('password'), 'role' => 'admin']
        );

        $user = User::firstOrCreate(
            ['email' => 'operator@layrate.local'],
            ['name' => 'Farm Operator', 'password' => Hash::make('password'), 'role' => 'operator']
        );

        // ── Cages, each with a real row x slot grid ──────────────────
        $cageDefs = [
            'CAGE-A' => ['location' => 'North Wing', 'rows' => 3, 'slots_per_row' => 10, 'max_chickens_per_slot' => 4, 'is_active' => 1],
            'CAGE-B' => ['location' => 'East Wing',  'rows' => 3, 'slots_per_row' => 10, 'max_chickens_per_slot' => 4, 'is_active' => 1],
            'CAGE-C' => ['location' => 'South Wing', 'rows' => 2, 'slots_per_row' => 8,  'max_chickens_per_slot' => 3, 'is_active' => 1],
            'CAGE-D' => ['location' => 'West Wing',  'rows' => 2, 'slots_per_row' => 8,  'max_chickens_per_slot' => 3, 'is_active' => 0],
        ];

        $cages = [];
        foreach ($cageDefs as $code => $def) {
            $cages[$code] = Cage::create(array_merge(['cage_code' => $code], $def));
        }

        // ── Slots for every cage (full grid, most left empty) ────────
        $slotsByCage = [];
        foreach ($cages as $code => $cage) {
            $slotsByCage[$code] = [];
            $slotNumber = 1;
            for ($row = 1; $row <= $cage->rows; $row++) {
                for ($col = 1; $col <= $cage->slots_per_row; $col++) {
                    $slotsByCage[$code]["{$row}-{$col}"] = CageSlot::create([
                        'cage_id'       => $cage->id,
                        'row_number'    => $row,
                        'column_number' => $col,
                        'slot_number'   => $slotNumber++,
                    ]);
                }
            }
        }

        // ── Which slots are occupied, and which have a sensor ────────
        $occupied = [
            'CAGE-A' => [
                ['key' => '1-1', 'breed' => 'ISA Brown',             'age' => 28, 'occ' => 4],
                ['key' => '1-2', 'breed' => 'ISA Brown',             'age' => 28, 'occ' => 4],
                ['key' => '1-3', 'breed' => 'ISA Brown',             'age' => 28, 'occ' => 4, 'sensor' => true],
                ['key' => '1-4', 'breed' => 'ISA Brown',             'age' => 28, 'occ' => 3],
                ['key' => '1-5', 'breed' => 'ISA Brown',             'age' => 28, 'occ' => 4],
                ['key' => '1-6', 'breed' => 'ISA Brown',             'age' => 28, 'occ' => 4],
                ['key' => '2-1', 'breed' => 'Lohmann Brown-Classic', 'age' => 20, 'occ' => 4],
                ['key' => '2-2', 'breed' => 'Lohmann Brown-Classic', 'age' => 20, 'occ' => 4],
                ['key' => '2-3', 'breed' => 'Lohmann Brown-Classic', 'age' => 20, 'occ' => 3],
                ['key' => '2-4', 'breed' => 'Lohmann Brown-Classic', 'age' => 20, 'occ' => 4],
                ['key' => '2-5', 'breed' => 'Lohmann Brown-Classic', 'age' => 20, 'occ' => 4],
            ],
            'CAGE-B' => [
                ['key' => '1-1', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 4],
                ['key' => '1-2', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 4],
                ['key' => '1-3', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 4],
                ['key' => '1-4', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 3],
                ['key' => '1-5', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 4, 'sensor' => true],
                ['key' => '1-6', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 4],
                ['key' => '1-7', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 4],
                ['key' => '1-8', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 3],
            ],
            'CAGE-C' => [
                ['key' => '1-1', 'breed' => 'Hy-Line Brown', 'age' => 52, 'occ' => 3],
                ['key' => '1-2', 'breed' => 'Hy-Line Brown', 'age' => 52, 'occ' => 3],
                ['key' => '1-3', 'breed' => 'Hy-Line Brown', 'age' => 52, 'occ' => 2],
                ['key' => '1-4', 'breed' => 'Hy-Line Brown', 'age' => 52, 'occ' => 3],
                ['key' => '1-5', 'breed' => 'Hy-Line Brown', 'age' => 52, 'occ' => 3],
                ['key' => '1-6', 'breed' => 'Hy-Line Brown', 'age' => 52, 'occ' => 2],
            ],
            'CAGE-D' => [], // inactive cage, kept empty
        ];

        $placementDate = now()->subWeeks(4);
        $occupiedSlotModels = []; // for production log seeding below

        foreach ($occupied as $code => $entries) {
            foreach ($entries as $entry) {
                $slot = $slotsByCage[$code][$entry['key']];
                $slot->update([
                    'current_occupancy' => $entry['occ'],
                    'has_sensor'        => $entry['sensor'] ?? false,
                ]);

                Hen::create([
                    'cage_slot_id'           => $slot->id,
                    'breed'                  => $entry['breed'],
                    'placement_date'         => $placementDate,
                    'age_at_placement_weeks' => $entry['age'],
                    'flock_age_weeks'        => $entry['age'],
                    'date_acquired'          => $placementDate,
                    'is_active'              => 1,
                ]);

                $occupiedSlotModels[] = ['slot' => $slot->fresh(), 'occ' => $entry['occ']];
            }
        }

        // ── 14 days of production history for every occupied slot ────
        for ($i = 0; $i < 14; $i++) {
            $date = now()->subDays($i)->toDateString();
            foreach ($occupiedSlotModels as $row) {
                $slot = $row['slot'];
                $hens = $row['occ'];
                $baseRate = 0.8; // eggs per hen per day, roughly
                $variation = (($i % 4) - 2) * 0.03;
                $eggs = (int) round($hens * max(0.4, $baseRate + $variation));

                ProductionLog::firstOrCreate(
                    ['cage_slot_id' => $slot->id, 'log_date' => $date],
                    [
                        'egg_count'   => $eggs,
                        'hen_count'   => $hens,
                        'hdep'        => round(($eggs / max(1, $hens)) * 100, 2),
                        'recorded_by' => $user->id,
                        'notes'       => $slot->has_sensor ? 'IR sensor synced' : 'Manual check',
                    ]
                );
            }
        }

        // ── Feed batches (unchanged from before) ──────────────────────
        $feedBatches = [
            ['batch_code' => 'F-001', 'crude_protein' => 17.50, 'date_received' => '2026-03-01', 'notes' => 'Layer mash - standard'],
            ['batch_code' => 'F-002', 'crude_protein' => 16.80, 'date_received' => '2026-03-15', 'notes' => 'Layer pellet - supplier B'],
            ['batch_code' => 'F-003', 'crude_protein' => 18.00, 'date_received' => '2026-03-28', 'notes' => 'Protein-boosted mix'],
        ];
        foreach ($feedBatches as $fb) {
            FeedBatch::firstOrCreate(['batch_code' => $fb['batch_code']], $fb);
        }
        $batches = FeedBatch::orderBy('date_received')->get()->keyBy('batch_code');

        // ── Environmental logs (cage-level, unchanged shape) ──────────
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
                EnvironmentalLog::create([
                    'cage_id'       => $cages[$code]->id,
                    'recorded_at'   => $ts,
                    'temperature_c' => round($base['temp'] - $v, 1),
                    'humidity_pct'  => round($base['hum']  - $v * 0.5, 1),
                ]);
            }
        }

        // ── Feed consumption (cage-level, unchanged shape) ────────────
        $feedConsumption = ['CAGE-A' => 11.4, 'CAGE-B' => 12.4, 'CAGE-C' => 13.4];
        for ($i = 0; $i < 7; $i++) {
            $date     = now()->subDays($i)->toDateString();
            $batchKey = $i < 3 ? 'F-003' : ($i < 5 ? 'F-002' : 'F-001');
            foreach ($feedConsumption as $code => $kg) {
                FeedConsumptionLog::firstOrCreate(
                    ['cage_id' => $cages[$code]->id, 'log_date' => $date],
                    [
                        'feed_batch_id'    => $batches[$batchKey]->id,
                        'feed_consumed_kg' => round($kg + ($i % 3) * 0.2, 1),
                        'recorded_by'      => $user->id,
                    ]
                );
            }
        }

        // ── Alerts (cage-level, unchanged shape) ──────────────────────
        Alert::create([
            'cage_id' => $cages['CAGE-C']->id, 'alert_type' => 'humidity_high',
            'message' => 'Humidity at 71% — above 70% threshold', 'is_read' => 0, 'triggered_at' => now()->subHours(2),
        ]);
        Alert::create([
            'cage_id' => $cages['CAGE-B']->id, 'alert_type' => 'humidity_watch',
            'message' => 'Humidity at 70% — at threshold boundary', 'is_read' => 0, 'triggered_at' => now()->subHours(5),
        ]);

        // ── Mortality (cage-level, unchanged shape) ───────────────────
        $mortalitySamples = [
            ['cage' => 'CAGE-C', 'days_ago' => 0, 'count' => 1, 'reason' => 'Heat Stress', 'notes' => 'Found near water trough, high temp recorded that day'],
            ['cage' => 'CAGE-A', 'days_ago' => 1, 'count' => 1, 'reason' => 'Unknown',     'notes' => null],
            ['cage' => 'CAGE-C', 'days_ago' => 2, 'count' => 2, 'reason' => 'Disease',     'notes' => 'Respiratory symptoms observed in surrounding hens'],
            ['cage' => 'CAGE-B', 'days_ago' => 3, 'count' => 1, 'reason' => 'Injury',      'notes' => 'Likely pecking injury — isolated others'],
        ];
        foreach ($mortalitySamples as $ms) {
            MortalityLog::create([
                'cage_id'  => $cages[$ms['cage']]->id,
                'log_date' => now()->subDays($ms['days_ago'])->toDateString(),
                'reason'   => $ms['reason'],
                'count'    => $ms['count'],
                'notes'    => $ms['notes'],
                'recorded_by' => $user->id,
            ]);
        }

        // ── A whole-farm forecast seed (per-cage/per-row forecasts are generated on demand) ──
        $today = now()->toDateString();
        for ($i = 1; $i <= 7; $i++) {
            $v = (($i % 3) - 1) * 0.3;
            Forecast::create([
                'cage_id' => null,
                'row_number' => null,
                'forecast_date' => $today,
                'target_date' => now()->addDays($i)->toDateString(),
                'predicted_hdep' => round(78.0 + $v, 2),
            ]);
        }
    }
}
