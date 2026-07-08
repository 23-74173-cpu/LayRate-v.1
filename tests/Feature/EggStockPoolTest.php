<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EggStockBatch;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EggStockPoolTest extends TestCase
{
    use RefreshDatabase;
    private User $user;
    private Cage $cage;
    private CageSlot $slot;

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

    private function createStock(int $count, string $size = 'large'): EggStockBatch
    {
        return EggStockBatch::create([
            'cage_id' => $this->cage->id,
            'egg_size' => $size,
            'count' => $count,
            'harvested_date' => now()->toDateString(),
        ]);
    }

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
    }

    /** @test */
    public function pool_is_zero_when_nothing_logged()
    {
        $this->assertEquals(0, EggStockBatch::getAvailablePool());
    }

    /** @test */
    public function pool_matches_logged_when_nothing_stocked()
    {
        $this->createLog(50);

        $this->assertEquals(50, EggStockBatch::getAvailablePool());
    }

    /** @test */
    public function pool_is_logged_minus_stocked()
    {
        $this->createLog(100);
        $this->createStock(30);

        $this->assertEquals(70, EggStockBatch::getAvailablePool());
    }

    /** @test */
    public function pool_never_goes_below_zero()
    {
        $this->createLog(10);
        $this->createStock(999);

        $this->assertEquals(0, EggStockBatch::getAvailablePool());
    }

    /** @test */
    public function store_accepts_exact_match()
    {
        $this->createLog(50);

        $this->actingAs($this->user);

        $response = $this->postJson(route('eggs.stocks.store'), [
            'egg_size' => 'large',
            'count' => 50,
            'harvested_date' => now()->toDateString(),
            'cage_id' => $this->cage->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals(0, EggStockBatch::getAvailablePool());
    }

    /** @test */
    public function store_rejects_over_limit()
    {
        $this->createLog(50);

        $this->actingAs($this->user);

        $response = $this->postJson(route('eggs.stocks.store'), [
            'egg_size' => 'large',
            'count' => 51,
            'harvested_date' => now()->toDateString(),
            'cage_id' => $this->cage->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'errors' => ['count' => ['Only 50 egg(s) available to stock (logged but not yet stocked).']],
        ]);

        $this->assertEquals(0, EggStockBatch::count());
    }

    /** @test */
    public function store_rejects_when_pool_exhausted()
    {
        $this->createLog(50);

        $this->actingAs($this->user);

        $this->postJson(route('eggs.stocks.store'), [
            'egg_size' => 'large',
            'count' => 50,
            'harvested_date' => now()->toDateString(),
            'cage_id' => $this->cage->id,
        ])->assertStatus(200);

        $response = $this->postJson(route('eggs.stocks.store'), [
            'egg_size' => 'medium',
            'count' => 1,
            'harvested_date' => now()->toDateString(),
            'cage_id' => $this->cage->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'errors' => ['count' => ['Only 0 egg(s) available to stock (logged but not yet stocked).']],
        ]);
    }

    /** @test */
    public function update_accepts_decrease_without_pool_check()
    {
        $this->createLog(100);
        $stock = $this->createStock(50);

        $this->actingAs($this->user);

        $response = $this->putJson(route('eggs.stocks.update', $stock), [
            'egg_size' => 'large',
            'count' => 30,
            'harvested_date' => now()->toDateString(),
        ]);

        $response->assertStatus(302);
        $this->assertEquals(30, $stock->fresh()->count);
    }

    /** @test */
    public function update_rejects_increase_exceeding_available_pool()
    {
        $this->createLog(100);
        $stock = $this->createStock(50);

        $this->actingAs($this->user);

        $response = $this->putJson(route('eggs.stocks.update', $stock), [
            'egg_size' => 'large',
            'count' => 120,
            'harvested_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'errors' => ['count' => ['Only 50 additional egg(s) available to stock (logged but not yet stocked).']],
        ]);

        $this->assertEquals(50, $stock->fresh()->count);
    }

    /** @test */
    public function multiple_logs_and_stocks_accumulate_correctly()
    {
        $this->createLog(60, now()->subDay()->toDateString());
        $this->createLog(40, now()->toDateString());

        $this->assertEquals(100, EggStockBatch::getAvailablePool());

        $this->createStock(30);
        $this->assertEquals(70, EggStockBatch::getAvailablePool());

        $this->createStock(40, 'medium');
        $this->assertEquals(30, EggStockBatch::getAvailablePool());
    }
}
