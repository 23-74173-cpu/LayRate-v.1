<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\ProductionLog;
use App\Models\User;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_unknown_route_renders_custom_404(): void
    {
        $response = $this->get('/this-page-does-not-exist-xyz');

        $response->assertStatus(404);
        $response->assertSee('Page Not Found');
        $response->assertSee('Error 404');
    }

    public function test_non_admin_hitting_admin_route_gets_custom_403(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $cage = Cage::create([
            'cage_code' => 'ERR-403', 'location' => 'Test', 'rows' => 1,
            'slots_per_row' => 1, 'max_chickens_per_slot' => 4, 'total_capacity' => 4, 'is_active' => 1,
        ]);
        $slot = CageSlot::create([
            'cage_id' => $cage->id, 'slot_number' => 1, 'row_number' => 1, 'column_number' => 1,
        ]);
        $log = ProductionLog::create([
            'cage_slot_id' => $slot->id, 'log_date' => now()->toDateString(),
            'egg_count' => 1, 'hen_count' => 1, 'logged_via' => 'manual',
        ]);

        $response = $this->actingAs($user)->delete('/eggs/logging/'.$log->id);

        $response->assertStatus(403);
        $response->assertSee('Access Denied');
        $response->assertSee('Error 403');
    }
}
