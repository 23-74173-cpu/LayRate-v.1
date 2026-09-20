<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historical farm data reseed — Mar 23 → Aug 27 2026 (158 days).
 *
 * Source: data/production_data_clean.csv (474 cage-day rows).
 *
 * Mapping onto the real schema (per-slot production granularity):
 * - cages CAGE-A/B/C rebuilt to 60/36/36 slots (4 birds/slot, 528 birds)
 * - hardware_items/devices left untouched; any slot/cage row they reference
 *   is preserved (and reset to fresh occupancy/hens like all others)
 * - CAGE-D deleted unless hardware references it
 * - production_logs: one row per SLOT per day (~20,856 rows); cage-day
 *   sums reconcile exactly to the CSV (74,990 eggs)
 * - environmental_logs: one row per cage per day, noon, is_override=1 (474)
 * - mortality_logs: 15 death-dates x 3 cages = 45 rows (57 deaths),
 *   with hens deactivated + mortality_log_hens pivots + slot occupancy
 * - feed: 10x Limcoma 1000kg batches + 316 farm_feed_entries (2x30kg/day)
 *   + 948 distributed per-cage logs split by live headcount (9,480 kg)
 *
 * All timestamps backdated realistically; recorded_by = admin user.
 * Rerunnable: purges business data first inside a transaction.
 */
class HistoricalFarmDataSeeder extends Seeder
{
    private const CSV_PATH = 'data/production_data_clean.csv';
    private const FLOCK_START = '2026-03-23';
    private const FLOCK_END = '2026-08-27';
    private const UNIT_COST = 30.96; // 1548 / 50kg

    public function run(): void
    {
        $csvFile = base_path(self::CSV_PATH);
        if (! file_exists($csvFile)) {
            $this->command->error("CSV not found at {$csvFile}");

            return;
        }

        $admin = User::where('email', 'admin@layrate.local')->first()
            ?? User::where('role', 'admin')->first()
            ?? User::first();

        if (! $admin) {
            $this->command->error('No users found. Run DatabaseSeeder first.');

            return;
        }

        $usersBefore = DB::table('users')->count();
        $hwBefore = DB::table('hardware_items')->count();
        $devBefore = DB::table('devices')->count();

        DB::transaction(function () use ($csvFile, $admin, $usersBefore, $hwBefore, $devBefore) {
            $this->purge();
            $this->rebuildStructure();
            $this->loadHistorical($csvFile, (int) $admin->id);
            $this->report($usersBefore, $hwBefore, $devBefore);
        });
    }

