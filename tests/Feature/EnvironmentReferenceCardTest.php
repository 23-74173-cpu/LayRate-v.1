<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnvironmentReferenceCardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Cage $cage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);
        $this->cage = Cage::create([
            'cage_code' => 'CAGE-R', 'location' => 'Test', 'rows' => 1,
            'slots_per_row' => 1, 'max_chickens_per_slot' => 4, 'total_capacity' => 4, 'is_active' => 1,
        ]);

        config(['environment.optimal' => ['temp_min' => 18, 'temp_max' => 24, 'hum_min' => 50, 'hum_max' => 70]]);
    }

    public function test_card_shows_current_next_to_optimal_with_status(): void
    {
        EnvironmentalLog::create(['cage_id' => $this->cage->id, 'recorded_at' => now(), 'temperature_c' => 28.4, 'humidity_pct' => 60]);

        $this->actingAs($this->user)
            ->get(route('environment.live-data'))
            ->assertOk()
            ->assertSee('IR Reference: Current vs. Optimal')
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
        EnvironmentalLog::create(['cage_id' => $this->cage->id, 'recorded_at' => now(), 'temperature_c' => 21, 'humidity_pct' => 55]);

        $this->actingAs($this->user)
            ->get(route('environment.live-data'))
            ->assertOk()
            ->assertSee('All within optimal range');
    }

    public function test_card_handles_no_readings(): void
    {
        $this->actingAs($this->user)
            ->get(route('environment.live-data'))
            ->assertOk()
            ->assertSee('No readings yet')
            ->assertSee('18–24 °C');
    }
}
