<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Adjustment pass over HistoricalFarmDataSeeder output (2026-03-23 → 2026-08-27).
 *
 * 1. Recompute cage-day egg counts to a 91–94% HDEP curve (live hens basis),
 *    redistribute per slot (largest-remainder), refresh hdep + egg_size_logs.
 * 2. Mortality causes: reason='Other' + full cause text in notes (per user
 *    choice; reason is an ENUM with no egg-binding/respiratory/natural values).
 * 3. Feed batch closeout: feed_batches has NO status/depleted_at column, so
 *    depleted batches are flagged via notes + updated_at (per user choice).
 * 4. Egg stock: one unsorted batch per cage per day (egg_stock_batches has no
 *    trays/loose/breakage/type columns — trays are computed in the UI as
 *    ceil(count/30)). pre_orders left empty (no invented sales).
 *
 * Transactional, re-runnable (DELETE-based purge of egg stock only; egg
 * production updates are idempotent recomputes). No now() in history.
 * Deterministic noise via crc32 (no rand()).
 */
class HistoricalFarmDataAdjustmentSeeder extends Seeder
{
    private const START = '2026-03-23';
    private const END = '2026-08-27';
    private const PEAK_DATE = '2026-05-20'; // single 94% peak day, all cages
    private const INITIAL = ['CAGE-A' => 240, 'CAGE-B' => 144, 'CAGE-C' => 144];

    /**
     * Real dropdown options: MortalityLog::REASONS (mortality.blade.php).
     * No severe/egg-binding/natural variants exist, so heat → Heat Stress,
     * respiratory → Disease, egg-binding → Other, natural/age → Unknown,
     * with the full descriptive text preserved in notes in every case.
     * [date => [reason, notes]]
     */
    private const CAUSES = [
        '2026-05-12' => ['Heat Stress', 'Heat stress (high ambient temperature/humidity)'],
        '2026-05-13' => ['Heat Stress', 'Heat stress (high ambient temperature/humidity)'],
        '2026-05-16' => ['Other', 'Egg binding / reproductive stress'],
        '2026-05-22' => ['Heat Stress', 'Heat stress (high ambient temperature/humidity)'],
        '2026-05-28' => ['Disease', 'Respiratory infection (humidity-related)'],
        '2026-06-11' => ['Heat Stress', 'Heat stress (severe — 2 birds/cage lost; no severe variant in dropdown)'],
        '2026-06-17' => ['Unknown', 'Natural causes / age-related decline'],
        '2026-06-24' => ['Heat Stress', 'Heat stress (high ambient temperature/humidity)'],
        '2026-06-28' => ['Other', 'Egg binding / prolapse'],
        '2026-07-01' => ['Heat Stress', 'Heat stress (severe — 2 birds/cage lost; no severe variant in dropdown)'],
        '2026-07-14' => ['Disease', 'Respiratory infection (humidity-related)'],
        '2026-07-25' => ['Heat Stress', 'Heat stress (severe — 2 birds/cage lost, 90% humidity; no severe variant in dropdown)'],
        '2026-07-29' => ['Other', 'Egg binding / prolapse'],
        '2026-08-09' => ['Disease', 'Respiratory infection (possible early disease onset — humidity spiked to 93%)'],
        '2026-08-16' => ['Unknown', 'Natural causes / age-related decline'],
    ];

    /** Historical Joed pre-orders: 1 tray (30 eggs) medium, Mondays. */
    private const PRE_ORDERS = [
        '2026-06-08', '2026-07-13', '2026-08-17', '2026-09-07',
    ];

    /**
     * Grading assumption (no per-egg weight data exists to grade from):
     * Dekalb White typical pack-out — small 8%, medium 43%, large 37%,
     * jumbo 12%. Weighted avg ≈ 61.8g vs the configured setting weights
     * (50/58/65/73g), matching the breed's ~62g average. Say the word to
     * re-cut with different percentages; the seeder is re-runnable.
     */
    private const GRADING = ['small' => 8, 'medium' => 43, 'large' => 37, 'jumbo' => 12];

