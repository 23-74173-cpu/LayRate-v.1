<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EggSizeLog;
use App\Models\Hen;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EggSizeLogWiringTest extends TestCase
{
    use RefreshDatabase;
    private User $user;
    private Cage $cage;
    private CageSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);

        $this->cage = Cage::create([
            'cage_code' => 'CAGE-T',
            'location' => 'Test',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);

        $this->slot = CageSlot::create([
            'cage_id' => $this->cage->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 4,
        ]);

        for ($i = 0; $i < 4; $i++) {
            $hen = new Hen;
            $hen->chicken_id = 'T-HEN-' . uniqid();
            $hen->tag_code = 'T-' . uniqid();
            $hen->breed = 'ISA Brown';
            $hen->cage_slot_id = $this->slot->id;
            $hen->date_acquired = now()->subDays(30);
            $hen->placement_date = now()->subDays(30);
            $hen->age_at_placement_weeks = 0;
            $hen->flock_age_weeks = 20;
            $hen->is_active = 1;
            $hen->save();
        }
    }

    private function createLog(int $eggCount, ?string $date = null): ProductionLog
    {
        $log = new ProductionLog();
        $log->cage_slot_id = $this->slot->id;
        $log->log_date = $date ?? now()->toDateString();
        $log->egg_count = $eggCount;
        $log->hen_count = 4;
        $log->save();

        return $log;
    }

    /** @test */
    public function store_with_no_size_fields_creates_unsorted_size_log()
    {
        $log = $this->createLog(4);

        $this->actingAs($this->user);

        $this->post(route('eggs.logging.store'), [
            'cage_slot_id' => $this->slot->id,
            'log_date' => now()->toDateString(),
            'egg_count' => 4,
            'hen_count' => 4,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(1, EggSizeLog::count());
        $this->assertEquals('unsorted', EggSizeLog::first()->egg_size);
        $this->assertEquals(4, EggSizeLog::first()->count);
    }

    /** @test */
    public function store_with_exact_match_sizes_creates_size_logs()
    {
        $this->actingAs($this->user);

        $this->post(route('eggs.logging.store'), [
            'cage_slot_id' => $this->slot->id,
            'log_date' => now()->toDateString(),
            'egg_count' => 4,
            'hen_count' => 4,
            'size_small' => 1,
            'size_medium' => 1,
            'size_large' => 1,
            'size_jumbo' => 1,
        ])->assertSessionHasNoErrors();

        $log = ProductionLog::first();
        $this->assertNotNull($log);

        $sizeLogs = $log->eggSizeLogs;
        $this->assertCount(4, $sizeLogs);

        $bySize = $sizeLogs->keyBy('egg_size');
        $this->assertEquals(1, $bySize['small']->count);
        $this->assertEquals(1, $bySize['medium']->count);
        $this->assertEquals(1, $bySize['large']->count);
        $this->assertEquals(1, $bySize['jumbo']->count);
        $this->assertEquals(4, $sizeLogs->sum('count'));
    }

    /** @test */
    public function store_rejects_mismatched_size_sum()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('eggs.logging.store'), [
            'cage_slot_id' => $this->slot->id,
            'log_date' => now()->toDateString(),
            'egg_count' => 4,
            'hen_count' => 4,
            'size_small' => 1,
            'size_medium' => 1,
            'size_large' => 1,
            'size_jumbo' => 2,
        ]);

        $response->assertSessionHasErrors('size_breakdown');
        $this->assertEquals(0, ProductionLog::count());
        $this->assertEquals(0, EggSizeLog::count());
    }

    /** @test */
    public function store_with_some_sizes_zero_still_creates_size_logs()
    {
        $this->actingAs($this->user);

        $this->post(route('eggs.logging.store'), [
            'cage_slot_id' => $this->slot->id,
            'log_date' => now()->toDateString(),
            'egg_count' => 4,
            'hen_count' => 4,
            'size_small' => 0,
            'size_medium' => 0,
            'size_large' => 4,
            'size_jumbo' => 0,
        ])->assertSessionHasNoErrors();

        $log = ProductionLog::first();
        $sizeLogs = $log->eggSizeLogs;
        $this->assertCount(1, $sizeLogs);
        $this->assertEquals('large', $sizeLogs->first()->egg_size);
        $this->assertEquals(4, $sizeLogs->first()->count);
    }

    /** @test */
    public function update_replaces_size_logs()
    {
        $log = $this->createLog(4);
        $log->eggSizeLogs()->create(['egg_size' => 'large', 'count' => 4]);

        $this->actingAs($this->user);

        $this->put(route('eggs.logging.update', $log), [
            'log_date' => now()->toDateString(),
            'egg_count' => 4,
            'hen_count' => 4,
            'size_small' => 2,
            'size_medium' => 2,
            'size_large' => 0,
            'size_jumbo' => 0,
        ])->assertSessionHasNoErrors();

        $log->refresh();
        $sizeLogs = $log->eggSizeLogs;
        $this->assertCount(2, $sizeLogs);

        $bySize = $sizeLogs->keyBy('egg_size');
        $this->assertEquals(2, $bySize['small']->count);
        $this->assertEquals(2, $bySize['medium']->count);
        $this->assertFalse(isset($bySize['large']));
    }

    /** @test */
    public function update_removes_size_logs_when_all_cleared()
    {
        $log = $this->createLog(4);
        $log->eggSizeLogs()->create(['egg_size' => 'large', 'count' => 4]);

        $this->assertEquals(1, $log->eggSizeLogs()->count());

        $this->actingAs($this->user);

        $response = $this->put(route('eggs.logging.update', $log), [
            'log_date' => now()->toDateString(),
            'egg_count' => 4,
            'hen_count' => 4,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $sizeLogs = EggSizeLog::where('production_log_id', $log->id)->get();
        $this->assertEquals(1, $sizeLogs->count());
        $this->assertEquals('unsorted', $sizeLogs->first()->egg_size);
        $this->assertEquals(4, $sizeLogs->first()->count);
    }

    /** @test */
    public function update_rejects_mismatched_size_sum()
    {
        $log = $this->createLog(4);

        $this->actingAs($this->user);

        $response = $this->put(route('eggs.logging.update', $log), [
            'log_date' => now()->toDateString(),
            'egg_count' => 4,
            'hen_count' => 4,
            'size_small' => 1,
            'size_medium' => 1,
            'size_large' => 1,
            'size_jumbo' => 2,
        ]);

        $response->assertSessionHasErrors('size_breakdown');
        $this->assertEquals(0, EggSizeLog::count());
    }

    /** @test */
    public function cascade_delete_removes_size_logs()
    {
        $log = $this->createLog(4);
        $log->eggSizeLogs()->create(['egg_size' => 'large', 'count' => 4]);

        $this->assertEquals(1, EggSizeLog::count());

        $log->delete();

        $this->assertEquals(0, EggSizeLog::count());
    }
}
