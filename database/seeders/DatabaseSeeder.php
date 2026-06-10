<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Cage;
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

        $cagesData = [
            ['cage_code' => 'CAGE-A', 'location' => 'North Wing', 'capacity' => 120, 'is_active' => 1],
            ['cage_code' => 'CAGE-B', 'location' => 'East Wing',  'capacity' => 120, 'is_active' => 1],
            ['cage_code' => 'CAGE-C', 'location' => 'South Wing', 'capacity' => 120, 'is_active' => 1],
            ['cage_code' => 'CAGE-D', 'location' => 'West Wing',  'capacity' => 120, 'is_active' => 0],
        ];
        foreach ($cagesData as $cd) {
            Cage::firstOrCreate(['cage_code' => $cd['cage_code']], $cd);
        }
        $cages = Cage::orderBy('cage_code')->get()->keyBy('cage_code');

        $hensData = [
            'CAGE-A' => ['flock_age_weeks' => 28, 'breed' => 'ISA Brown',             'date_acquired' => '2025-10-18', 'tag_code' => 'FLOCK-A-2025'],
            'CAGE-B' => ['flock_age_weeks' => 34, 'breed' => 'Lohmann Brown-Classic', 'date_acquired' => '2025-09-06', 'tag_code' => 'FLOCK-B-2025'],
            'CAGE-C' => ['flock_age_weeks' => 52, 'breed' => 'Dekalb White',          'date_acquired' => '2025-04-19', 'tag_code' => 'FLOCK-C-2025'],
            'CAGE-D' => ['flock_age_weeks' => 18, 'breed' => 'ISA Brown',             'date_acquired' => '2025-12-13', 'tag_code' => 'FLOCK-D-2026'],
        ];
        foreach ($hensData as $code => $hd) {
            Hen::firstOrCreate(
                ['tag_code' => $hd['tag_code']],
                array_merge($hd, ['cage_id' => $cages[$code]->id, 'is_active' => 1])
            );
        }

        $feedBatches = [
            ['batch_code' => 'F-001', 'crude_protein' => 17.50, 'date_received' => '2026-03-01', 'notes' => 'Layer mash - standard'],
            ['batch_code' => 'F-002', 'crude_protein' => 16.80, 'date_received' => '2026-03-15', 'notes' => 'Layer pellet - supplier B'],
            ['batch_code' => 'F-003', 'crude_protein' => 18.00, 'date_received' => '2026-03-28', 'notes' => 'Protein-boosted mix'],
        ];
        foreach ($feedBatches as $fb) {
            FeedBatch::firstOrCreate(['batch_code' => $fb['batch_code']], $fb);
        }
        $batches = FeedBatch::orderBy('date_received')->get()->keyBy('batch_code');

        $prodData = [
            'CAGE-A' => ['egg_count' => 103, 'hdep' => 85.83],
            'CAGE-B' => ['egg_count' => 87,  'hdep' => 72.50],
            'CAGE-C' => ['egg_count' => 70,  'hdep' => 58.33],
            'CAGE-D' => ['egg_count' => 0,   'hdep' => 0.00],
        ];
        for ($i = 0; $i < 14; $i++) {
            $date = now()->subDays($i)->toDateString();
            foreach ($prodData as $code => $pd) {
                $variation = ($i % 3) * 0.5;
                ProductionLog::firstOrCreate(
                    ['cage_id' => $cages[$code]->id, 'log_date' => $date],
                    [
                        'egg_count'   => max(0, $pd['egg_count'] - ($i % 3)),
                        'hen_count'   => 120,
                        'hdep'        => max(0, round($pd['hdep'] - $variation, 2)),
                        'recorded_by' => $user->id,
                        'notes'       => $i % 2 === 0 ? 'IR sensor synced' : 'Manual check',
                    ]
                );
            }
        }

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

        Alert::firstOrCreate(
            ['cage_id' => $cages['CAGE-C']->id, 'alert_type' => 'humidity_high'],
            ['message' => 'Humidity at 71% — above 70% threshold', 'is_read' => 0, 'triggered_at' => now()->subHours(2)]
        );
        Alert::firstOrCreate(
            ['cage_id' => $cages['CAGE-B']->id, 'alert_type' => 'humidity_watch'],
            ['message' => 'Humidity at 70% — at threshold boundary', 'is_read' => 0, 'triggered_at' => now()->subHours(5)]
        );

        // Mortality sample data (past 14 days)
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

        $today = now()->toDateString();
        for ($i = 1; $i <= 7; $i++) {
            $v = (($i % 3) - 1) * 0.3;
            Forecast::firstOrCreate(
                ['cage_id' => $cages['CAGE-A']->id, 'forecast_date' => $today, 'target_date' => now()->addDays($i)->toDateString()],
                ['predicted_hdep' => round(86.0 + $v, 2)]
            );
        }
    }
}
