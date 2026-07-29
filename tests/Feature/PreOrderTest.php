<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EggSizeLog;
use App\Models\EggStockBatch;
use App\Models\PreOrder;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PreOrderTest extends TestCase
{
    use RefreshDatabase;
    private User $user;
    private Cage $cage;
    private CageSlot $slot;

    private function createLog(int $eggCount, string $size = 'large', ?string $date = null): ProductionLog
    {
        $log = new ProductionLog();
        $log->cage_slot_id = $this->slot->id;
        $log->log_date = $date ?? now()->toDateString();
        $log->egg_count = $eggCount;
        $log->hen_count = 4;
        $log->save();

        $log->eggSizeLogs()->create([
            'egg_size' => $size,
            'count' => $eggCount,
        ]);

        return $log;
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
    public function pool_validation_rejects_over_limit()
    {
        // Log 50 large eggs
        $this->createLog(50);

        // Stock 30 of them — leaves 20 available
        EggStockBatch::createWithinPool([
            'egg_size' => 'large',
            'count' => 30,
            'harvested_date' => now()->toDateString(),
            'cage_id' => $this->cage->id,
        ]);

        $this->actingAs($this->user);

        // Pre-order for 25 should be rejected (only 20 available)
        $response = $this->post(route('eggs.preorders.store'), [
            'customer_name' => 'Test Customer',
            'egg_size' => 'large',
            'egg_count' => 25,
            'requested_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('egg_count');
        $this->assertEquals(0, PreOrder::count());
    }

    /** @test */
    public function pool_validation_accepts_within_limit()
    {
        $this->createLog(50);

        EggStockBatch::createWithinPool([
            'egg_size' => 'large',
            'count' => 30,
            'harvested_date' => now()->toDateString(),
            'cage_id' => $this->cage->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->post(route('eggs.preorders.store'), [
            'customer_name' => 'Test Customer',
            'egg_size' => 'large',
            'egg_count' => 18,
            'requested_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertEquals(1, PreOrder::count());
        $this->assertEquals(18, PreOrder::first()->egg_count);
    }

    /** @test */
    public function cancel_order_returns_stock_to_pool()
    {
        $this->createLog(50);

        EggStockBatch::createWithinPool([
            'egg_size' => 'large',
            'count' => 30,
            'harvested_date' => now()->toDateString(),
            'cage_id' => $this->cage->id,
        ]);

        $this->actingAs($this->user);

        // Create a pre-order for 10 (from 20 available)
        $order = PreOrder::createWithinPool([
            'customer_name' => 'Test',
            'egg_size' => 'large',
            'egg_count' => 10,
            'requested_date' => now()->addDay()->toDateString(),
        ]);

        // Pool should now have 10 available (20 - 10 committed)
        $this->assertEquals(10, EggStockBatch::getAvailablePoolForSize('large'));

        // Cancel the order
        $order->updateWithinPool(['status' => 'cancelled']);

        // Pool should be back to 20 (the cancelled order no longer counts)
        $this->assertEquals(20, EggStockBatch::getAvailablePoolForSize('large'));
    }

    /** @test */
    public function fulfill_order_frees_pool()
    {
        $this->createLog(50);

        EggStockBatch::createWithinPool([
            'egg_size' => 'large',
            'count' => 30,
            'harvested_date' => now()->toDateString(),
            'cage_id' => $this->cage->id,
        ]);

        $this->actingAs($this->user);

        $order = PreOrder::createWithinPool([
            'customer_name' => 'Test',
            'egg_size' => 'large',
            'egg_count' => 10,
            'requested_date' => now()->addDay()->toDateString(),
        ]);

        $this->assertEquals(10, EggStockBatch::getAvailablePoolForSize('large'));

        // Fullfil the order
        $order->updateWithinPool([
            'status' => 'fulfilled',
            'fulfillment_date' => now()->toDateString(),
        ]);

        // Pool should be back to 20 (fulfilled orders are not counted as pending)
        $this->assertEquals(20, EggStockBatch::getAvailablePoolForSize('large'));
    }

    /** @test */
    public function delete_order_returns_stock_to_pool()
    {
        $this->createLog(50);

        EggStockBatch::createWithinPool([
            'egg_size' => 'large',
            'count' => 30,
            'harvested_date' => now()->toDateString(),
            'cage_id' => $this->cage->id,
        ]);

        $this->actingAs($this->user);

        $order = PreOrder::createWithinPool([
            'customer_name' => 'Test',
            'egg_size' => 'large',
            'egg_count' => 10,
            'requested_date' => now()->addDay()->toDateString(),
        ]);

        $this->assertEquals(10, EggStockBatch::getAvailablePoolForSize('large'));

        // Delete the order
        $order->delete();

        // Pool should be back to 20
        $this->assertEquals(20, EggStockBatch::getAvailablePoolForSize('large'));
    }

    /** @test */
    public function concurrent_orders_do_not_over_commit()
    {
        $this->createLog(100);

        $this->actingAs($this->user);

        // Create first order of 60
        $order1 = PreOrder::createWithinPool([
            'customer_name' => 'Customer A',
            'egg_size' => 'large',
            'egg_count' => 60,
            'requested_date' => now()->addDay()->toDateString(),
        ]);

        $this->assertEquals(1, PreOrder::count());

        // Second order of 60 should fail (only 40 remaining)
        $this->expectException(\OverflowException::class);
        PreOrder::createWithinPool([
            'customer_name' => 'Customer B',
            'egg_size' => 'large',
            'egg_count' => 60,
            'requested_date' => now()->addDay()->toDateString(),
        ]);
    }

    /** @test */
    public function tray_count_labels()
    {
        $this->assertEquals('0 eggs', PreOrder::eggLabel(0));
        $this->assertEquals('1 egg', PreOrder::eggLabel(1));
        $this->assertEquals('1 dozen', PreOrder::eggLabel(12));
        $this->assertEquals('15 eggs (0.5 trays)', PreOrder::eggLabel(15));
        $this->assertEquals('1.5 dozen', PreOrder::eggLabel(18));
        $this->assertEquals('22 eggs (0.7 trays)', PreOrder::eggLabel(22));
        $this->assertEquals('2.5 dozen', PreOrder::eggLabel(30));
        $this->assertEquals('45 eggs (1.5 trays)', PreOrder::eggLabel(45));
        $this->assertEquals('5 dozens', PreOrder::eggLabel(60));
        $this->assertEquals('7.5 dozen', PreOrder::eggLabel(90));
    }

    /** @test */
    public function customer_name_is_required()
    {
        $this->createLog(50);

        $this->actingAs($this->user);

        $response = $this->post(route('eggs.preorders.store'), [
            'customer_name' => '',
            'egg_size' => 'large',
            'egg_count' => 10,
            'requested_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('customer_name');
    }

    /** @test */
    public function requested_date_allows_past_dates()
    {
        $this->createLog(50);

        $this->actingAs($this->user);

        $response = $this->post(route('eggs.preorders.store'), [
            'customer_name' => 'Test',
            'egg_size' => 'large',
            'egg_count' => 6,
            'requested_date' => now()->subDay()->toDateString(),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, PreOrder::count());
    }

    /** @test */
    public function egg_count_cannot_be_zero()
    {
        $this->createLog(50);

        $this->actingAs($this->user);

        $response = $this->post(route('eggs.preorders.store'), [
            'customer_name' => 'Test',
            'egg_size' => 'large',
            'egg_count' => 0,
            'requested_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('egg_count');
    }

    /** @test */
    public function pool_data_endpoint_returns_pools()
    {
        $this->createLog(100, 'large', now()->subDay()->toDateString());
        $this->createLog(50, 'small');

        $this->actingAs($this->user);

        $response = $this->getJson(route('eggs.preorders.pool-data'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['pools' => ['small', 'medium', 'large', 'jumbo']]);
        $this->assertEquals(100, $response->json('pools.large'));
        $this->assertEquals(50, $response->json('pools.small'));
    }

}