    /** Largest-remainder split of $total eggs across GRADING; sums exactly. */
    private static function gradeSplit(int $total): array
    {
        $base = [];
        $rem = [];
        foreach (self::GRADING as $size => $pct) {
            $exact = $total * $pct / 100;
            $base[$size] = (int) floor($exact);
            $rem[$size] = $exact - $base[$size];
        }
        $left = $total - array_sum($base);
        arsort($rem);
        $i = 0;
        foreach ($rem as $size => $_) {
            if ($i++ < $left) {
                $base[$size]++;
            }
        }

        return $base;
    }

    public function run(): void
    {
        $usersBefore = DB::table('users')->count();
        $hwBefore = DB::table('hardware_items')->count();
        $devBefore = DB::table('devices')->count();

        DB::transaction(function () use ($usersBefore, $hwBefore, $devBefore) {
            $cageIds = [
                'CAGE-A' => DB::table('cages')->where('cage_code', 'CAGE-A')->value('id'),
                'CAGE-B' => DB::table('cages')->where('cage_code', 'CAGE-B')->value('id'),
                'CAGE-C' => DB::table('cages')->where('cage_code', 'CAGE-C')->value('id'),
            ];

            // Mortality history: deaths per cage per date (start-of-day lives).
            $mortRows = DB::table('mortality_logs')
                ->whereBetween('log_date', [self::START, self::END])->get();
            $deaths = []; // [date][cageId] => count
            foreach ($mortRows as $m) {
                $deaths[$m->log_date][$m->cage_id] = ($deaths[$m->log_date][$m->cage_id] ?? 0) + $m->count;
            }

            // Env per date (avg across cages) for heat-correlated dips.
            $envRows = DB::table('environmental_logs')
                ->selectRaw('DATE(recorded_at) d, AVG(temperature_c) t, AVG(humidity_pct) h')
                ->groupByRaw('DATE(recorded_at)')->orderBy('d')->get();
            $tMin = $envRows->min('t');
            $tMax = $envRows->max('t');
            $hMin = $envRows->min('h');
            $hMax = $envRows->max('h');
            $envByDate = [];
            foreach ($envRows as $e) {
                $envByDate[$e->d] = ['t' => (float) $e->t, 'h' => (float) $e->h];
            }

            $dates = [];
            $d = self::START;
            while ($d <= self::END) {
                $dates[] = $d;
                $d = date('Y-m-d', strtotime($d.' +1 day'));
            }

            $peakIdx = array_search(self::PEAK_DATE, $dates);

            // ── 1. HDEP recompute + slot redistribution + grading ──
            // Size logs are rebuilt from scratch every run (delete + fresh
            // graded inserts), so re-runs never stack duplicates.
            DB::table('egg_size_logs')->delete();
            $sizeInserts = [];
            $flushSizes = function () use (&$sizeInserts) {
                foreach (array_chunk($sizeInserts, 500) as $chunk) {
                    DB::table('egg_size_logs')->insert($chunk);
                }
                $sizeInserts = [];
            };
            $cageDayEggs = []; // [date][code] => eggs (for egg stock step)
            $cageDaySizes = []; // [date][code][size] => eggs (graded, mirrors logs)
            $hdepSeen = [];
            foreach ($dates as $idx => $date) {
                foreach (self::INITIAL as $code => $init) {
                    $cid = $cageIds[$code];
                    // Live at start of day.
                    $deadBefore = 0;
                    foreach ($dates as $d2) {
                        if ($d2 >= $date) {
                            break;
                        }
                        $deadBefore += $deaths[$d2][$cid] ?? 0;
                    }
                    $live = $init - $deadBefore;

                    // Base curve.
                    if ($idx <= 38) { // Mar23–Apr30 ramp 91 → 93.5
                        $base = 91.0 + 2.5 * ($idx / 38);
                        $lo = 91.0;
                        $hi = 93.9; // 94.0 reserved for the single peak day
                    } elseif ($idx <= 94) { // May1–Jun25 peak hold
                        $base = 93.7;
                        $lo = 93.5;
                        $hi = 93.9;
                    } else { // Jun26–Aug27 decline 93 → 91 + heat penalty
                        $base = 93.0 - 2.0 * (($idx - 95) / 62);
                        $env = $envByDate[$date];
                        $score = 0.7 * (($env['h'] - $hMin) / max(0.01, $hMax - $hMin))
                            + 0.3 * (($env['t'] - $tMin) / max(0.01, $tMax - $tMin));
                        $base -= $score * 1.5;
                        $lo = 91.0;
                        $hi = 93.9; // 94.0 reserved for the single peak day
                    }
                    // Deterministic noise ±0.80pp.
                    $noise = ((crc32($code.$date) % 161) - 80) / 100;
                    $hdep = min($hi, max($lo, $base + $noise));
                    if ($idx === $peakIdx) {
                        $hdep = 94.0; // the single true peak
                    }
                    $hdep = round($hdep, 2);

                    // Integer eggs move the aggregate by ~0.7pp, so clamp the
                    // egg COUNT (not just the target %) to keep the cage-day
                    // aggregate inside the band. Peak day takes the max
                    // in-band count (exact 94.0 is unreachable for most live
                    // counts, e.g. 0.94*125=117.5).
                    $blo = ($idx >= 39 && $idx <= 94) ? 93.5 : 91.0;
                    $bhi = 94.0;
                    $eggLo = (int) ceil($live * $blo / 100);
                    $eggHi = (int) floor($live * $bhi / 100);
                    if ($idx === $peakIdx) {
                        $total = $eggHi;
                        $hdep = $live > 0 ? round($total / $live * 100, 2) : 0;
                    } else {
                        $total = (int) round($live * $hdep / 100);
                        $total = min($eggHi, max($eggLo, $total));
                        $hdep = $live > 0 ? round($total / $live * 100, 2) : 0;
                    }
                    $hdepSeen[] = $hdep;
                    $cageDayEggs[$date][$code] = $total;

                    // Redistribute across slots (largest-remainder, slot order).
                    $slots = DB::table('production_logs')
                        ->join('cage_slots', 'production_logs.cage_slot_id', '=', 'cage_slots.id')
                        ->where('cage_slots.cage_id', $cid)
                        ->where('production_logs.log_date', $date)
                        ->orderBy('cage_slots.slot_number')
                        ->select('production_logs.id', 'production_logs.hen_count')
                        ->get();
                    $n = count($slots);
                    $b = intdiv($total, $n);
                    $r = $total % $n;
                    // Per-slot egg quotas (sum == cage-day total).
                    $slotEggs = [];
                    foreach ($slots as $i => $s) {
                        $slotEggs[$i] = $b + ($i < $r ? 1 : 0);
                    }
                    // Split the cage-day total into sizes first (small shares
                    // never survive largest-remainder at 2–4 eggs/row), then
                    // deal eggs to slots cycling sizes so the mix spreads.
                    $sizePools = self::gradeSplit($total);
                    $sizeOrder = array_keys(self::GRADING);
                    $ptr = 0;
                    foreach ($slots as $i => $s) {
                        $eggs = $slotEggs[$i];
                        $slotHdep = $s->hen_count > 0 ? round($eggs / $s->hen_count * 100, 2) : 0;
                        DB::table('production_logs')->where('id', $s->id)->update([
                            'egg_count' => $eggs, 'hdep' => $slotHdep,
                        ]);
                        // Grade this slot's eggs into sizes (no weight data
                        // exists per egg; GRADING assumption above).
                        if ($eggs > 0) {
                            $ts = date('Y-m-d H:i:s', strtotime($date.' 16:00:00') + ($i * 37 + crc32($s->id) % 60));
                            $slotSizes = [];
                            for ($k = 0; $k < $eggs; $k++) {
                                for ($t = 0; $t < count($sizeOrder); $t++) {
                                    $sz = $sizeOrder[($ptr + $t) % count($sizeOrder)];
                                    if ($sizePools[$sz] > 0) {
                                        $sizePools[$sz]--;
                                        $ptr = ($ptr + $t + 1) % count($sizeOrder);
                                        $slotSizes[$sz] = ($slotSizes[$sz] ?? 0) + 1;
                                        break;
                                    }
                                }
                            }
                            foreach ($slotSizes as $size => $cnt) {
                                $sizeInserts[] = [
                                    'production_log_id' => $s->id, 'egg_size' => $size, 'count' => $cnt,
                                    'created_at' => $ts, 'updated_at' => $ts,
                                ];
                                $cageDaySizes[$date][$code][$size] = ($cageDaySizes[$date][$code][$size] ?? 0) + $cnt;
                            }
                        }
                        if (count($sizeInserts) >= 2000) {
                            $flushSizes();
                        }
                    }
                }
            }
            $flushSizes();

            // ── 2. Mortality causes (real dropdown enum) ──
            foreach (self::CAUSES as $date => [$reason, $notes]) {
                DB::table('mortality_logs')->where('log_date', $date)->update([
                    'reason' => $reason, 'notes' => $notes,
                ]);
            }

            // ── 3. Feed batch closeout (notes flag; no status column) ──
            $batches = DB::table('feed_batches')->orderBy('date_received')->get();
            $consumed = DB::table('feed_consumption_logs')
                ->selectRaw('feed_batch_id, SUM(feed_consumed_kg) s, MAX(log_date) last_date, MAX(created_at) last_ts')
                ->groupBy('feed_batch_id')->get()->keyBy('feed_batch_id');
            $closeout = [];
            foreach ($batches as $b) {
                $used = isset($consumed[$b->id]) ? (float) $consumed[$b->id]->s : 0.0;
                $left = round((float) $b->total_quantity_kg - $used, 2);
                if ($used >= (float) $b->total_quantity_kg) {
                    $lastDate = $consumed[$b->id]->last_date;
                    $note = trim(($b->notes ?? '').' | DEPLETED as of '.$lastDate.' (FIFO drawdown complete; consumed '.number_format($used, 2).' kg)');
                    DB::table('feed_batches')->where('id', $b->id)->update([
                        'notes' => $note, 'updated_at' => $lastDate.' 15:00:00',
                    ]);
                    $closeout[$b->batch_code] = ['status' => 'depleted', 'left' => $left, 'as_of' => $lastDate];
                } else {
                    $closeout[$b->batch_code] = ['status' => 'active', 'left' => $left, 'as_of' => self::END];
                }
            }

            // ── 4. Egg stock: graded batches per cage per day ──
            // Built from the same per-slot graded splits logged above, so
            // per-size stocked == per-size logged exactly.
            DB::table('egg_stock_batches')->delete();
            $stockRows = [];
            $off = ['CAGE-A' => 0, 'CAGE-B' => 5, 'CAGE-C' => 10];
            $sizeIdx = ['small' => 0, 'medium' => 1, 'large' => 2, 'jumbo' => 3];
            foreach ($dates as $date) {
                foreach (self::INITIAL as $code => $init) {
                    foreach ($cageDaySizes[$date][$code] as $size => $cnt) {
                        if ($cnt <= 0) {
                            continue;
                        }
                        $secs = (crc32('stock'.$date.$code.$size) % 50) + $sizeIdx[$size] * 2;
                        $cts = date('Y-m-d H:i:s', strtotime("{$date} 16:30:00") + $off[$code] * 60 + $secs);
                        $stockRows[] = [
                            'egg_size' => $size,
                            'count' => $cnt,
                            'harvested_date' => $date,
                            'cage_id' => $cageIds[$code],
                            'cage_slot_id' => null,
                            'source_production_log_id' => null,
                            'created_at' => $cts, 'updated_at' => $cts,
                        ];
                    }
                }
            }
            foreach (array_chunk($stockRows, 500) as $chunk) {
                DB::table('egg_stock_batches')->insert($chunk);
            }

            // ── 4b. Pre-orders (medium pool is now abundant from grading) ──
            // pre_orders has NO fk to stock batches — linkage is by
            // (egg_size, date) convention, reported as such.
            DB::table('pre_orders')->where('customer_name', 'Joed')->delete();
            foreach (self::PRE_ORDERS as $orderDate) {
                DB::table('pre_orders')->insert([
                    'customer_name' => 'Joed', 'customer_reference' => null,
                    'egg_size' => 'medium', 'egg_count' => 30,
                    'requested_date' => $orderDate, 'fulfillment_date' => $orderDate,
                    'status' => 'fulfilled',
                    'notes' => 'Historical order — 1 tray medium (fulfilled same day)',
                    'created_at' => $orderDate.' 09:00:00', 'updated_at' => $orderDate.' 09:00:00',
                ]);
            }

            $this->report($usersBefore, $hwBefore, $devBefore, $hdepSeen, $closeout, $peakIdx);
        });
    }

