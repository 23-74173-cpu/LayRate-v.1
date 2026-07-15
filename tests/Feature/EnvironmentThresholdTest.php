<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnvironmentThresholdTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Cage $cage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);

        Setting::firstOrCreate(['key' => 'temp_min'], ['value' => 18, 'label' => 'Temp Min']);
        Setting::firstOrCreate(['key' => 'temp_max'], ['value' => 30, 'label' => 'Temp Max']);
        Setting::firstOrCreate(['key' => 'hum_min'], ['value' => 40, 'label' => 'Hum Min']);
        Setting::firstOrCreate(['key' => 'hum_max'], ['value' => 70, 'label' => 'Hum Max']);

        $this->cage = Cage::create([
            'cage_code' => 'CAGE-T',
            'location' => 'Test',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);
    }

    private function createLog(float $temp, float $hum, ?string $at = null): EnvironmentalLog
    {
        return EnvironmentalLog::create([
            'cage_id' => $this->cage->id,
            'recorded_at' => $at ?? now(),
            'temperature_c' => $temp,
            'humidity_pct' => $hum,
        ]);
    }

    /** @test */
    public function environment_live_data_shows_alert_for_cold_reading()
    {
        $this->createLog(15.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('Temp Alert');
        $response->assertSee('Alert');
        $response->assertDontSee('Temp OK');
    }

    /** @test */
    public function environment_live_data_shows_watch_at_min_boundary()
    {
        $this->createLog(18.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('Temp Watch');
    }

    /** @test */
    public function environment_live_data_shows_ok_inside_range()
    {
        $this->createLog(24.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('Temp OK');
        $response->assertSee('Humidity OK');
    }

    /** @test */
    public function environment_live_data_shows_watch_at_max_boundary()
    {
        $this->createLog(30.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('Temp Watch');
    }

    /** @test */
    public function environment_live_data_shows_alert_for_hot_reading()
    {
        $this->createLog(31.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('Temp Alert');
    }

    /** @test */
    public function environment_live_data_shows_alert_for_low_humidity()
    {
        $this->createLog(24.0, 35.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('Humidity Alert');
    }

    /** @test */
    public function environment_logs_use_configured_thresholds()
    {
        $this->createLog(15.0, 55.0, now()->subHour());

        $response = $this->actingAs($this->user)
            ->get(route('environment.logs'));

        $response->assertOk();
        $response->assertSee('Alert');
        $response->assertDontSee('Normal');
    }

    /** @test */
    public function dashboard_shows_alert_for_cold_reading()
    {
        $this->createLog(15.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.stats'));

        $response->assertOk();
        $response->assertSee('Alert');
    }

    /** @test */
    public function dashboard_shows_watch_at_min_boundary()
    {
        $this->createLog(18.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.stats'));

        $response->assertOk();
        $response->assertSee('Watch');
    }

    /** @test */
    public function dashboard_shows_normal_inside_range()
    {
        $this->createLog(24.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.stats'));

        $response->assertOk();
        $response->assertSee('Normal');
    }

    /** @test */
    public function dashboard_shows_watch_at_max_boundary()
    {
        $this->createLog(30.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.stats'));

        $response->assertOk();
        $response->assertSee('Watch');
    }

    /** @test */
    public function dashboard_shows_alert_for_hot_reading()
    {
        $this->createLog(31.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.stats'));

        $response->assertOk();
        $response->assertSee('Alert');
    }

    /** @test */
    public function dashboard_shows_alert_for_low_humidity()
    {
        $this->createLog(24.0, 35.0);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.stats'));

        $response->assertOk();
        $response->assertSee('Alert');
    }

    // ── Humidity boundary tests for Environment page ──

    /** @test */
    public function environment_live_data_shows_watch_at_humidity_min_boundary()
    {
        $this->createLog(24.0, 40.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('Humidity Watch');
    }

    /** @test */
    public function environment_live_data_shows_watch_at_humidity_max_boundary()
    {
        $this->createLog(24.0, 70.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('Humidity Watch');
    }

    /** @test */
    public function environment_live_data_shows_alert_for_high_humidity()
    {
        $this->createLog(24.0, 75.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('Humidity Alert');
        $response->assertDontSee('Humidity OK');
    }

    // ── Humidity boundary tests for Dashboard ──

    /** @test */
    public function dashboard_shows_watch_at_humidity_min_boundary()
    {
        $this->createLog(24.0, 40.0);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.stats'));

        $response->assertOk();
        $response->assertSee('Watch');
    }

    /** @test */
    public function dashboard_shows_watch_at_humidity_max_boundary()
    {
        $this->createLog(24.0, 70.0);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.stats'));

        $response->assertOk();
        $response->assertSee('Watch');
    }

    /** @test */
    public function dashboard_shows_alert_for_high_humidity()
    {
        $this->createLog(24.0, 75.0);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.stats'));

        $response->assertOk();
        $response->assertSee('Alert');
    }
}
