<?php

namespace Tests\Feature;

use App\Http\Controllers\ReportController;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EggSizeLog;
use App\Models\Hen;
use App\Models\ProductionLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\FcrCalculator;
use App\Services\ProductionTimelineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The FCR egg-mass sums and the production report rows are computed in SQL
 * instead of from hydrated models. These tests rebuild the old in-PHP
 * results on tricky data and require the new results to match them.
 */
class FeedReportSqlAggregationTest extends TestCase
{
    use RefreshDatabase;

    private Cage $cageA;
    private Cage $cageB;
    private Cage $cageOff;
    private array $slotsA = [];
    private CageSlot $slotB;
    private CageSlot $slotOff;
    private int $henN = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Setting::set('egg_weight_small', 50);
        Setting::set('egg_weight_medium', 57.5);
        Setting::set('egg_weight_large', 65.25);
        Setting::set('egg_weight_jumbo', 73);
        Setting::set('egg_weight_fallback', 61.7);
        \Illuminate\Support\Facades\Cache::flush();

        $this->cageA = $this->cage('CAGE-A', 3, 1);
        $this->cageB = $this->cage('CAGE-B', 1, 1);
        $this->cageOff = $this->cage('CAGE-OFF', 1, 0);
        foreach ([1, 2, 3] as $n) {
            $this->slotsA[$n] = $this->slot($this->cageA, $n);
        }
        $this->slotB = $this->slot($this->cageB, 1);
        $this->slotOff = $this->slot($this->cageOff, 1);