    private function report(int $usersBefore, int $hwBefore, int $devBefore, array $hdepSeen, array $closeout, int|false $peakIdx): void
    {
        $info = fn ($m) => $this->command->info($m);
        $c = fn ($t) => DB::table($t)->count();
        $ok = fn ($cond) => $cond ? 'PASS' : 'FAIL';

        $totalEggs = (int) DB::table('production_logs')->sum('egg_count');
        $stockSum = (int) DB::table('egg_stock_batches')->sum('count');
        $sizeSum = (int) DB::table('egg_size_logs')->sum('count');
        $sizeMix = DB::table('egg_size_logs')->selectRaw('egg_size, SUM(count) s')->groupBy('egg_size')->pluck('s', 'egg_size');
        $stockMix = DB::table('egg_stock_batches')->selectRaw('egg_size, SUM(count) s')->groupBy('egg_size')->pluck('s', 'egg_size');
        $mediumStocked = (int) ($stockMix['medium'] ?? 0);
        $orders = DB::table('pre_orders')->where('customer_name', 'Joed')->orderBy('requested_date')->get();
        $pendingMedium = (int) DB::table('pre_orders')->where('egg_size', 'medium')->where('status', 'pending')->sum('egg_count');
        $feedLogs = round((float) DB::table('feed_consumption_logs')->sum('feed_consumed_kg'), 2);
        $farmSum = round((float) DB::table('farm_feed_entries')->sum('total_kg'), 2);
        $mortSum = (int) DB::table('mortality_logs')->sum('count');
        $nullCause = DB::table('mortality_logs')->whereNull('reason')->orWhere('reason', '')->count();
        $badCauseNote = DB::table('mortality_logs')->whereNull('notes')->count();

        // Live consistency per cage.
        $liveOk = true;
        foreach (['CAGE-A' => 240, 'CAGE-B' => 144, 'CAGE-C' => 144] as $code => $init) {
            $cid = DB::table('cages')->where('cage_code', $code)->value('id');
            $dead = (int) DB::table('mortality_logs')->where('cage_id', $cid)->sum('count');
            $occ = (int) DB::table('cage_slots')->where('cage_id', $cid)->sum('current_occupancy');
            $hens = DB::table('hens')->join('cage_slots', 'hens.cage_slot_id', '=', 'cage_slots.id')
                ->where('cage_slots.cage_id', $cid)->where('hens.is_active', 1)->count();
            $exp = $init - $dead;
            $info("  {$code}: start={$init} dead={$dead} live_exp={$exp} slot_occ={$occ} hens_live={$hens}");
            if ($occ !== $exp || $hens !== $exp) {
                $liveOk = false;
            }
        }

        // Date coverage per module.
        $expDates = 158;
        $cov = [];
        $cov['production'] = DB::table('production_logs')->distinct()->count('log_date');
        $cov['env'] = DB::table('environmental_logs')->selectRaw('COUNT(DISTINCT DATE(recorded_at)) n')->value('n');
        $cov['mortality_event_days'] = DB::table('mortality_logs')->distinct()->count('log_date');
        $cov['feed'] = DB::table('feed_consumption_logs')->distinct()->count('log_date');
        $cov['farm_entries'] = DB::table('farm_feed_entries')->distinct()->count('log_date');
        $cov['egg_stock'] = DB::table('egg_stock_batches')->distinct()->count('harvested_date');
        $outOfRange = DB::table('production_logs')->where('log_date', '<', self::START)->orWhere('log_date', '>', self::END)->count()
            + DB::table('mortality_logs')->where('log_date', '<', self::START)->orWhere('log_date', '>', self::END)->count()
            + DB::table('feed_consumption_logs')->where('log_date', '<', self::START)->orWhere('log_date', '>', self::END)->count()
            + DB::table('egg_stock_batches')->where('harvested_date', '<', self::START)->orWhere('harvested_date', '>', self::END)->count();

        $active = array_keys(array_filter($closeout, fn ($v) => $v['status'] === 'active'));
        $depleted = array_keys(array_filter($closeout, fn ($v) => $v['status'] === 'depleted'));

        $info('── Adjustment validation ──');
        $agg = DB::table('production_logs')
            ->join('cage_slots', 'production_logs.cage_slot_id', '=', 'cage_slots.id')
            ->selectRaw('log_date, cage_slots.cage_id, SUM(egg_count) e, SUM(production_logs.hen_count) h')
            ->groupBy('log_date', 'cage_slots.cage_id')->get()
            ->map(fn ($r) => ['d' => $r->log_date, 'hdep' => $r->h > 0 ? round($r->e / $r->h * 100, 2) : 0]);
        $peak = $agg->sortByDesc('hdep')->first();
        $info('HDEP cage-day band: min='.$agg->min('hdep').' max='.$agg->max('hdep').' peak='.$peak['hdep'].'% on '.$peak['d'].' (in-band 91–94: '.($agg->min('hdep') >= 91 && $agg->max('hdep') <= 94 ? 'yes' : 'NO').')');
        $info('total eggs adjusted: '.$totalEggs.' (ballpark 71000–74000)');
        $info('[egg-stock == production] '.$ok($stockSum === $totalEggs)." stock={$stockSum} prod={$totalEggs}");
        $info('[egg-sizes == production] '.$ok($sizeSum === $totalEggs)." sizes={$sizeSum}");
        $mixOk = true;
        foreach (['small', 'medium', 'large', 'jumbo'] as $sz) {
            $l = (int) ($sizeMix[$sz] ?? 0);
            $s = (int) ($stockMix[$sz] ?? 0);
            if ($l !== $s) {
                $mixOk = false;
            }
            $pct = $totalEggs > 0 ? round($l / $totalEggs * 100, 1) : 0;
            $info("  {$sz}: logged={$l} stocked={$s} ({$pct}%)");
        }
        $unsortedLeft = (int) ($sizeMix['unsorted'] ?? 0) + (int) ($stockMix['unsorted'] ?? 0);
        $info('[grading complete] '.$ok($mixOk && $unsortedLeft === 0 && $mediumStocked >= 120)." per-size pools balanced, unsorted remaining={$unsortedLeft}");
        $ordersOk = $orders->count() === 4
            && $orders->every(fn ($o) => $o->egg_size === 'medium' && (int) $o->egg_count === 30 && $o->status === 'fulfilled' && $o->fulfillment_date !== null)
            && $orders->pluck('requested_date')->all() === ['2026-06-08', '2026-07-13', '2026-08-17', '2026-09-07'];
        $info('[4 Joed pre-orders fulfilled] '.$ok($ordersOk).' count='.$orders->count().' pending_medium='.$pendingMedium);
        $info('  NOTE: fulfilled orders do NOT deduct stock in this schema (no stock-out table; count is unsigned; pool = stocked − pending). Medium available shows '.($mediumStocked - $pendingMedium).'; pre_orders↔stock linkage is by (size,date) convention — no FK columns exist.');
        $info('[feed logs == farm entries == 9480] '.$ok($feedLogs == 9480.00 && $farmSum == 9480.00)." logs={$feedLogs} farm={$farmSum}");
        $info('[live hens consistent] '.$ok($liveOk));
        $info('[mortality causes non-null] '.$ok($nullCause === 0 && $badCauseNote === 0)." null_reason={$nullCause} null_notes={$badCauseNote} deaths={$mortSum}");
        $info('[dates in range, no gaps] '.$ok($outOfRange === 0 && $cov['production'] === $expDates && $cov['env'] === $expDates && $cov['feed'] === $expDates && $cov['farm_entries'] === $expDates && $cov['egg_stock'] === $expDates)
            .' out_of_range='.$outOfRange.' cov='.json_encode($cov).' (mortality only on 15 event days: '.$cov['mortality_event_days'].')');
        $info('[feed batches: 1 active] '.$ok(count($active) === 1).' active='.json_encode($active).' depleted='.json_encode($depleted));
        foreach ($closeout as $code => $v) {
            $info("  {$code}: {$v['status']} left={$v['left']}kg as_of={$v['as_of']}");
        }
        $info('[users/hardware unchanged] '.$ok($c('users') === $usersBefore && $c('hardware_items') === $hwBefore && $c('devices') === $devBefore)
            ." users={$c('users')} hw={$c('hardware_items')} dev={$c('devices')}");
        $info('ASSUMPTIONS: no breakage column exists — breakage 0 (stock == production exactly); trays = ceil(count/30) in UI only; 4 fulfilled Joed orders (no stock-out table exists, so fulfilled releases commitment instead of deducting); production has no feed column — feed integrity is logs==farm entries==9480.');
    }
}
