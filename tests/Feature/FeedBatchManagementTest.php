<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeedBatchManagementTest extends TestCase
{
    private User $user;
    private Cage $cage;
    private Cage $testCage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::where('email', 'admin@layrate.local')->first();
        $cages = Cage::where('is_active', 1)->orderBy('cage_code')->get()->keyBy('cage_code');
        $this->cage = $cages->first();

        // A dedicated cage not in seed data — no pre-existing consumption log conflict
        $this->testCage = Cage::create([
            'cage_code' => 'CAGE-TEST',
            'location' => 'Test suite',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);
    }

    // ─── 1. Batch code auto-generation ───────────────────────────

    public function test_batch_code_auto_generates_sequential(): void
    {
        $year = now()->format('Y');

        $b1 = FeedBatch::create(['crude_protein' => 16.0, 'date_received' => today()]);
        $b2 = FeedBatch::create(['crude_protein' => 17.0, 'date_received' => today()]);
        $b3 = FeedBatch::create(['crude_protein' => 18.0, 'date_received' => today()]);

        $this->assertEquals("F-{$year}-001", $b1->batch_code);
        $this->assertEquals("F-{$year}-002", $b2->batch_code);
        $this->assertEquals("F-{$year}-003", $b3->batch_code);

        // All unique in DB
        $codes = FeedBatch::pluck('batch_code')->toArray();
        $this->assertCount(3 + 3 /* seeder */, array_unique($codes));
    }

    public function test_batch_code_ignores_passed_value_in_request(): void
    {
        $response = $this->actingAs($this->user)->post('/feed/batch', [
            'batch_code' => 'SHOULD-BE-IGNORED',
            'crude_protein' => 17.5,
            'date_received' => today()->toDateString(),
        ]);

        $response->assertSessionHas('success');

        // Confirm the auto-generated code was used, not the passed one
        $latest = FeedBatch::latest()->first();
        $year = now()->format('Y');
        $this->assertStringStartsWith("F-{$year}-", $latest->batch_code);
        $this->assertNotEquals('SHOULD-BE-IGNORED', $latest->batch_code);
    }

    // ─── 2. Year rollover ──────────────────────────────────────

    public function test_batch_code_resets_per_year(): void
    {
        // Simulate a batch from a previous year
        DB::table('feed_batches')->insert([
            'batch_code' => 'F-2025-999',
            'crude_protein' => 16.0,
            'date_received' => '2025-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $year = now()->format('Y');
        $batch = FeedBatch::create(['crude_protein' => 18.0, 'date_received' => today()]);

        // Resets to 001 for current year, not 1000
        $this->assertEquals("F-{$year}-001", $batch->batch_code);
    }

    // ─── 3. Concurrency (lockForUpdate) ─────────────────────────

    public function test_batch_code_generation_uses_lock_for_update(): void
    {
        DB::enableQueryLog();

        FeedBatch::create(['crude_protein' => 16.0, 'date_received' => today()]);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $hasLock = false;
        foreach ($queries as $q) {
            if (str_contains(strtolower($q['query']), 'for update')) {
                $hasLock = true;
                break;
            }
        }

        $this->assertTrue($hasLock, 'Expected lockForUpdate() in batch_code auto-generation');
    }

    public function test_no_duplicate_batch_codes_in_sequence(): void
    {
        $count = 10;
        for ($i = 0; $i < $count; $i++) {
            FeedBatch::create(['crude_protein' => 16.0, 'date_received' => today()]);
        }

        $codes = FeedBatch::where('crude_protein', 16.0)
            ->where('date_received', today())
            ->pluck('batch_code')
            ->toArray();

        $this->assertCount($count, $codes);
        $this->assertCount($count, array_unique($codes));
    }

    // ─── 4. Brand field ─────────────────────────────────────────

    public function test_brand_persists_and_displays(): void
    {
        $response = $this->actingAs($this->user)->post('/feed/batch', [
            'brand' => 'Premium Layer Feed',
            'crude_protein' => 17.0,
            'date_received' => today()->toDateString(),
        ]);
        $response->assertSessionHas('success');

        $batch = FeedBatch::where('brand', 'Premium Layer Feed')->first();
        $this->assertNotNull($batch);
        $this->assertEquals('Premium Layer Feed', $batch->brand);
    }

    public function test_brand_is_nullable(): void
    {
        $batch = FeedBatch::create(['crude_protein' => 16.0, 'date_received' => today()]);
        $this->assertNull($batch->brand);
    }

    // ─── 5. Cost calculation ────────────────────────────────────

    public function test_total_cost_accessor_returns_correct_product(): void
    {
        $batch = FeedBatch::create([
            'crude_protein' => 17.0,
            'total_quantity_kg' => 500,
            'unit_cost' => 25.50,
            'date_received' => today(),
        ]);

        $this->assertEqualsWithDelta(500 * 25.50, $batch->total_cost, 0.01);
    }

    public function test_total_cost_is_null_when_unit_cost_or_quantity_missing(): void
    {
        $noCost = FeedBatch::create(['crude_protein' => 17.0, 'total_quantity_kg' => 500, 'date_received' => today()]);
        $this->assertNull($noCost->total_cost);

        $noQty = FeedBatch::create(['crude_protein' => 17.0, 'unit_cost' => 25.50, 'date_received' => today()]);
        $this->assertNull($noQty->total_cost);
    }

    public function test_total_feed_cost_month_in_live_data_matches_db(): void
    {
        $batch = FeedBatch::create([
            'crude_protein' => 17.0,
            'total_quantity_kg' => 500,
            'unit_cost' => 25.50,
            'date_received' => today(),
        ]);

        FeedConsumptionLog::create([
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 10.0,
            'recorded_by' => $this->user->id,
        ]);

        FeedConsumptionLog::create([
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => now()->subDay()->toDateString(),
            'feed_consumed_kg' => 5.0,
            'recorded_by' => $this->user->id,
        ]);

        $expected = (10.0 + 5.0) * 25.50;

        $response = $this->actingAs($this->user)->get('/feed/live-data');
        $response->assertOk();
        $response->assertViewHas('totalFeedCostMonth', function ($val) use ($expected) {
            return abs((float) $val - $expected) < 0.01;
        });
    }

    // ─── 6. Remaining stock calculation ─────────────────────────

    public function test_remaining_kg_matches_total_minus_consumed(): void
    {
        $batch = FeedBatch::create([
            'crude_protein' => 17.0,
            'total_quantity_kg' => 100.0,
            'date_received' => today(),
        ]);

        $this->assertEqualsWithDelta(100.0, $batch->remaining_kg, 0.01);

        FeedConsumptionLog::create([
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 30.0,
            'recorded_by' => $this->user->id,
        ]);

        $this->assertEqualsWithDelta(70.0, $batch->fresh()->remaining_kg, 0.01);

        FeedConsumptionLog::create([
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => now()->subDay()->toDateString(),
            'feed_consumed_kg' => 20.0,
            'recorded_by' => $this->user->id,
        ]);

        $this->assertEqualsWithDelta(50.0, $batch->fresh()->remaining_kg, 0.01);
    }

    public function test_remaining_kg_is_null_when_total_quantity_null(): void
    {
        $batch = FeedBatch::create(['crude_protein' => 17.0, 'date_received' => today()]);
        $this->assertNull($batch->remaining_kg);
    }

    public function test_remaining_kg_never_goes_below_zero(): void
    {
        $batch = FeedBatch::create([
            'crude_protein' => 17.0,
            'total_quantity_kg' => 10.0,
            'date_received' => today(),
        ]);

        FeedConsumptionLog::create([
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 100.0,
            'recorded_by' => $this->user->id,
        ]);

        $this->assertEqualsWithDelta(0.0, $batch->fresh()->remaining_kg, 0.01);
    }

    // ─── 7. Low-stock alert ────────────────────────────────────

    public function test_low_stock_alert_created_when_threshold_crossed(): void
    {
        $batch = FeedBatch::create([
            'crude_protein' => 17.0,
            'total_quantity_kg' => 50.0,
            'low_stock_threshold' => 10.0,
            'date_received' => today(),
        ]);

        $this->actingAs($this->user)->post('/feed/consumption', [
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 45.0,
        ]);

        // Remaining = 5.0, threshold = 10.0 → low stock
        $this->assertEqualsWithDelta(5.0, $batch->fresh()->remaining_kg, 0.01);
        $this->assertTrue($batch->fresh()->is_low_stock);

        $alert = Alert::where('alert_type', 'low_stock')
            ->whereDate('triggered_at', today())
            ->first();

        $this->assertNotNull($alert);
        $this->assertStringContainsString($batch->batch_code, $alert->message);
        $this->assertStringContainsString('5', $alert->message);
        $this->assertStringContainsString('10', $alert->message);
    }

    public function test_low_stock_alert_not_created_when_threshold_not_crossed(): void
    {
        $batch = FeedBatch::create([
            'crude_protein' => 17.0,
            'total_quantity_kg' => 50.0,
            'low_stock_threshold' => 10.0,
            'date_received' => today(),
        ]);

        $this->actingAs($this->user)->post('/feed/consumption', [
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 5.0,
        ]);

        // Remaining = 45.0, threshold = 10.0 → not low stock
        $this->assertFalse($batch->fresh()->is_low_stock);

        $alert = Alert::where('alert_type', 'low_stock')->first();
        $this->assertNull($alert);
    }

    public function test_low_stock_alert_dedup_same_day(): void
    {
        $batch = FeedBatch::create([
            'crude_protein' => 17.0,
            'total_quantity_kg' => 50.0,
            'low_stock_threshold' => 10.0,
            'date_received' => today(),
        ]);

        // First consumption: crosses threshold → alert created
        $this->actingAs($this->user)->post('/feed/consumption', [
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 45.0,
        ]);

        $this->assertEquals(1, Alert::where('alert_type', 'low_stock')->count());

        // Second consumption on same day, still below threshold → no new alert
        $this->actingAs($this->user)->post('/feed/consumption', [
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 50.0, // update existing, remaining still 0
        ]);

        $this->assertEquals(1, Alert::where('alert_type', 'low_stock')->count(),
            'Should not create duplicate alert for same batch same day');
    }

    public function test_low_stock_alert_created_on_subsequent_day(): void
    {
        $batch = FeedBatch::create([
            'crude_protein' => 17.0,
            'total_quantity_kg' => 50.0,
            'low_stock_threshold' => 10.0,
            'date_received' => today(),
        ]);

        // Day 1: crosses threshold
        $this->actingAs($this->user)->post('/feed/consumption', [
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 45.0,
        ]);

        $this->assertEquals(1, Alert::where('alert_type', 'low_stock')->count());

        // Mark alert as read so a new one can be created on subsequent day
        Alert::where('alert_type', 'low_stock')->update(['is_read' => 1]);

        // Day 2: still below threshold → new alert (different day)
        $this->actingAs($this->user)->post('/feed/consumption', [
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => now()->addDay()->toDateString(),
            'feed_consumed_kg' => 1.0,
        ]);

        $this->assertEquals(2, Alert::where('alert_type', 'low_stock')->count(),
            'Should create new alert on subsequent day');
    }

    // ─── 8. Consumption CRUD ────────────────────────────────────

    public function test_store_consumption_creates_log(): void
    {
        $batch = FeedBatch::create(['crude_protein' => 17.0, 'date_received' => today()]);

        $response = $this->actingAs($this->user)->post('/feed/consumption', [
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 12.5,
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('feed_consumption_logs', [
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 12.5,
            'recorded_by' => $this->user->id,
        ]);
    }

    public function test_store_consumption_updates_existing_via_update_or_create(): void
    {
        $batch = FeedBatch::create(['crude_protein' => 17.0, 'date_received' => today()]);

        // Create
        $this->actingAs($this->user)->post('/feed/consumption', [
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 10.0,
        ]);

        $this->assertEquals(1, FeedConsumptionLog::where('cage_id', $this->testCage->id)->count());

        // Update via same cage+date — different batch, different kg
        $this->actingAs($this->user)->post('/feed/consumption', [
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 20.0,
        ]);

        // Still only one record
        $this->assertEquals(1, FeedConsumptionLog::where('cage_id', $this->testCage->id)->count());

        // Value was updated
        $this->assertDatabaseHas('feed_consumption_logs', [
            'cage_id' => $this->testCage->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 20.0,
        ]);
    }

    public function test_destroy_consumption_deletes_log(): void
    {
        $batch = FeedBatch::create(['crude_protein' => 17.0, 'date_received' => today()]);

        $this->actingAs($this->user)->post('/feed/consumption', [
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 10.0,
        ]);

        $log = FeedConsumptionLog::first();

        $response = $this->actingAs($this->user)->delete("/feed/consumption/{$log->id}");
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('feed_consumption_logs', ['id' => $log->id]);
    }

    // ─── Additional: store/update via HTTP ──────────────────────

    public function test_store_batch_via_http_persists_all_fields(): void
    {
        $response = $this->actingAs($this->user)->post('/feed/batch', [
            'brand' => 'Layer Booster',
            'crude_protein' => 18.5,
            'total_quantity_kg' => 1000.0,
            'unit_cost' => 30.00,
            'low_stock_threshold' => 50.0,
            'date_received' => today()->toDateString(),
            'notes' => 'Test batch',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('feed_batches', [
            'brand' => 'Layer Booster',
            'crude_protein' => 18.5,
            'total_quantity_kg' => 1000.0,
            'unit_cost' => 30.00,
            'low_stock_threshold' => 50.0,
            'notes' => 'Test batch',
        ]);
    }

    public function test_update_batch_via_http_updates_fields(): void
    {
        $batch = FeedBatch::create([
            'crude_protein' => 17.0,
            'total_quantity_kg' => 500.0,
            'unit_cost' => 25.00,
            'date_received' => today(),
        ]);

        $response = $this->actingAs($this->user)->put("/feed/batch/{$batch->id}", [
            'brand' => 'Updated Brand',
            'crude_protein' => 18.0,
            'total_quantity_kg' => 600.0,
            'unit_cost' => 30.00,
            'low_stock_threshold' => 20.0,
            'notes' => 'Updated notes',
        ]);

        $response->assertSessionHas('success');

        $batch->refresh();
        $this->assertEquals('Updated Brand', $batch->brand);
        $this->assertEquals(18.0, $batch->crude_protein);
        $this->assertEqualsWithDelta(600.0, $batch->total_quantity_kg, 0.01);
        $this->assertEqualsWithDelta(30.00, $batch->unit_cost, 0.01);
        $this->assertEqualsWithDelta(20.0, $batch->low_stock_threshold, 0.01);
        $this->assertEquals('Updated notes', $batch->notes);
    }
}
