<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\Alert;
use App\Models\EnvironmentalLog;
use App\Models\HardwareItem;
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

        // Max-only thresholds. A leftover legacy minimum row must be ignored.
        Setting::firstOrCreate(['key' => 'temp_max'], ['value' => 30, 'label' => 'Temp Max']);
        Setting::firstOrCreate(['key' => 'hum_max'], ['value' => 70, 'label' => 'Hum Max']);
        Setting::firstOrCreate(['key' => 'temp_min'], ['value' => 18, 'label' => 'Temp Min (legacy)']);
        Setting::firstOrCreate(['key' => 'hum_min'], ['value' => 40, 'label' => 'Hum Min (legacy)']);

        $this->cage = Cage::create([
            'cage_code' => 'CAGE-T',
            'location' => 'Test',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);

        // Live cards render only for cages with an active DHT22.
        HardwareItem::create([
            'device_type' => 'DHT22', 'serial_number' => 'THR-DHT-1',
            'cage_id' => $this->cage->id, 'status' => 'active',
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
    public function environment_live_data_shows_ok_for_cold_reading()
    {
        $this->createLog(15.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('OK');
        $response->assertSee('Normal');
        $response->assertDontSee('Watch');
    }

    /** @test */
    public function environment_live_data_ignores_legacy_temperature_minimum()
    {
        $this->createLog(18.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('Normal');
        $response->assertDontSee('Watch');
    }

    /** @test */
    public function environment_live_data_shows_ok_inside_range()
    {
        $this->createLog(24.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('OK');
        $response->assertSee('Normal');
        $response->assertDontSee('Alert');
        $response->assertDontSee('Watch');
    }

    /** @test */
    public function environment_live_data_shows_watch_at_max_boundary()
    {
        $this->createLog(30.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('Watch');
        $response->assertSee('OK');
        $response->assertDontSee('Alert');
    }

    /** @test */
    public function environment_live_data_shows_alert_for_hot_reading()
    {
        $this->createLog(31.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('Alert');
        $response->assertSee('OK');
        $response->assertDontSee('Watch');
    }

    /** @test */
    public function environment_live_data_shows_ok_for_low_humidity()
    {
        $this->createLog(24.0, 35.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('OK');
        $response->assertSee('Normal');
        $response->assertDontSee('Watch');
    }

    /** @test */
    public function environment_logs_use_configured_thresholds()
    {
        $this->createLog(32.0, 55.0, now()->subHour());

        $response = $this->actingAs($this->user)
            ->get(route('environment.logs'));

        $response->assertOk();
        $response->assertSee('Alert');
        $response->assertDontSee('Normal');
    }

    /** @test */
    public function dashboard_shows_normal_for_cold_reading()
    {
        $this->createLog(15.0, 55.0);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.stats'));

        $response->assertOk();
        $response->assertSee('Normal');
        $response->assertDontSee('Watch');
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
    public function dashboard_shows_normal_for_low_humidity()
    {
        $this->createLog(24.0, 35.0);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.stats'));

        $response->assertOk();
        $response->assertSee('Normal');
        $response->assertDontSee('Watch');
    }

    // ── Humidity boundary tests for Environment page ──

    /** @test */
    public function environment_live_data_ignores_legacy_humidity_minimum()
    {
        $this->createLog(24.0, 40.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('Normal');
        $response->assertDontSee('Watch');
    }

    /** @test */
    public function environment_live_data_shows_watch_at_humidity_max_boundary()
    {
        $this->createLog(24.0, 70.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('Watch');
        $response->assertSee('OK');
        $response->assertDontSee('Alert');
    }

    /** @test */
    public function environment_live_data_shows_alert_for_high_humidity()
    {
        $this->createLog(24.0, 75.0);

        $response = $this->actingAs($this->user)
            ->get(route('environment.live-data'));

        $response->assertOk();
        $response->assertSee('Alert');
        $response->assertDontSee('Watch');
    }

    // ── Humidity boundary tests for Dashboard ──

    /** @test */
    public function dashboard_ignores_legacy_humidity_minimum()
    {
        $this->createLog(24.0, 40.0);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.stats'));

        $response->assertOk();
        $response->assertSee('Normal');
        $response->assertDontSee('Watch');
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

    // ── Persistent alert generation (EnvironmentAlertService) ──

    /** @test */
    public function only_readings_above_the_maximums_create_alerts()
    {
        \App\Services\EnvironmentAlertService::check($this->createLog(10.0, 20.0, now()->subMinutes(2)));
        \App\Services\EnvironmentAlertService::check($this->createLog(30.0, 70.0, now()->subMinute()));
        $this->assertSame(0, Alert::count());

        \App\Services\EnvironmentAlertService::check($this->createLog(31.5, 75.0));

        $this->assertSame(['humidity_high', 'temperature_high'], Alert::orderBy('alert_type')->pluck('alert_type')->all());
        $this->assertSame(0, Alert::whereIn('alert_type', ['temperature_low', 'humidity_low'])->count());
    }

    /** @test */
    public function existing_low_alert_history_is_preserved()
    {
        $legacy = Alert::create([
            'cage_id' => $this->cage->id, 'alert_type' => 'temperature_low',
            'message' => 'Temperature 15°C is below minimum threshold (18°C)',
            'is_read' => 0, 'triggered_at' => now()->subDays(3),
        ]);

        \App\Services\EnvironmentAlertService::check($this->createLog(15.0, 55.0));

        $this->assertSame(1, Alert::count());
        $this->assertNotNull($legacy->fresh());
    }
}
