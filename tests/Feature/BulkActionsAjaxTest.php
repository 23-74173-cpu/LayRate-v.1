<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Hen;
use App\Models\CageSlot;

class BulkActionsAjaxTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\UserSeeder::class);
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);
        $this->seed(\Database\Seeders\StructuralDataSeeder::class);
        $this->seed(\Database\Seeders\DemoDataSeeder::class);
        $this->user = User::firstOrFail();
    }

    public function test_cull_ajax_returns_json()
    {
        $hen = Hen::where('is_active', true)->firstOrFail();
        $response = $this->actingAs($this->user)
            ->postJson('/chickens/cull', [
                'hen_id' => (string) $hen->id,
                'cull_date' => today()->toDateString(),
                'reason' => 'age',
                'notes' => 'Test cull AJAX',
            ]);
        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('hens', ['id' => $hen->id, 'is_active' => false]);
    }

    public function test_removal_ajax_returns_json()
    {
        $hen = Hen::where('is_active', true)->firstOrFail();
        $response = $this->actingAs($this->user)
            ->postJson('/chickens/removal', [
                'hen_id' => (string) $hen->id,
                'removal_date' => today()->toDateString(),
                'reason' => 'Sold',
                'destination' => 'Farm X',
                'notes' => 'Test removal AJAX',
            ]);
        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('hens', ['id' => $hen->id, 'is_active' => false]);
    }

    public function test_bulk_remove_ajax_returns_json()
    {
        $hens = Hen::where('is_active', true)->take(2)->get();
        $this->assertCount(2, $hens);
        $response = $this->actingAs($this->user)
            ->postJson('/chickens/remove', [
                'hen_ids' => $hens->pluck('id')->implode(','),
                'reason' => 'Disease',
                'notes' => 'Test bulk remove AJAX',
            ]);
        $response->assertStatus(200)->assertJson(['success' => true]);
        foreach ($hens as $hen) {
            $this->assertDatabaseHas('hens', ['id' => $hen->id, 'is_active' => false]);
        }
    }

    public function test_move_ajax_returns_json()
    {
        $hen = Hen::where('is_active', true)->firstOrFail();
        $dest = CageSlot::with('cage')
            ->get()
            ->filter(fn($s) => $s->id !== $hen->cage_slot_id && $s->remaining > 0)
            ->firstOrFail();
        $response = $this->actingAs($this->user)
            ->postJson('/chickens/move', [
                'hen_ids' => (string) $hen->id,
                'destination_slot_id' => $dest->id,
                'transfer_reason' => 'Test move AJAX',
            ]);
        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('hens', ['id' => $hen->id, 'cage_slot_id' => $dest->id]);
    }

    public function test_validation_errors_returned_as_json()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chickens/cull', []);
        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }
}
