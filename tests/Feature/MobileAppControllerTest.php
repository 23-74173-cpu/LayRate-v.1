<?php

namespace Tests\Feature;

use App\Http\Controllers\MobileAppController;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EnvironmentalLog;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MobileAppControllerTest extends TestCase
{
    private User $user;
    private Cage $cage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'operator']);

        $this->cage = Cage::create([
            'cage_code' => 'CAGE-MOB',
            'location' => 'Test',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);

        Route::get('/_test/mobile/dashboard-status', [MobileAppController::class, 'dashboardStatus']);
    }

    public function test_dashboard_status_returns_todays_data(): void
    {
        $slot = $this->cage->cageSlots()->first();
        if (!$slot) {
            $slot = CageSlot::create([
                'cage_id' => $this->cage->id,
                'slot_number' => 1,
                'row_number' => 1,
                'column_number' => 1,
                'current_occupancy' => 0,
            ]);
        }

        EnvironmentalLog::create([
            'cage_id'       => $this->cage->id,
            'recorded_at'   => now(),
            'temperature_c' => 25.5,
            'humidity_pct'  => 60.0,
        ]);

        ProductionLog::create([
            'cage_slot_id' => $slot->id,
            'log_date'     => now()->toDateString(),
            'egg_count'    => 42,
            'hen_count'    => 10,
            'hdep'         => 420.0,
            'recorded_by'  => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->getJson('/_test/mobile/dashboard-status')
            ->assertOk()
            ->assertJson([
                'temperature' => 25.5,
                'humidity'    => 60.0,
                'egg_count'   => 42,
            ]);
    }

    public function test_dashboard_status_returns_zeroes_when_no_data(): void
    {
        $this->actingAs($this->user)
            ->getJson('/_test/mobile/dashboard-status')
            ->assertOk()
            ->assertJson([
                'temperature' => 0.0,
                'humidity'    => 0.0,
                'egg_count'   => 0,
            ]);
    }

    public function test_dashboard_status_returns_json_structure(): void
    {
        $this->actingAs($this->user)
            ->getJson('/_test/mobile/dashboard-status')
            ->assertJsonStructure(['temperature', 'humidity', 'egg_count']);
    }
}