        // Breeds: slot A1 two "ISA Brown", A2 "Hy-Line Brown" (so day 1 of
        // cage A is Mixed), A3 "Lohmann Brown-Classic" plus an inactive
        // "Dekalb White" (ignored), B1 "ISA Brown".
        $this->hen($this->slotsA[1], 'ISA Brown');
        $this->hen($this->slotsA[1], 'ISA Brown');
        $this->hen($this->slotsA[2], 'Hy-Line Brown');
        $this->hen($this->slotsA[3], 'Lohmann Brown-Classic');
        $this->hen($this->slotsA[3], 'Dekalb White', false);
        $this->hen($this->slotB, 'ISA Brown');
    }

    private function cage(string $code, int $slots, int $active): Cage
    {
        return Cage::create(['cage_code' => $code, 'location' => 'T', 'rows' => 1, 'slots_per_row' => $slots,
            'max_chickens_per_slot' => 4, 'total_capacity' => 4 * $slots, 'is_active' => $active]);
    }

    private function slot(Cage $cage, int $n): CageSlot
    {
        return CageSlot::create(['cage_id' => $cage->id, 'slot_number' => $n, 'row_number' => 1, 'column_number' => $n, 'current_occupancy' => 2]);
    }

    private function hen(CageSlot $slot, string $breed, bool $active = true): void
    {
        $this->henN++;
        $hen = Hen::create(['tag_code' => 'AGG-' . $this->henN, 'breed' => $breed, 'flock_age_weeks' => 30,
            'date_acquired' => now()->subMonths(3)->toDateString(), 'placement_date' => now()->subMonths(3)->toDateString(),
            'age_at_placement_weeks' => 18, 'is_active' => $active ? 1 : 0]);
        $hen->cage_slot_id = $slot->id;
        $hen->save();
    }

    private function log(CageSlot $slot, string $date, int $eggs, int $hens = 4, array $sizes = [], bool $demo = false): ProductionLog
    {
        $log = new ProductionLog();
        $log->cage_slot_id = $slot->id;
        $log->log_date = $date;
        $log->egg_count = $eggs;
        $log->hen_count = $hens;
        $log->is_demo = $demo;
        $log->save();
        foreach ($sizes as $size => $count) {
            EggSizeLog::create(['production_log_id' => $log->id, 'egg_size' => $size, 'count' => $count]);
        }

        return $log;
    }

    private function seedLogs(): void
    {
        // Day 1: cage B's log is recorded first, so B comes before A that day.
        $this->log($this->slotB, '2026-09-01', 3, 4, ['large' => 3]);
        $this->log($this->slotsA[1], '2026-09-01', 7, 4, ['small' => 2, 'large' => 4, 'unsorted' => 1]);
        $this->log($this->slotsA[2], '2026-09-01', 5);                       // no size rows: fallback weight
        // Day 2: only slot A3 (Lohmann) logged for cage A.
        $this->log($this->slotsA[3], '2026-09-02', 4, 4, ['jumbo' => 1, 'medium' => 3]);
        $this->log($this->slotB, '2026-09-02', 0, 4);                        // zero eggs still a row
        // Excluded rows: demo log, and a log in an inactive cage (excluded from "all active cages" FCR only).
        $this->log($this->slotsA[1], '2026-09-02', 99, 4, ['large' => 99], true);
        $this->log($this->slotOff, '2026-09-02', 6, 4, ['small' => 6]);
        // A later week and month for the week/month buckets.
        $this->log($this->slotsA[1], '2026-10-03', 6, 4, ['medium' => 6]);
        $this->log($this->slotsA[2], '2026-10-04', 2);
    }

    /** Old egg-mass rule: per hydrated log via eggMassForLog(), grouped by period. */
    private function oldEggMassByPeriod($logs, string $groupBy): array
    {
        $out = [];
        foreach ($logs as $log) {
            $p = ProductionTimelineService::periodForDate($log->log_date, $groupBy);
            $out[$p] = ($out[$p] ?? 0) + FcrCalculator::eggMassForLog($log);
        }
        krsort($out);

        return array_map(fn ($v) => round($v, 3), $out);
    }

    public function test_fcr_egg_mass_matches_the_per_log_rule(): void
    {
        $this->seedLogs();
        $realA = ProductionLog::with('eggSizeLogs')->real()->whereHas('cageSlot', fn ($q) => $q->where('cage_id', $this->cageA->id))->get();
        $realActive = ProductionLog::with('eggSizeLogs')->real()->whereHas('cageSlot.cage', fn ($q) => $q->where('is_active', 1))->get();

        foreach (['day', 'week', 'month'] as $g) {
            $new = FcrCalculator::timeline($this->cageA, $g)->mapWithKeys(fn ($r) => [$r['period'] => $r['egg_mass_kg']])->all();
            $this->assertEquals($this->oldEggMassByPeriod($realA, $g), $new, "cage timeline $g");

            $newAll = FcrCalculator::timelineAll($g)->mapWithKeys(fn ($r) => [$r['period'] => $r['egg_mass_kg']])->all();
            $this->assertEquals($this->oldEggMassByPeriod($realActive, $g), $newAll, "all timeline $g");
        }

        // Since-bounded timeline drops earlier days.
        $since = FcrCalculator::timeline($this->cageA, 'day', Carbon::parse('2026-09-02'))->pluck('period')->all();
        $this->assertSame(['2026-10-04', '2026-10-03', '2026-09-02'], $since);

        // Hand check of one day: A1 = 2x50 + 4x65.25 + 1x61.7 (unsorted -> fallback) = 422.7 g,
        // A2 = 5 x 61.7 (no size rows) = 308.5 g  ->  0.731 kg
        $day1 = FcrCalculator::timeline($this->cageA, 'day')->firstWhere('period', '2026-09-01');
        $this->assertEqualsWithDelta(0.7312, $day1['egg_mass_kg'], 0.0005);
    }

    public function test_fcr_for_range_matches_per_log_rule(): void
    {
        $this->seedLogs();
        $batch = \App\Models\FeedBatch::create(['batch_code' => 'B1', 'crude_protein' => 17.0, 'date_received' => '2026-08-01']);
        foreach ([$this->cageA, $this->cageB] as $c) {
            \App\Models\FeedConsumptionLog::create(['cage_id' => $c->id, 'feed_batch_id' => $batch->id, 'log_date' => '2026-09-01',
                'feed_consumed_kg' => 1.5, 'recorded_by' => auth()->id()]);
        }
        $start = Carbon::parse('2026-09-01')->startOfDay();
        $end = Carbon::parse('2026-09-02')->endOfDay();

        $massA = ProductionLog::with('eggSizeLogs')->real()->whereHas('cageSlot', fn ($q) => $q->where('cage_id', $this->cageA->id))
            ->whereBetween('log_date', ['2026-09-01', '2026-09-02'])->get()->sum(fn ($l) => FcrCalculator::eggMassForLog($l));
        $this->assertSame(round(1.5 / $massA, 2), FcrCalculator::forCage($this->cageA, $start, $end));

        $massAll = ProductionLog::with('eggSizeLogs')->real()->whereHas('cageSlot.cage', fn ($q) => $q->where('is_active', 1))
            ->whereBetween('log_date', ['2026-09-01', '2026-09-02'])->get()->sum(fn ($l) => FcrCalculator::eggMassForLog($l));
        $this->assertSame(round(3.0 / $massAll, 2), FcrCalculator::forAllCages($start, $end));

        // No production in range -> null, as before.
        $this->assertNull(FcrCalculator::forCage($this->cageA, Carbon::parse('2020-01-01'), Carbon::parse('2020-01-02')));
    }

    public function test_production_report_rows_match_the_old_grouping(): void
    {
        $this->seedLogs();
        $allCages = Cage::orderBy('cage_code')->get();
        $cageIds = $allCages->pluck('id');

        $method = new \ReflectionMethod(ReportController::class, 'productionReport');
        $rows = $method->invoke(app(ReportController::class), null, null, $cageIds, $allCages, false)
            ->map(fn ($r) => [$r->date, $r->cage, $r->breed, $r->eggs, $r->hens, $r->hdep])->all();

        // Old algorithm: hydrate, group by (date, cage), newest first, stable.
        $old = ProductionLog::with(['cageSlot.cage', 'cageSlot.hens' => fn ($q) => $q->where('is_active', 1)])
            ->real()->whereHas('cageSlot', fn ($q) => $q->whereIn('cage_id', $cageIds))->get()
            ->groupBy(fn ($l) => $l->log_date->format('Y-m-d') . '-' . $l->cageSlot->cage->id)
            ->sortByDesc(fn ($g) => $g->first()->log_date->format('Y-m-d'))
            ->map(function ($g) {
                $breeds = $g->flatMap(fn ($l) => $l->cageSlot->hens->pluck('breed'))->filter()->unique();
                $eggs = $g->sum('egg_count');
                $hens = $g->sum('hen_count');

                return [$g->first()->log_date->format('m/d/Y'), $g->first()->cageSlot->cage->cage_code,
                    $breeds->count() > 1 ? 'Mixed' : ($breeds->first() ?? '—'), $eggs, $hens,
                    number_format($hens > 0 ? $eggs / $hens * 100 : 0, 1) . '%'];
            })->values()->all();

        $this->assertSame($old, $rows);

        // Spot checks of the cases this data was built for.
        $this->assertSame(['09/01/2026', 'CAGE-B'], array_slice($rows[array_search('09/01/2026', array_column($rows, 0))], 0, 2), 'cage B first on day 1');
        $day1A = collect($rows)->first(fn ($r) => $r[0] === '09/01/2026' && $r[1] === 'CAGE-A');
        $this->assertSame('Mixed', $day1A[2], 'two breeds logged that day');
        $this->assertSame(12, $day1A[3]);
        $day2A = collect($rows)->first(fn ($r) => $r[0] === '09/02/2026' && $r[1] === 'CAGE-A');
        $this->assertSame('Lohmann Brown-Classic', $day2A[2], 'only the slot that logged counts; inactive hen ignored');
        $this->assertSame(4, $day2A[3], 'demo log excluded');
    }
}
