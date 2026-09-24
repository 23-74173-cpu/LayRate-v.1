<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\HardwareItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IR Reference Card (live DHT22 reading vs. the standard optimal range) and
 * the Alert Thresholds input hints, which share the same optimal range.
 */
class EnvironmentReferenceCardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Cage $cage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);
        $this->cage = $this->cage('CAGE-R');

        config(['environment.optimal' => ['temp_min' => 18, 'temp_max' => 24, 'hum_min' => 50, 'hum_max' => 70]]);
    }

    private function cage(string $code): Cage
    {
        return Cage::create([
            'cage_code' => $code, 'location' => 'Test', 'rows' => 1,
            'slots_per_row' => 1, 'max_chickens_per_slot' => 4, 'total_capacity' => 4, 'is_active' => 1,
        ]);
    }

    private function dht22(Cage $cage): void
    {
        HardwareItem::create([
            'device_type' => 'DHT22', 'serial_number' => 'REF-DHT-' . $cage->id,
            'cage_id' => $cage->id, 'status' => 'active',
        ]);
    }

    private function reading(Cage $cage, float $temp, float $hum, bool $manual = false, $at = null): void
    {
        EnvironmentalLog::create([
            'cage_id' => $cage->id, 'recorded_at' => $at ?? now(),
            'temperature_c' => $temp, 'humidity_pct' => $hum, 'is_override' => $manual ? 1 : 0,
        ]);
    }

    public function test_card_shows_live_dht22_reading_next_to_optimal_with_status(): void
    {
        $this->dht22($this->cage);
        $this->reading($this->cage, 28.4, 60);

        $this->actingAs($this->user)
            ->get(route('environment.live-data'))
            ->assertOk()
            ->assertSee('IR Reference: Current vs. Optimal')
            ->assertSee('Current = latest DHT22 reading')
            ->assertSee('28.4 °C')
            ->assertSee('18–24 °C')
            ->assertSee('Above optimal by 4.4 °C')
            ->assertSee('60.0 %')
            ->assertSee('50–70 %')
            ->assertSee('Within optimal range')
            ->assertSee('Outside optimal range');
    }

    public function test_card_says_all_within_when_both_readings_are_ideal(): void
    {
        $this->dht22($this->cage);
        $this->reading($this->cage, 21, 55);

        $this->actingAs($this->user)
            ->get(route('environment.live-data'))
            ->assertOk()
            ->assertSee('All within optimal range');
    }

    public function test_card_ignores_manual_entries_and_cages_without_a_dht22(): void
    {
        $plain = $this->cage('CAGE-NOSENSOR');
        $this->dht22($this->cage);
        $this->reading($this->cage, 22.0, 60, false, now()->subMinutes(5));
        // A newer manual entry on the sensor cage, and a reading on a cage
        // with no DHT22: neither is a live DHT22 reading.
        $this->reading($this->cage, 35.0, 90, true);
        $this->reading($plain, 40.0, 95);

        $this->actingAs($this->user)
            ->get(route('environment.live-data'))
            ->assertOk()
            ->assertSee('22.0 °C')
            ->assertSee('60.0 %')
            ->assertSee('All within optimal range')
            ->assertDontSee('35.0 °C')
            ->assertDontSee('40.0 °C');
    }

    public function test_card_averages_several_sensors(): void
    {
        $second = $this->cage('CAGE-R2');
        $this->dht22($this->cage);
        $this->dht22($second);
        $this->reading($this->cage, 20.0, 50);
        $this->reading($second, 24.0, 70);

        $this->actingAs($this->user)
            ->get(route('environment.live-data'))
            ->assertOk()
            ->assertSee('average of the latest DHT22 reading from 2 sensors')
            ->assertSee('22.0 °C')
            ->assertSee('60.0 %');
    }

    public function test_card_marks_an_old_reading_as_stale(): void
    {
        $this->dht22($this->cage);
        $this->reading($this->cage, 21, 55, false, now()->subHours(2));

        $this->actingAs($this->user)
            ->get(route('environment.live-data'))
            ->assertOk()
            ->assertSee('Stale: no new sensor reading in the last 30 minutes.');
    }

    public function test_card_handles_no_readings(): void
    {
        $this->actingAs($this->user)
            ->get(route('environment.live-data'))
            ->assertOk()
            ->assertSee('No live DHT22 reading yet')
            ->assertSee('18–24 °C');
    }

    public function test_threshold_inputs_get_hints_from_the_same_range_and_stay_manual(): void
    {
        foreach (['temp_min' => 18, 'temp_max' => 30, 'hum_min' => 40, 'hum_max' => 70] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'label' => $key]);
        }

        $html = $this->actingAs($this->user)->get(route('environment'))->assertOk()->getContent();

        // One hint line per threshold input, fed by the same optimal range.
        $this->assertSame(4, substr_count($html, '<p class="text-xs mt-1 truncate" data-optimal-hint'));
        $this->assertStringContainsString('data-optimal-kind="temp"', $html);
        $this->assertStringContainsString('data-optimal-kind="hum"', $html);
        $this->assertStringContainsString('var optimal = {"temp_min":18,"temp_max":24,"hum_min":50,"hum_max":70};', $html);

        // The inputs keep their saved values and are not locked.
        $this->assertMatchesRegularExpression('/name="temp_max" data-optimal-kind="temp" step="0\.5"\s+value="30"/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="(temp|hum)_(min|max)"[^>]*(readonly|disabled)/', $html);
    }

    public function test_saving_a_value_outside_the_optimal_range_is_still_allowed(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('environment.thresholds'), ['temp_min' => 10, 'temp_max' => 35, 'hum_min' => 20, 'hum_max' => 95])
            ->assertOk();

        $this->assertEquals(35, Setting::thresholds()['temp_max']);
        $this->assertEquals(20, Setting::thresholds()['hum_min']);
    }
}
