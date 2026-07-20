<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class AlertControllerTest extends TestCase
{
    private User $admin;
    private Cage $cage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@layrate.local')->firstOrFail();
        $this->cage = Cage::where('cage_code', 'CAGE-A')->firstOrFail();
    }

    public function test_index_page_loads(): void
    {
        Alert::create([
            'cage_id'      => $this->cage->id,
            'alert_type'   => 'temperature_high',
            'message'      => 'Test notification alert',
            'is_read'      => false,
            'triggered_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Test notification alert');
    }

    public function test_table_partial_loads(): void
    {
        Alert::create([
            'cage_id'      => $this->cage->id,
            'alert_type'   => 'temperature_high',
            'message'      => 'Table test message',
            'is_read'      => false,
            'triggered_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('notifications.table'))
            ->assertOk()
            ->assertSee('Table test message');
    }

    public function test_mark_read_updates_alert(): void
    {
        $alert = Alert::create([
            'cage_id'      => $this->cage->id,
            'alert_type'   => 'temperature_high',
            'message'      => 'Mark as read',
            'is_read'      => false,
            'triggered_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('alerts.read', $alert))
            ->assertRedirect();

        $this->assertTrue($alert->fresh()->is_read);
    }

    public function test_mark_all_read_updates_all_alerts(): void
    {
        Alert::create([
            'cage_id'      => $this->cage->id,
            'alert_type'   => 'temperature_high',
            'message'      => 'Batch read 1',
            'is_read'      => false,
            'triggered_at' => now(),
        ]);
        Alert::create([
            'cage_id'      => $this->cage->id,
            'alert_type'   => 'humidity_low',
            'message'      => 'Batch read 2',
            'is_read'      => false,
            'triggered_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('alerts.read-all'))
            ->assertRedirect();

        $this->assertEquals(0, Alert::where('is_read', false)->count());
    }

    public function test_acknowledge_modal_stores_ids_in_session(): void
    {
        $alert = Alert::create([
            'cage_id'      => $this->cage->id,
            'alert_type'   => 'temperature_high',
            'message'      => 'Acknowledge test',
            'is_read'      => false,
            'triggered_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('alerts.acknowledge-modal'), [
                'ids' => [$alert->id],
            ])
            ->assertJson(['ok' => true]);
    }

    public function test_guest_redirected_to_login(): void
    {
        $this->get(route('notifications.index'))
            ->assertRedirect(route('login'));
    }
}