    private function purge(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // NOTE: DELETE (not TRUNCATE) — TRUNCATE is DDL in MySQL/MariaDB and
        // causes an implicit commit, which would break the outer transaction.
        foreach ([
            'egg_size_logs', 'egg_stock_batches', 'sensor_occupancy_readings',
            'mortality_log_hens', 'mortality_logs', 'production_logs',
            'environmental_logs', 'feed_consumption_logs', 'farm_feed_entries',
            'feed_batches', 'forecasts', 'forecast_runs', 'alerts',
            'pre_orders', 'notes', 'health_events', 'weight_checks',
            'culling_logs', 'removals', 'cage_transfers',
        ] as $t) {
            DB::table($t)->delete();
        }

        // Hardware-aware purge: slot/cage rows referenced by hardware_items
        // are NEVER deleted (FKs are cascadeOnDelete). Everything else
        // business-data is rebuilt fresh; preserved slots are reset to
        // 4 occupancy + fresh hens like all others in rebuildStructure().
        $keepSlotIds = DB::table('hardware_items')->whereNotNull('cage_slot_id')->pluck('cage_slot_id')->all();
        if (Schema::hasTable('hardware_item_cage_slot')) {
            $keepSlotIds = array_merge($keepSlotIds, DB::table('hardware_item_cage_slot')->pluck('cage_slot_id')->all());
        }
        $keepSlotIds = array_values(array_unique($keepSlotIds));
        $keepCageIds = DB::table('hardware_items')->whereNotNull('cage_id')->pluck('cage_id')->all();
        if (! empty($keepSlotIds)) {
            $keepCageIds = array_merge($keepCageIds, DB::table('cage_slots')->whereIn('id', $keepSlotIds)->pluck('cage_id')->all());
        }
        $keepCageIds = array_values(array_unique($keepCageIds));

        // Hens: all birds go; preserved slots get fresh hens in the rebuild.
        DB::table('hens')->delete();

        // Slots for A/B/C/D only, except hardware-referenced ones.
        $killCageIds = DB::table('cages')
            ->whereIn('cage_code', ['CAGE-A', 'CAGE-B', 'CAGE-C', 'CAGE-D'])
            ->pluck('id')->all();
        if (! empty($killCageIds)) {
            DB::table('cage_slots')->whereIn('cage_id', $killCageIds)
                ->when(! empty($keepSlotIds), fn ($q) => $q->whereNotIn('id', $keepSlotIds))
                ->delete();
        }

        // Delete CAGE-D only when nothing hardware-related points at it.
        $dId = DB::table('cages')->where('cage_code', 'CAGE-D')->value('id');
        if ($dId && ! in_array($dId, $keepCageIds)) {
            DB::table('cages')->where('id', $dId)->delete();
        }

        // Preserved slots in non-rebuilt cages (e.g. legacy holding cages)
        // lose their hens above, so reset occupancy to 0. Slots in A/B/C
        // are reset to 4 by rebuildStructure().
        $specIds = DB::table('cages')->whereIn('cage_code', ['CAGE-A', 'CAGE-B', 'CAGE-C'])->pluck('id')->all();
        DB::table('cage_slots')->whereIn('id', $keepSlotIds)->whereNotIn('cage_id', $specIds)->update([
            'current_occupancy' => 0,
        ]);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function rebuildStructure(): void
    {
        $ts = '2026-03-23 08:00:00';
        $specs = [
            'CAGE-A' => ['rows' => 3, 'slots_per_row' => 20, 'max' => 4, 'cap' => 240],
            'CAGE-B' => ['rows' => 3, 'slots_per_row' => 12, 'max' => 4, 'cap' => 144],
            'CAGE-C' => ['rows' => 3, 'slots_per_row' => 12, 'max' => 4, 'cap' => 144],
        ];

        foreach ($specs as $code => $s) {
            $id = DB::table('cages')->where('cage_code', $code)->value('id');
            $row = [
                'location' => '', 'rows' => $s['rows'],
                'slots_per_row' => $s['slots_per_row'],
                'max_chickens_per_slot' => $s['max'],
                'total_capacity' => $s['cap'],
                'is_active' => 1,
                'created_at' => $ts, 'updated_at' => $ts,
            ];
            if ($id) {
                DB::table('cages')->where('id', $id)->update($row);
            } else {
                $id = DB::table('cages')->insertGetId(array_merge(['cage_code' => $code], $row));
            }

            // Create slots: slot_number sequential per cage. Slots already
            // present (hardware-preserved) are kept and reset to fresh state
            // instead of re-inserted (unique cage_id+slot_number).
            $existing = DB::table('cage_slots')->where('cage_id', $id)->pluck('current_occupancy', 'slot_number')->all();
            $slots = [];
            for ($r = 1; $r <= $s['rows']; $r++) {
                for ($c = 1; $c <= $s['slots_per_row']; $c++) {
                    $slotNumber = ($r - 1) * $s['slots_per_row'] + $c;
                    if (array_key_exists($slotNumber, $existing)) {
                        DB::table('cage_slots')->where('cage_id', $id)->where('slot_number', $slotNumber)->update([
                            'row_number' => $r, 'column_number' => $c,
                            'current_occupancy' => 4,
                            'created_at' => $ts, 'updated_at' => $ts,
                        ]);
                        continue;
                    }
                    $slots[] = [
                        'cage_id' => $id,
                        'row_number' => $r,
                        'column_number' => $c,
                        'slot_number' => $slotNumber,
                        'current_occupancy' => 4,
                        'created_at' => $ts, 'updated_at' => $ts,
                    ];
                }
            }
            foreach (array_chunk($slots, 500) as $chunk) {
                DB::table('cage_slots')->insert($chunk);
            }
        }
    }

    private function loadHistorical(string $csvFile, int $adminId): void
    {
        // ── Parse CSV grouped by date → cage ──
        $byDay = []; // [date][cage] = row
        $dates = [];
        if (($h = fopen($csvFile, 'r')) === false) {
            throw new \RuntimeException('Cannot open CSV');
        }
        $header = fgetcsv($h);
        while (($r = fgetcsv($h)) !== false) {
            $row = array_combine($header, $r);
            $byDay[$row['Date']][$row['Cage_Code']] = $row;
            $dates[$row['Date']] = true;
        }
        fclose($h);
        ksort($dates);
        $dates = array_keys($dates);

        $cageIds = [
            'CAGE-A' => DB::table('cages')->where('cage_code', 'CAGE-A')->value('id'),
            'CAGE-B' => DB::table('cages')->where('cage_code', 'CAGE-B')->value('id'),
            'CAGE-C' => DB::table('cages')->where('cage_code', 'CAGE-C')->value('id'),
        ];

        // Slots per cage ordered by slot_number.
        $slotsByCage = [];
        foreach ($cageIds as $code => $cid) {
            $slotsByCage[$code] = DB::table('cage_slots')
                ->where('cage_id', $cid)->orderBy('slot_number')->get();
        }

        // ── Hens: 4 per slot ──
        $henRows = [];
        $seq = 1;
        $ts = '2026-03-23 08:00:00';
        // hen registry: [cage][slot_id] => list of hen ids (in insert order)
        $slotHens = [];
        foreach (['CAGE-A', 'CAGE-B', 'CAGE-C'] as $code) {
            foreach ($slotsByCage[$code] as $slot) {
                $n = (int) $slot->slot_number;
                for ($k = 1; $k <= 4; $k++) {
                    $henRows[] = [
                        'chicken_id' => sprintf('CHK-2026-%05d', $seq),
                        'cage_slot_id' => $slot->id,
                        'tag_code' => sprintf('%s-S%02d-H%d', $code, $n, $k),
                        'date_acquired' => '2026-03-23',
                        'placement_date' => '2026-03-23',
                        'age_at_placement_weeks' => 35,
                        'flock_age_weeks' => 35,
                        'breed' => 'Dekalb White',
                        'sex' => 'hen',
                        'is_active' => 1,
                        'created_at' => $ts, 'updated_at' => $ts,
                    ];
                    $seq++;
                }
            }
        }
        foreach (array_chunk($henRows, 500) as $chunk) {
            DB::table('hens')->insert($chunk);
        }
        // Reload hen ids per slot (ordered by id).
        foreach (['CAGE-A', 'CAGE-B', 'CAGE-C'] as $code) {
            $slotHens[$code] = [];
            $hens = DB::table('hens')
                ->join('cage_slots', 'hens.cage_slot_id', '=', 'cage_slots.id')
                ->where('cage_slots.cage_id', $cageIds[$code])
                ->orderBy('hens.id')->select('hens.id', 'hens.cage_slot_id')->get();
            foreach ($hens as $hn) {
                $slotHens[$code][$hn->cage_slot_id][] = $hn->id;
            }
        }
        // Live occupancy tracker: [cage][slot_id] => live count + ordered slot list.
        $occ = [];
        $slotOrder = [];
        foreach (['CAGE-A', 'CAGE-B', 'CAGE-C'] as $code) {
            $occ[$code] = [];
            $slotOrder[$code] = [];
            foreach ($slotsByCage[$code] as $slot) {
                $occ[$code][$slot->id] = 4;
                $slotOrder[$code][] = $slot->id;
            }
        }
        // Round-robin pointer per cage for balanced mortality removal.
        $killPtr = ['CAGE-A' => 0, 'CAGE-B' => 0, 'CAGE-C' => 0];

        // ── Feed batches: 10x 1000kg Limcoma ──
        $batchDates = ['2026-03-20', '2026-04-05', '2026-04-21', '2026-05-07',
            '2026-05-23', '2026-06-08', '2026-06-24', '2026-07-10',
            '2026-07-26', '2026-08-11'];
        $batchIds = [];
        foreach ($batchDates as $i => $d) {
            $batchIds[] = DB::table('feed_batches')->insertGetId([
                'batch_code' => sprintf('LIM-2026-%02d', $i + 1),
                'brand' => 'Limcoma Laying Mash',
                'crude_protein' => 17.00,
                'total_quantity_kg' => 1100.00,
                'unit_cost' => 30.96,
                'low_stock_threshold' => 200.00,
                'date_received' => $d,
                'notes' => 'Limcoma Laying Mash 50kg sack @ P1548 (historical import)',
                'created_at' => $d.' 08:00:00', 'updated_at' => $d.' 08:00:00',
            ]);
        }
        $batchForDate = function (string $date) use ($batchDates, $batchIds): int {
            $pick = $batchIds[0];
            foreach ($batchDates as $i => $d) {
                if ($d <= $date) {
                    $pick = $batchIds[$i];
                }
            }

            return $pick;
        };

        // ── Daily loop ──
        $envBatch = [];

        // Production rows are inserted per day (132 rows/day) via insertGetId
        // so egg_size_logs can link immediately; volume is small per day.
        foreach ($dates as $date) {
            $isCorrected = ($date >= '2026-03-23' && $date <= '2026-03-28');
            $noteBase = 'Import: production_data_clean.csv';
            $prodNote = $isCorrected
                ? $noteBase.' | Humidity corrected from raw sensor (interpolated 65-70% ramp)'
                : $noteBase;

            // Live headcount at START of day (before today's deaths).
            $liveStart = [];
            foreach (['CAGE-A', 'CAGE-B', 'CAGE-C'] as $code) {
                $liveStart[$code] = array_sum($occ[$code]);
            }

            foreach (['CAGE-A', 'CAGE-B', 'CAGE-C'] as $code) {
                $csv = $byDay[$date][$code];
                $totalEggs = (int) $csv['Egg_Count'];
                $slots = $slotsByCage[$code];
                $n = count($slots);
                $base = intdiv($totalEggs, $n);
                $rem = $totalEggs % $n;

                $idx = 0;
                foreach ($slots as $slot) {
                    $eggs = $base + ($idx < $rem ? 1 : 0);
                    $hens = $occ[$code][$slot->id]; // start-of-day
                    $hdep = $hens > 0 ? round($eggs / $hens * 100, 2) : 0;
                    // 16:00 + slot offset minutes + deterministic seconds jitter.
                    $mins = $idx; // 0..59 fits in 16:00-17:30 window
                    $secs = crc32($date.$code.$slot->id) % 60;
                    $hh = 16 + intdiv($mins, 60);
                    $mm = str_pad($mins % 60, 2, '0', STR_PAD_LEFT);
                    $ss = str_pad((string) $secs, 2, '0', STR_PAD_LEFT);
                    $created = "{$date} {$hh}:{$mm}:{$ss}";

                    $plId = DB::table('production_logs')->insertGetId([
                        'cage_slot_id' => $slot->id,
                        'log_date' => $date,
                        'egg_count' => $eggs,
                        'hen_count' => $hens,
                        'hdep' => $hdep,
                        'recorded_by' => $adminId,
                        'notes' => $prodNote,
                        'logged_via' => 'unknown',
                        'is_demo' => 0,
                        'created_at' => $created,
                    ]);
                    if ($eggs > 0) {
                        DB::table('egg_size_logs')->insert([
                            'production_log_id' => $plId,
                            'egg_size' => 'unsorted',
                            'count' => $eggs,
                            'created_at' => $created, 'updated_at' => $created,
                        ]);
                    }
                    $idx++;
                }

                // Environmental log at noon.
                $envBatch[] = [
                    'cage_id' => $cageIds[$code],
                    'recorded_at' => $date.' 12:00:00',
                    'temperature_c' => $csv['Temperature_C'],
                    'humidity_pct' => $csv['Humidity_Percent'],
                    'is_override' => 1,
                    'is_demo' => 0,
                    'created_at' => $date.' 12:00:00',
                ];

                // Mortality for this cage/date.
                $deaths = (int) $csv['Mortality_Count'];
                if ($deaths > 0) {
                    $cageOff = $code === 'CAGE-A' ? 0 : ($code === 'CAGE-B' ? 2 : 4);
                    $j = crc32('mort'.$date.$code) % 60;
                    // 16:45 + small per-cage offset + deterministic jitter.
                    $created = date('Y-m-d H:i:s', strtotime("{$date} 16:45:00") + $cageOff * 60 + $j);
                    $mortRow = [
                        'cage_id' => $cageIds[$code],
                        'log_date' => $date,
                        'count' => $deaths,
                        'reason' => 'Unknown',
                        'notes' => null,
                        'recorded_by' => $adminId,
                        'created_at' => $created,
                    ];
                    // Pick hens: balanced round-robin across slots with live > 0.
                    $pivots = [];
                    for ($d = 0; $d < $deaths; $d++) {
                        $order = $slotOrder[$code];
                        $m = count($order);
                        $chosen = null;
                        for ($t = 0; $t < $m; $t++) {
                            $p = ($killPtr[$code] + $t) % $m;
                            $sid = $order[$p];
                            if ($occ[$code][$sid] > 0 && ! empty($slotHens[$code][$sid])) {
                                // pop one live hen (still marked active)
                                $hid = array_shift($slotHens[$code][$sid]);
                                $chosen = [$sid, $hid, $p];
                                break;
                            }
                        }
                        if (! $chosen) {
                            throw new \RuntimeException("No live hen left in {$code} on {$date}");
                        }
                        [$sid, $hid] = $chosen;
                        $occ[$code][$sid]--;
                        $killPtr[$code] = ($chosen[2] + 1) % $m;
                        DB::table('hens')->where('id', $hid)->update([
                            'is_active' => 0,
                            'deactivation_cause' => 'mortality',
                            'updated_at' => $created,
                        ]);
                        DB::table('cage_slots')->where('id', $sid)->update([
                            'current_occupancy' => $occ[$code][$sid],
                            'updated_at' => $created,
                        ]);
                        $pivots[] = [
                            'hen_id' => $hid,
                            'cage_slot_id' => $sid,
                            'created_at' => $created, 'updated_at' => $created,
                        ];
                    }
                    $mid = DB::table('mortality_logs')->insertGetId($mortRow);
                    foreach ($pivots as $pv) {
                        $pv['mortality_log_id'] = $mid;
                        DB::table('mortality_log_hens')->insert($pv);
                    }
                }
            }

            // ── Feed: 2x30kg farm entries + distributed splits ──
            foreach (['07:00:00', '15:00:00'] as $lt) {
                $batchId = $batchForDate($date);
                $fid = DB::table('farm_feed_entries')->insertGetId([
                    'log_date' => $date,
                    'log_time' => $lt,
                    'total_kg' => 30.00,
                    'unit_cost' => self::UNIT_COST,
                    'feed_batch_id' => $batchId,
                    'created_at' => "{$date} {$lt}",
                    'updated_at' => "{$date} {$lt}",
                ]);
                // Largest-remainder split by liveStart headcount.
                $totalHens = $liveStart['CAGE-A'] + $liveStart['CAGE-B'] + $liveStart['CAGE-C'];
                $totalCents = 3000;
                $exact = [];
                foreach (['CAGE-A', 'CAGE-B', 'CAGE-C'] as $code) {
                    $exact[$code] = $liveStart[$code] / $totalHens * 30.00 * 100;
                }
                $baseC = [];
                $rem = [];
                foreach ($exact as $code => $v) {
                    $baseC[$code] = (int) floor($v);
                    $rem[$code] = $v - $baseC[$code];
                }
                $left = $totalCents - array_sum($baseC);
                arsort($rem);
                $i = 0;
                $split = $baseC;
                foreach ($rem as $code => $_) {
                    if ($i < $left) {
                        $split[$code]++;
                    }
                    $i++;
                }
                $off = ['CAGE-A' => 10, 'CAGE-B' => 20, 'CAGE-C' => 30];
                foreach (['CAGE-A', 'CAGE-B', 'CAGE-C'] as $code) {
                    $kg = $split[$code] / 100;
                    $cts = date('Y-m-d H:i:s', strtotime("{$date} {$lt}") + $off[$code]);
                    DB::table('feed_consumption_logs')->insert([
                        'cage_id' => $cageIds[$code],
                        'feed_batch_id' => $batchId,
                        'log_date' => $date,
                        'log_time' => $lt,
                        'feed_consumed_kg' => $kg,
                        'source' => 'distributed',
                        'farm_feed_entry_id' => $fid,
                        'recorded_by' => $adminId,
                        'created_at' => $cts,
                    ]);
                }
            }

            // Flush env in chunks.
            if (count($envBatch) >= 300) {
                foreach (array_chunk($envBatch, 500) as $c) {
                    DB::table('environmental_logs')->insert($c);
                }
                $envBatch = [];
            }
        }

        if ($envBatch) {
            DB::table('environmental_logs')->insert($envBatch);
        }
    }

    private function report(int $usersBefore, int $hwBefore, int $devBefore): void
    {
        $c = fn ($t) => DB::table($t)->count();
        $eggs = DB::table('production_logs')
            ->join('cage_slots', 'production_logs.cage_slot_id', '=', 'cage_slots.id')
            ->join('cages', 'cage_slots.cage_id', '=', 'cages.id')
            ->select('cages.cage_code', DB::raw('SUM(production_logs.egg_count) e'), DB::raw('COUNT(*) n'))
            ->groupBy('cages.cage_code')->get();
        $feedKg = DB::table('feed_consumption_logs')->sum('feed_consumed_kg');
        $mort = DB::table('mortality_logs')->sum('count');
        $live = DB::table('hens')->where('is_active', 1)->count();

        $this->command->info('── Validation ──');
        $this->command->info('users: '.$c('users')." (before {$usersBefore})");
        $this->command->info('hardware_items: '.$c('hardware_items')." (before {$hwBefore})");
        $this->command->info('devices: '.$c('devices')." (before {$devBefore})");
        $this->command->info('cages: '.$c('cages').' | cage_slots: '.$c('cage_slots').' | hens total: '.$c('hens')." (live {$live})");
        $distinctDates = (int) DB::table('production_logs')->distinct()->count('log_date');
        $this->command->info('production_logs rows: '.$c('production_logs')." (per-slot; distinct dates={$distinctDates}, cage-day pairs=".($distinctDates * 3).' ≈ expect 474)');
        foreach ($eggs as $e) {
            $this->command->info("  {$e->cage_code}: rows={$e->n} eggs={$e->e}");
        }
        $this->command->info('total eggs: '.DB::table('production_logs')->sum('egg_count').' (expect 74990)');
        $this->command->info('environmental_logs: '.$c('environmental_logs').' (expect 474)');
        $this->command->info('mortality_logs rows: '.$c('mortality_logs')." deaths: {$mort} (expect 45 rows / 57)");
        $this->command->info('farm_feed_entries: '.$c('farm_feed_entries').' (expect 316)');
        $this->command->info('feed_consumption_logs: '.$c('feed_consumption_logs')." kg={$feedKg} (expect 948 / 9480)");
        $this->command->info('feed_batches: '.$c('feed_batches'));
        $this->command->info('range production: '.DB::table('production_logs')->min('log_date').' → '.DB::table('production_logs')->max('log_date'));
        $this->command->info('range env: '.DB::table('environmental_logs')->min('recorded_at').' → '.DB::table('environmental_logs')->max('recorded_at'));
    }
}
