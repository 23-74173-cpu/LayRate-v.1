<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\FarmFeedEntry;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\Hen;
use App\Models\ProductionLog;
use App\Models\User;
use App\Services\FcrCalculator;
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
        $latest = FeedBatch::orderByDesc('id')->first();
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

    public function test_multiple_consumption_entries_per_cage_per_day_persist(): void
    {
        $batch = FeedBatch::create(['crude_protein' => 17.0, 'date_received' => today()]);

        // First entry (morning)
        $this->actingAs($this->user)->post('/feed/consumption', [
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'log_time' => '08:00',
            'feed_consumed_kg' => 10.0,
        ]);

        // Second entry (afternoon) — same cage + date, different time
        $this->actingAs($this->user)->post('/feed/consumption', [
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'log_time' => '16:00',
            'feed_consumed_kg' => 20.0,
        ]);

        // Both records persist (no unique constraint violation)
        $this->assertEquals(2, FeedConsumptionLog::where('cage_id', $this->testCage->id)->count());

        $this->assertDatabaseHas('feed_consumption_logs', [
            'cage_id' => $this->testCage->id,
            'log_date' => today()->toDateString(),
            'log_time' => '08:00:00',
            'feed_consumed_kg' => 10.0,
            'source' => 'direct',
        ]);

        $this->assertDatabaseHas('feed_consumption_logs', [
            'cage_id' => $this->testCage->id,
            'log_date' => today()->toDateString(),
            'log_time' => '16:00:00',
            'feed_consumed_kg' => 20.0,
            'source' => 'direct',
        ]);
    }

    public function test_store_consumption_without_time_treats_it_as_unspecified(): void
    {
        $batch = FeedBatch::create(['crude_protein' => 17.0, 'date_received' => today()]);

        $this->actingAs($this->user)->post('/feed/consumption', [
            'cage_id' => $this->testCage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 5.0,
        ]);

        $this->assertDatabaseHas('feed_consumption_logs', [
            'cage_id' => $this->testCage->id,
            'log_date' => today()->toDateString(),
            'log_time' => null,
            'feed_consumed_kg' => 5.0,
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

    // ─── Whole-farm feeding tests ───────────────────────────────

    private function isolateWholeFarmTest(): void
    {
        Cage::query()->update(['is_active' => 0]);
    }

    private function createCageWithHens(string $code, int $henCount): Cage
    {
        $cage = Cage::create([
            'cage_code' => $code,
            'location' => 'Test',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);

        $slot = CageSlot::create([
            'cage_id' => $cage->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => $henCount,
        ]);

        for ($i = 1; $i <= $henCount; $i++) {
            $hen = Hen::create([
                'tag_code' => "{$code}-HEN{$i}",
                'breed' => 'ISA Brown',
                'flock_age_weeks' => 28,
                'date_acquired' => now()->subMonths(6)->toDateString(),
                'placement_date' => now()->subMonths(6)->toDateString(),
                'age_at_placement_weeks' => 0,
                'is_active' => 1,
            ]);
            $hen->cage_slot_id = $slot->id;
            $hen->save();
        }

        return $cage;
    }

    public function test_whole_farm_distribution_splits_proportionally_and_sums_to_total(): void
    {
        $this->isolateWholeFarmTest();
        $cageA = $this->createCageWithHens('CAGE-WA', 10);
        $cageB = $this->createCageWithHens('CAGE-WB', 30);
        $cageC = $this->createCageWithHens('CAGE-WC', 60);

        $batch = FeedBatch::create([
            'crude_protein' => 17.0,
            'total_quantity_kg' => 1000.0,
            'unit_cost' => 25.00,
            'date_received' => today(),
        ]);

        $response = $this->actingAs($this->user)->post('/feed/farm-entry', [
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'log_time' => '09:30',
            'total_kg' => 100.0,
        ]);

        $response->assertSessionHas('success');

        // 10 + 30 + 60 = 100 hens
        // A: 10/100 * 100 = 10.0 kg
        // B: 30/100 * 100 = 30.0 kg
        // C: 60/100 * 100 = 60.0 kg
        $entry = FarmFeedEntry::first();
        $this->assertNotNull($entry);

        $distributed = FeedConsumptionLog::where('farm_feed_entry_id', $entry->id)
            ->orderBy('cage_id')
            ->get();

        $this->assertCount(3, $distributed);

        $a = $distributed->firstWhere('cage_id', $cageA->id);
        $b = $distributed->firstWhere('cage_id', $cageB->id);
        $c = $distributed->firstWhere('cage_id', $cageC->id);

        $this->assertEqualsWithDelta(10.0, $a->feed_consumed_kg, 0.01);
        $this->assertEqualsWithDelta(30.0, $b->feed_consumed_kg, 0.01);
        $this->assertEqualsWithDelta(60.0, $c->feed_consumed_kg, 0.01);

        $this->assertEqualsWithDelta(100.0, $distributed->sum('feed_consumed_kg'), 0.01);

        foreach ($distributed as $log) {
            $this->assertEquals('distributed', $log->source);
            $this->assertEquals($entry->id, $log->farm_feed_entry_id);
            $this->assertEquals($batch->id, $log->feed_batch_id);
        }
    }

    public function test_whole_farm_distribution_uses_largest_remainder_for_rounding(): void
    {
        $this->isolateWholeFarmTest();
        // 3 cages, total 100 kg, shares of 33.333... each
        $cageA = $this->createCageWithHens('CAGE-WA', 1);
        $cageB = $this->createCageWithHens('CAGE-WB', 1);
        $cageC = $this->createCageWithHens('CAGE-WC', 1);

        $batch = FeedBatch::create(['crude_protein' => 17.0, 'date_received' => today()]);

        $this->actingAs($this->user)->post('/feed/farm-entry', [
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'total_kg' => 100.0,
        ]);

        $entry = FarmFeedEntry::first();
        $distributed = FeedConsumptionLog::where('farm_feed_entry_id', $entry->id)->get();

        // Two cages get 33.34, one gets 33.32 (or any largest-remainder allocation that sums to 100)
        $this->assertEqualsWithDelta(100.0, $distributed->sum('feed_consumed_kg'), 0.01);
        $this->assertCount(3, $distributed);
        $this->assertContainsEquals(33.34, $distributed->pluck('feed_consumed_kg')->map(fn($v) => round($v, 2))->all());
    }

    public function test_zero_hen_cage_is_excluded_from_distribution(): void
    {
        $this->isolateWholeFarmTest();
        $withHens = $this->createCageWithHens('CAGE-WH', 10);
        $empty = $this->createCageWithHens('CAGE-WE', 0);

        $batch = FeedBatch::create(['crude_protein' => 17.0, 'date_received' => today()]);

        $this->actingAs($this->user)->post('/feed/farm-entry', [
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'total_kg' => 50.0,
        ]);

        $entry = FarmFeedEntry::first();
        $distributed = FeedConsumptionLog::where('farm_feed_entry_id', $entry->id)->get();

        $this->assertCount(1, $distributed);
        $this->assertEquals($withHens->id, $distributed->first()->cage_id);
        $this->assertEqualsWithDelta(50.0, $distributed->first()->feed_consumed_kg, 0.01);
        $this->assertFalse(FeedConsumptionLog::where('cage_id', $empty->id)->exists());
    }

    public function test_all_active_cages_zero_hens_creates_no_logs(): void
    {
        $this->isolateWholeFarmTest();
        $this->createCageWithHens('CAGE-WZ', 0);

        $batch = FeedBatch::create(['crude_protein' => 17.0, 'date_received' => today()]);

        $this->actingAs($this->user)->post('/feed/farm-entry', [
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'total_kg' => 50.0,
        ]);

        $this->assertCount(1, FarmFeedEntry::all());
        $entry = FarmFeedEntry::first();
        $this->assertCount(0, $entry->consumptionLogs);
    }

    public function test_remaining_stock_and_monthly_cost_with_mixed_direct_and_distributed(): void
    {
        $this->isolateWholeFarmTest();
        $cage = $this->createCageWithHens('CAGE-WM', 10);

        $batch = FeedBatch::create([
            'crude_protein' => 17.0,
            'total_quantity_kg' => 200.0,
            'unit_cost' => 30.00,
            'date_received' => today(),
        ]);

        // Direct entry
        FeedConsumptionLog::create([
            'cage_id' => $cage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'log_time' => '08:00',
            'feed_consumed_kg' => 25.0,
            'source' => 'direct',
            'recorded_by' => $this->user->id,
        ]);

        // Distributed entry (simulated via controller helper isn't public, so create directly)
        $entry = FarmFeedEntry::create([
            'feed_batch_id' => $batch->id,
            'log_date' => today(),
            'total_kg' => 50.0,
            'unit_cost' => 30.00,
        ]);

        FeedConsumptionLog::create([
            'cage_id' => $cage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'log_time' => '14:00',
            'feed_consumed_kg' => 50.0,
            'source' => 'distributed',
            'farm_feed_entry_id' => $entry->id,
            'recorded_by' => $this->user->id,
        ]);

        // Remaining stock sums both
        $this->assertEqualsWithDelta(125.0, $batch->fresh()->remaining_kg, 0.01);

        // Monthly cost sums both
        $response = $this->actingAs($this->user)->get('/feed/live-data');
        $response->assertOk();

        $expected = (25.0 + 50.0) * 30.00;
        $response->assertViewHas('totalFeedCostMonth', function ($val) use ($expected) {
            return abs((float) $val - $expected) < 0.01;
        });
    }

    public function test_fcr_aggregates_multiple_entries_per_day(): void
    {
        $this->isolateWholeFarmTest();
        $cage = $this->createCageWithHens('CAGE-WF', 4);
        $slot = $cage->cageSlots()->first();

        $batch = FeedBatch::create(['crude_protein' => 17.0, 'date_received' => today()]);

        // Two feed entries same day
        FeedConsumptionLog::create([
            'cage_id' => $cage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 0.6,
            'source' => 'direct',
            'recorded_by' => $this->user->id,
        ]);
        FeedConsumptionLog::create([
            'cage_id' => $cage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 0.6,
            'source' => 'direct',
            'recorded_by' => $this->user->id,
        ]);

        // 10 eggs * 60g fallback = 0.6 kg egg mass
        $log = new \App\Models\ProductionLog;
        $log->cage_slot_id = $slot->id;
        $log->log_date = today()->toDateString();
        $log->egg_count = 10;
        $log->hen_count = 4;
        $log->hdep = 250.0;
        $log->logged_via = 'manual';
        $log->recorded_by = $this->user->id;
        $log->save();

        $fcr = \App\Services\FcrCalculator::forCage($cage, today()->startOfDay(), today()->endOfDay());

        // FCR = 1.2 kg feed / 0.6 kg eggs = 2.0
        $this->assertEqualsWithDelta(2.0, $fcr, 0.01);
    }

    public function test_update_farm_feed_entry_cascades_to_distributed_rows(): void
    {
        $this->isolateWholeFarmTest();
        $cageA = $this->createCageWithHens('CAGE-WUA', 10);
        $cageB = $this->createCageWithHens('CAGE-WUB', 10);
        $batch = FeedBatch::create(['crude_protein' => 17.0, 'date_received' => today()]);

        $this->actingAs($this->user)->post('/feed/farm-entry', [
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'total_kg' => 100.0,
        ]);

        $entry = FarmFeedEntry::first();
        $this->assertCount(2, $entry->consumptionLogs);

        $this->actingAs($this->user)->put("/feed/farm-entry/{$entry->id}", [
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'total_kg' => 60.0,
        ]);

        $entry->refresh();
        $this->assertEqualsWithDelta(60.0, $entry->total_kg, 0.01);

        $distributed = $entry->consumptionLogs()->get();
        $this->assertCount(2, $distributed);
        $this->assertEqualsWithDelta(60.0, $distributed->sum('feed_consumed_kg'), 0.01);
    }

    public function test_delete_farm_feed_entry_cascades_to_distributed_rows(): void
    {
        $this->isolateWholeFarmTest();
        $cage = $this->createCageWithHens('CAGE-WD', 10);
        $batch = FeedBatch::create(['crude_protein' => 17.0, 'date_received' => today()]);

        $this->actingAs($this->user)->post('/feed/farm-entry', [
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'total_kg' => 50.0,
        ]);

        $entry = FarmFeedEntry::first();
        $this->assertCount(1, $entry->consumptionLogs);

        $this->actingAs($this->user)->delete("/feed/farm-entry/{$entry->id}");

        $this->assertCount(0, FarmFeedEntry::all());
        $this->assertFalse(FeedConsumptionLog::where('farm_feed_entry_id', $entry->id)->exists());
        $this->assertEquals(0, FeedConsumptionLog::where('source', 'distributed')->count());
    }

    public function test_distributed_row_cannot_be_edited_or_deleted_directly(): void
    {
        $this->isolateWholeFarmTest();
        $cage = $this->createCageWithHens('CAGE-WP', 10);
        $batch = FeedBatch::create(['crude_protein' => 17.0, 'date_received' => today()]);

        $this->actingAs($this->user)->post('/feed/farm-entry', [
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'total_kg' => 50.0,
        ]);

        $log = FeedConsumptionLog::where('source', 'distributed')->first();

        // Direct update should be rejected
        $response = $this->actingAs($this->user)->put("/feed/consumption/{$log->id}", [
            'cage_id' => $cage->id,
            'feed_batch_id' => $batch->id,
            'log_date' => today()->toDateString(),
            'feed_consumed_kg' => 999.0,
        ]);
        $response->assertSessionHas('error');

        // Direct delete should be rejected
        $response = $this->actingAs($this->user)->delete("/feed/consumption/{$log->id}");
        $response->assertSessionHas('error');

        // Row unchanged
        $this->assertEqualsWithDelta(50.0, $log->fresh()->feed_consumed_kg, 0.01);
    }
}
