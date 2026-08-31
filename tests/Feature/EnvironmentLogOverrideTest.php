<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReportingDateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnvironmentLogOverrideTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    private function cage(string $code): Cage
    {
        return Cage::create([
            'cage_code' => $code,
            'location' => 'Test suite',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);
    }

    // UpdateLog persists the override as an is_override noon row and clears raws.
    public function test_update_log_writes_flagged_override_row(): void
    {
        $cage = $this->cage('OV-WRITE');
        $date = ReportingDateService::reportingDateString();

        $this->actingAs($this->user)->put(route('environment.logs.update', [$cage->id, $date]), [
            'temperature_c' => 19.4,
            'humidity_pct' => 63,
        ])->assertSessionHasNoErrors();

        $row = EnvironmentalLog::where('cage_id', $cage->id)->get();
        $this->assertCount(1, $row);
        $this->assertTrue((bool) $row->first()->is_override);
        $this->assertEquals(19.4, (float) $row->first()->temperature_c);
    }

    public function test_current_reporting_day_override_wins_over_newer_raw_in_liveData(): void
    {
        $cage = $this->cage('OV-LIVE');
        $repDay = ReportingDateService::reportingDateString();
        [, $end] = ReportingDateService::reportingDayWindow($repDay);

        // Override at noon UTC of the reporting date (inside the window by construction)
        $overrideAt = Carbon::parse($repDay, config('app.timezone', 'UTC'))->setHour(12)->setMinute(0)->setSecond(0)->toDateTimeString();
        // Newer raw reading, still inside the reporting-day window
        $rawAt = Carbon::parse($end, config('app.timezone', 'UTC'))->subSecond()->toDateTimeString();

        EnvironmentalLog::create(['cage_id' => $cage->id, 'recorded_at' => $overrideAt, 'temperature_c' => 21.5, 'humidity_pct' => 50, 'is_override' => 1]);
        EnvironmentalLog::create(['cage_id' => $cage->id, 'recorded_at' => $rawAt, 'temperature_c' => 33.0, 'humidity_pct' => 80, 'is_override' => 0]);

        $row = $this->actingAs($this->user)
            ->get(route('environment.live-data'))
            ->viewData('latestPerCage')
            ->where('cage.id', $cage->id)->first();

        $this->assertNotNull($row);
        $this->assertEquals(21.5, (float) $row->env->temperature_c, 'Override must win over a newer raw reading for the current reporting day');
        $this->assertEquals(50, (float) $row->env->humidity_pct);
    }

    public function test_nightly_aggregation_leaves_override_intact_and_excludes_it(): void
    {
        $cage = $this->cage('OV-NIGHT');
        $pastDay = Carbon::parse(ReportingDateService::reportingDateString(), config('app.timezone', 'UTC'))->subDay()->toDateString();

        EnvironmentalLog::create(['cage_id' => $cage->id, 'recorded_at' => "$pastDay 12:00:00", 'temperature_c' => 24.0, 'humidity_pct' => 60, 'is_override' => 1]);
        EnvironmentalLog::create(['cage_id' => $cage->id, 'recorded_at' => "$pastDay 08:00:00", 'temperature_c' => 30.0, 'humidity_pct' => 70, 'is_override' => 0]);
        EnvironmentalLog::create(['cage_id' => $cage->id, 'recorded_at' => "$pastDay 16:00:00", 'temperature_c' => 31.0, 'humidity_pct' => 75, 'is_override' => 0]);

        $this->artisan('environment:compute-daily-averages', ['--date' => $pastDay])->assertExitCode(0);

        $noon = EnvironmentalLog::where('cage_id', $cage->id)->where('recorded_at', "$pastDay 12:00:00")->first();
        $this->assertNotNull($noon);
        $this->assertTrue((bool) $noon->is_override, 'Nightly job must not replace the override row');
        $this->assertEquals(24.0, (float) $noon->temperature_c, 'Override value must be unchanged (not re-averaged)');
    }

    public function test_past_day_override_reflects_in_logs_average(): void
    {
        $cage = $this->cage('OV-PAST');
        $pastDay = Carbon::parse(ReportingDateService::reportingDateString(), config('app.timezone', 'UTC'))->subDay()->toDateString();

        EnvironmentalLog::create(['cage_id' => $cage->id, 'recorded_at' => "$pastDay 12:00:00", 'temperature_c' => 24.0, 'humidity_pct' => 60, 'is_override' => 1]);
        EnvironmentalLog::create(['cage_id' => $cage->id, 'recorded_at' => "$pastDay 08:00:00", 'temperature_c' => 30.0, 'humidity_pct' => 70, 'is_override' => 0]);
        EnvironmentalLog::create(['cage_id' => $cage->id, 'recorded_at' => "$pastDay 16:00:00", 'temperature_c' => 31.0, 'humidity_pct' => 75, 'is_override' => 0]);

        $logs = $this->actingAs($this->user)
            ->get(route('environment.logs', ['cage_id' => $cage->id, 'date_from' => $pastDay, 'date_to' => $pastDay]))
            ->viewData('summaryLogs');

        $this->assertCount(1, $logs);
        $entry = $logs->first();
        $this->assertEquals(24.0, (float) $entry->avg_temp, 'Past-day override must be the authoritative average');
        $this->assertEquals(1, (int) $entry->reading_count);
    }

    public function test_reporting_day_boundary_attribution_in_liveData(): void
    {
        Carbon::setTestNow('2026-08-22 18:00:00 UTC');
        try {
            [$start, ] = ReportingDateService::reportingDayWindow(ReportingDateService::reportingDateString());
            $rawAt = Carbon::parse($start, config('app.timezone', 'UTC'))->addHours(18)->toDateTimeString(); // in-window raw

            // Cage 1: override is one second BEFORE the reporting-day window -> NOT current
            $c1 = $this->cage('OV-B1');
            EnvironmentalLog::create(['cage_id' => $c1->id, 'recorded_at' => Carbon::parse($start, config('app.timezone', 'UTC'))->subSecond()->toDateTimeString(), 'temperature_c' => 10.0, 'humidity_pct' => 10, 'is_override' => 1]);
            EnvironmentalLog::create(['cage_id' => $c1->id, 'recorded_at' => $rawAt, 'temperature_c' => 33.0, 'humidity_pct' => 80, 'is_override' => 0]);

            // Cage 2: override exactly AT the window start -> IS current
            $c2 = $this->cage('OV-B2');
            EnvironmentalLog::create(['cage_id' => $c2->id, 'recorded_at' => $start, 'temperature_c' => 15.0, 'humidity_pct' => 40, 'is_override' => 1]);
            EnvironmentalLog::create(['cage_id' => $c2->id, 'recorded_at' => $rawAt, 'temperature_c' => 34.0, 'humidity_pct' => 85, 'is_override' => 0]);

            $rows = $this->actingAs($this->user)->get(route('environment.live-data'))->viewData('latestPerCage');

            $r1 = $rows->where('cage.id', $c1->id)->first();
            $this->assertEquals(33.0, (float) $r1->env->temperature_c, 'Out-of-window override must NOT beat the raw reading');

            $r2 = $rows->where('cage.id', $c2->id)->first();
            $this->assertEquals(15.0, (float) $r2->env->temperature_c, 'In-window (boundary-inclusive) override must win');
        } finally {
            Carbon::setTestNow();
        }
    }
}