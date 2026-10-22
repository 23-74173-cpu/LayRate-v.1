<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EggSizeLog;
use App\Models\EggCountEvent;
use App\Models\HardwareItem;
use App\Models\Hen;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EggReportingAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Cage $cageA;
    private Cage $cageB;
    private CageSlot $slotA1;
    private CageSlot $slotB1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);

        $this->cageA = Cage::create([
            'cage_code' => 'CAGE-A',
            'location' => 'North',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);

        $this->cageB = Cage::create([
            'cage_code' => 'CAGE-B',
            'location' => 'South',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);

        $this->slotA1 = CageSlot::create([
            'cage_id' => $this->cageA->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 4,
        ]);

        $this->slotB1 = CageSlot::create([
            'cage_id' => $this->cageB->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 4,
        ]);

        // Active hens with distinct breeds per cage.
        // cage_slot_id is guarded on Hen, so assign it after creation to match production patterns.
        foreach (range(1, 4) as $i) {
            $hen = Hen::create([
                'tag_code' => "A-HEN{$i}",
                'breed' => 'ISA Brown',
                'flock_age_weeks' => 28,
                'date_acquired' => now()->subMonths(6)->toDateString(),
                'placement_date' => now()->subMonths(6)->toDateString(),
                'age_at_placement_weeks' => 0,
                'is_active' => 1,
            ]);
            $hen->cage_slot_id = $this->slotA1->id;
            $hen->save();
        }

        foreach (range(1, 4) as $i) {
            $hen = Hen::create([
                'tag_code' => "B-HEN{$i}",
                'breed' => 'Dekalb White',
                'flock_age_weeks' => 34,
                'date_acquired' => now()->subMonths(8)->toDateString(),
                'placement_date' => now()->subMonths(8)->toDateString(),
                'age_at_placement_weeks' => 0,
                'is_active' => 1,
            ]);
            $hen->cage_slot_id = $this->slotB1->id;
            $hen->save();
        }
    }

    private function createLog(CageSlot $slot, int $eggs, string $date, ?string $notes, ?string $loggedVia = null): ProductionLog
    {
        $log = new ProductionLog();
        $log->cage_slot_id = $slot->id;
        $log->log_date = $date;
        $log->egg_count = $eggs;
        $log->hen_count = 4;
        $log->hdep = round(($eggs / 4) * 100, 2);
        $log->notes = $notes;
        $log->logged_via = $loggedVia ?? 'unknown';
        $log->recorded_by = $this->user->id;
        $log->save();

        return $log;
    }

    /** @test */
    public function logged_via_backfill_classifies_notes_correctly()
    {
        // Create records with known notes *before* applying backfill by leaving logged_via null.
        $sensor1 = $this->createLog($this->slotA1, 4, now()->toDateString(), 'IR sensor synced');
        $sensor2 = $this->createLog($this->slotA1, 4, now()->subDay()->toDateString(), 'Sensor reading confirmed');
        $manual1 = $this->createLog($this->slotA1, 4, now()->subDays(2)->toDateString(), 'Manual entry');
        $manual2 = $this->createLog($this->slotA1, 4, now()->subDays(3)->toDateString(), 'Manual check');
        $unknown1 = $this->createLog($this->slotA1, 4, now()->subDays(4)->toDateString(), null);
        $unknown2 = $this->createLog($this->slotA1, 4, now()->subDays(5)->toDateString(), 'Routine count');

        // Reset logged_via to simulate pre-migration state.
        ProductionLog::query()->update(['logged_via' => 'unknown']);

        // Re-run the backfill logic from the migration.
        ProductionLog::whereNotNull('notes')
            ->where(function ($q) {
                $q->where('notes', 'like', '%sensor%')
                  ->orWhere('notes', 'like', '%IR%');
            })
            ->update(['logged_via' => 'sensor']);

        ProductionLog::whereNotNull('notes')
            ->where(function ($q) {
                $q->where('notes', 'like', '%Manual%')
                  ->orWhere('notes', 'like', '%manual%');
            })
            ->update(['logged_via' => 'manual']);

        $this->assertEquals('sensor', $sensor1->fresh()->logged_via);
        $this->assertEquals('sensor', $sensor2->fresh()->logged_via);
        $this->assertEquals('manual', $manual1->fresh()->logged_via);
        $this->assertEquals('manual', $manual2->fresh()->logged_via);
        $this->assertEquals('unknown', $unknown1->fresh()->logged_via);
        $this->assertEquals('unknown', $unknown2->fresh()->logged_via);
    }

    /** @test */
    public function recent_logs_filter_by_cage()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotB1, 3, now()->subDay()->toDateString(), 'Manual entry', 'manual');

        $this->actingAs($this->user);

        // The table rows are rendered by the lazy-loaded egg-logs-list turbo frame; test that endpoint directly.
        $response = $this->get(route('eggs.logging.logs', ['cage_id' => $this->cageA->id]));
        $response->assertOk();
        $response->assertSee('CAGE-A');
        $response->assertDontSee('CAGE-B');
    }

    /** @test */
    public function recent_logs_filter_by_slot()
    {
        $slotA2 = CageSlot::create([
            'cage_id' => $this->cageA->id,
            'slot_number' => 2,
            'row_number' => 1,
            'column_number' => 2,
            'current_occupancy' => 4,
        ]);

        $this->createLog($this->slotA1, 5, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($slotA2, 7, now()->subDay()->toDateString(), 'Manual entry', 'manual');

        $this->actingAs($this->user);

        $response = $this->get(route('eggs.logging.logs', ['cage_slot_id' => $this->slotA1->id]));
        $response->assertOk();
        $response->assertSee('1-1');
        $response->assertDontSee('1-2');
    }

    /** @test */
    public function recent_logs_filter_by_breed()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotB1, 3, now()->subDay()->toDateString(), 'Manual entry', 'manual');

        $this->actingAs($this->user);

        $response = $this->get(route('eggs.logging.logs', ['breed' => 'ISA Brown']));
        $response->assertOk();
        $response->assertSee('CAGE-A');
        $response->assertDontSee('CAGE-B');
    }

    /** @test */
    public function recent_logs_filter_by_logged_via()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'IR sensor synced', 'sensor');
        $this->createLog($this->slotA1, 3, now()->subDay()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotA1, 2, now()->subDays(2)->toDateString(), 'Routine count', 'unknown');

        $this->actingAs($this->user);

        $response = $this->get(route('eggs.logging.logs', ['logged_via' => ['sensor']]));
        $response->assertOk();
        $response->assertSee('Sensor');
        $response->assertDontSee('Manual');

        $response = $this->get(route('eggs.logging.logs', ['logged_via' => ['manual', 'unknown']]));
        $response->assertOk();
        $response->assertSee('Manual');
        $response->assertDontSee('Sensor');
    }

    /** @test */
    public function recent_logs_combined_filters_narrow_results()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'IR sensor synced', 'sensor');
        $this->createLog($this->slotA1, 3, now()->subDay()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotB1, 4, now()->subDay()->toDateString(), 'IR sensor synced', 'sensor');

        $this->actingAs($this->user);

        $response = $this->get(route('eggs.logging.logs', [
            'cage_id' => $this->cageA->id,
            'breed' => 'ISA Brown',
            'logged_via' => ['sensor'],
        ]));
        $response->assertOk();
        $response->assertSee('CAGE-A');
        $response->assertDontSee('CAGE-B');
        // Only one data row (the sensor log for CAGE-A ISA Brown) should be present.
        $this->assertEquals(1, substr_count($response->getContent(), '<tr class="border-b hover:bg-black'));
    }

    /** @test */
    public function dashboard_lifetime_eggs_kpi_matches_db_sum()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotB1, 3, now()->subDay()->toDateString(), 'Manual entry', 'manual');

        $expected = ProductionLog::sum('egg_count');
        $this->assertEquals(8, $expected);

        $this->actingAs($this->user);

        // The dashboard renders KPI cards lazily via the dashboard.stats turbo-frame.
        $response = $this->get(route('dashboard.stats'));
        $response->assertOk();
        $response->assertSee('Lifetime Eggs');
        $response->assertSee(number_format($expected));
    }

    /** @test */
    public function egg_production_history_page_shows_correct_lifetime_total()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotB1, 3, now()->subDay()->toDateString(), 'Manual entry', 'manual');

        $expected = ProductionLog::sum('egg_count');

        $this->actingAs($this->user);

        $response = $this->get(route('egg-production-history'));
        $response->assertOk();
        $response->assertSee('Lifetime Total');
        $response->assertSee(number_format($expected));
    }

    /** @test */
    public function egg_production_history_timeline_aggregates_match_db()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotB1, 3, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotA1, 4, now()->subDay()->toDateString(), 'Manual entry', 'manual');

        $expectedDayTotal = ProductionLog::whereDate('log_date', now()->toDateString())->sum('egg_count');
        $expectedTotal = ProductionLog::sum('egg_count');

        $this->actingAs($this->user);

        $response = $this->get(route('egg-production-history', ['group_by' => 'day']));
        $response->assertOk();
        $response->assertSee(number_format($expectedDayTotal));
        $response->assertSee(number_format($expectedTotal));

        // Week grouping should still sum to the same lifetime total across rows.
        $weekResponse = $this->get(route('egg-production-history', ['group_by' => 'week']));
        $weekResponse->assertOk();
        $weekResponse->assertSee(number_format($expectedTotal));

        $monthResponse = $this->get(route('egg-production-history', ['group_by' => 'month']));
        $monthResponse->assertOk();
        $monthResponse->assertSee(number_format($expectedTotal));
    }

    /** @test */
    public function egg_production_history_cage_breakdown_matches_db()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotA1, 4, now()->subDay()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotB1, 3, now()->toDateString(), 'Manual entry', 'manual');

        $expectedA = $this->cageA->productionLogs->sum('egg_count');
        $expectedB = $this->cageB->productionLogs->sum('egg_count');

        $this->actingAs($this->user);

        $response = $this->get(route('egg-production-history'));
        $response->assertOk();
        $response->assertSee('CAGE-A');
        $response->assertSee('CAGE-B');
        $response->assertSee(number_format($expectedA));
        $response->assertSee(number_format($expectedB));
    }

    /** @test */
    public function egg_production_history_size_breakdown_uses_egg_size_logs()
    {
        $log = $this->createLog($this->slotA1, 6, now()->toDateString(), 'Manual entry', 'manual');
        $log->eggSizeLogs()->createMany([
            ['egg_size' => 'small', 'count' => 1],
            ['egg_size' => 'medium', 'count' => 2],
            ['egg_size' => 'large', 'count' => 2],
            ['egg_size' => 'jumbo', 'count' => 1],
        ]);

        $expectedSizeTotal = EggSizeLog::sum('count');
        $this->assertEquals(6, $expectedSizeTotal);

        $this->actingAs($this->user);

        $response = $this->get(route('egg-production-history'));
        $response->assertOk();
        // The view renders the raw lowercase size value and styles it with CSS capitalize.
        $response->assertSee('small');
        $response->assertSee('medium');
        $response->assertSee('large');
        $response->assertSee('jumbo');
        $response->assertSee(number_format($expectedSizeTotal));
    }

    /** @test */
    public function cage_overview_shows_hen_count_and_empty_indicator()
    {
        $this->actingAs($this->user);

        $cageC = Cage::create([
            'cage_code' => 'CAGE-C-EMPTY',
            'location' => 'West',
            'rows' => 1,
            'slots_per_row' => 1,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 4,
            'is_active' => 1,
        ]);
        CageSlot::create([
            'cage_id' => $cageC->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 0,
        ]);

        $response = $this->get(route('eggs.logging'));
        $response->assertOk();

        $response->assertSee('4 hens');
        $response->assertSee('empty cage', false);
    }

    /** @test */
    public function production_history_page_renders_calendar_frame()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('eggs.production-history'));
        $response->assertOk();
        $response->assertSee('Production History');
        $response->assertSee('dashboard-calendar');
    }

    /** @test */
    public function manual_store_sets_logged_via_to_manual_by_default()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('eggs.logging.store'), [
            'cage_slot_id' => $this->slotA1->id,
            'log_date' => now()->toDateString(),
            'egg_count' => 3,
            'hen_count' => 4,
        ]);

        $response->assertRedirect();
        $this->assertEquals(1, ProductionLog::count());
        $this->assertEquals('manual', ProductionLog::first()->logged_via);
    }

    public function test_sensor_override_to_zero_is_persisted_as_audit_event_and_live_count_is_zero(): void
    {
        Cache::forever('egg_count_sse_version', 9);
        $log = $this->createLog($this->slotA1, 3, now()->toDateString(), 'Sensor reading', 'sensor');

        $this->actingAs($this->user)->put(route('eggs.logging.update', $log), [
            'log_date' => now()->toDateString(),
            'egg_count' => 0,
        ])->assertRedirect(route('eggs.logging'));

        $this->assertSame(0, (int) $log->fresh()->egg_count);
        $this->assertDatabaseMissing('egg_size_logs', [
            'production_log_id' => $log->id,
        ]);
        $event = EggCountEvent::where('production_log_id', $log->id)->firstOrFail();
        $this->assertSame('manual_override', $event->event_type);
        $this->assertSame(3, $event->old_value);
        $this->assertSame(0, $event->new_value);
        $this->assertSame($this->user->id, $event->user_id);
        $this->assertSame(10, (int) Cache::get('egg_count_sse_version'));
        $this->actingAs($this->user)->get(route('eggs.logging.logs', ['cage_slot_id' => $this->slotA1->id]))
            ->assertOk()->assertSee('0', false);
        $this->get(route('eggs.logging'))->assertOk()->assertSee('data-today-eggs="0"', false);
    }

    public function test_zero_override_changes_only_todays_slot_log_and_preserves_october_19_log(): void
    {
        $today = now()->toDateString();
        $older = $this->createLog($this->slotA1, 2, '2026-10-19', 'Older sensor record', 'sensor');
        $current = $this->createLog($this->slotA1, 3, $today, 'Today sensor record', 'sensor');

        $this->actingAs($this->user)->put(route('eggs.logging.update', $current), [
            'log_date' => $today,
            'egg_count' => 0,
        ])->assertRedirect(route('eggs.logging'));

        $this->assertSame(0, (int) $current->fresh()->egg_count);
        $this->assertSame('2026-10-19', $older->fresh()->log_date->toDateString());
        $this->assertSame(2, (int) $older->fresh()->egg_count);
    }

    public function test_live_count_sse_includes_cage_with_zero_total(): void
    {
        $this->actingAs($this->user);
        $this->createLog($this->slotA1, 0, now()->toDateString(), 'Manual entry', 'manual');

        $response = $this->get(route('eggs.logging.live-count', ['cage_code' => 'CAGE-A']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=utf-8');
    }

    public function test_override_then_sensor_events_count_up_from_override_without_audit_inflation(): void
    {
        $log = $this->createLog($this->slotA1, 2, now()->toDateString(), 'Sensor reading', 'sensor');
        $this->actingAs($this->user)->put(route('eggs.logging.update', $log), [
            'log_date' => now()->toDateString(),
            'egg_count' => 1,
        ])->assertRedirect(route('eggs.logging'));

        $this->assertSame(1, (int) $log->fresh()->egg_count);
        $this->assertSame(1, EggCountEvent::where('production_log_id', $log->id)
            ->where('event_type', 'manual_override')->count());
        $this->get(route('eggs.logging'))->assertOk()->assertSee('data-today-eggs="1"', false);

        $overrideItem = HardwareItem::create([
            'device_type' => 'IR_breakbeam',
            'serial_number' => 'IRBBS-OVERRIDE-CURRENT-001',
            'cage_slot_id' => $this->slotA1->id,
            'device_id' => \App\Models\Device::create([
                'name' => 'Override current Pi',
                'api_key_hash' => \Illuminate\Support\Facades\Hash::make('override-current-key'),
                'is_active' => true,
            ])->id,
            'status' => 'active',
        ]);
        $this->postJson('/api/sensor-readings', [
            'readings' => [['serial_number' => $overrideItem->serial_number, 'count' => 2, 'trigger_count' => 3, 'new_trigger_count' => 1]],
            'recorded_at' => now()->addSecond()->toDateTimeString(),
        ], ['X-Device-Key' => 'override-current-key'])->assertOk();
        $this->assertSame(2, (int) $log->fresh()->egg_count);
        $this->assertDatabaseHas('egg_count_events', [
            'hardware_item_id' => $overrideItem->id,
            'event_type' => 'sensor_trigger',
            'old_value' => 1,
            'new_value' => 2,
        ]);

        $device = \App\Models\Device::create([
            'name' => 'Override test Pi',
            'api_key_hash' => \Illuminate\Support\Facades\Hash::make('override-test-key'),
            'is_active' => true,
        ]);
        $item = \App\Models\HardwareItem::create([
            'device_type' => 'IR_breakbeam',
            'serial_number' => 'IRBBS-OVERRIDE-001',
            'cage_slot_id' => $this->slotB1->id,
            'device_id' => $device->id,
            'status' => 'active',
        ]);
        $sensorLog = $this->createLog($this->slotB1, 0, now()->toDateString(), 'Sensor reading', 'sensor');
        $this->postJson('/api/sensor-readings', [
            'readings' => [['serial_number' => 'IRBBS-OVERRIDE-001', 'count' => 2]],
            'recorded_at' => now()->addSecond()->toDateTimeString(),
        ], ['X-Device-Key' => 'override-test-key'])->assertOk();

        $this->assertSame(2, (int) $sensorLog->fresh()->egg_count);
        $this->assertSame(2, EggCountEvent::where('hardware_item_id', $item->id)
            ->where('event_type', 'sensor_trigger')->count());

        $this->postJson('/api/sensor-readings', [
            'readings' => [['serial_number' => 'IRBBS-OVERRIDE-001', 'count' => 3]],
            'recorded_at' => now()->addSeconds(2)->toDateTimeString(),
        ], ['X-Device-Key' => 'override-test-key'])->assertOk();

        $this->assertSame(3, (int) $sensorLog->fresh()->egg_count);
        $this->assertSame(3, EggCountEvent::where('hardware_item_id', $item->id)
            ->where('event_type', 'sensor_trigger')->count());
    }

    public function test_override_to_zero_then_sensor_event_counts_up_from_zero(): void
    {
        $log = $this->createLog($this->slotA1, 2, now()->toDateString(), 'Sensor reading', 'sensor');
        $this->actingAs($this->user)->put(route('eggs.logging.update', $log), [
            'log_date' => now()->toDateString(),
            'egg_count' => 0,
        ])->assertRedirect(route('eggs.logging'));

        $device = \App\Models\Device::create([
            'name' => 'Zero override Pi',
            'api_key_hash' => \Illuminate\Support\Facades\Hash::make('zero-override-key'),
            'is_active' => true,
        ]);
        $item = \App\Models\HardwareItem::create([
            'device_type' => 'IR_breakbeam',
            'serial_number' => 'IRBBS-ZERO-001',
            'cage_slot_id' => $this->slotB1->id,
            'device_id' => $device->id,
            'status' => 'active',
        ]);
        $nextLog = $this->createLog($this->slotB1, 0, now()->toDateString(), 'Sensor reading', 'sensor');

        $this->postJson('/api/sensor-readings', [
            'readings' => [['serial_number' => 'IRBBS-ZERO-001', 'count' => 1]],
            'recorded_at' => now()->addSecond()->toDateTimeString(),
        ], ['X-Device-Key' => 'zero-override-key'])->assertOk();

        $this->assertSame(1, (int) $nextLog->fresh()->egg_count);
        $this->assertDatabaseHas('egg_count_events', [
            'hardware_item_id' => $item->id,
            'event_type' => 'sensor_trigger',
            'old_value' => 0,
            'new_value' => 1,
        ]);
    }

    public function test_sensor_event_migration_path_creates_persisted_event_and_production_log(): void
    {
        $device = \App\Models\Device::create([
            'name' => 'Persist test Pi',
            'api_key_hash' => \Illuminate\Support\Facades\Hash::make('persist-test-key'),
            'is_active' => true,
        ]);
        $item = HardwareItem::create([
            'device_type' => 'IR_breakbeam',
            'serial_number' => 'IRBBS-PERSIST-001',
            'cage_slot_id' => $this->slotA1->id,
            'device_id' => $device->id,
            'status' => 'active',
        ]);

        $this->postJson('/api/sensor-readings', [
            'readings' => [['serial_number' => $item->serial_number, 'count' => 1]],
            'recorded_at' => now()->toDateTimeString(),
        ], ['X-Device-Key' => 'persist-test-key'])->assertOk();

        $this->assertSame(1, EggCountEvent::where('cage_slot_id', $this->slotA1->id)
            ->where('hardware_item_id', $item->id)
            ->where('event_type', 'sensor_trigger')->count());
        $this->assertDatabaseHas('production_logs', [
            'cage_slot_id' => $this->slotA1->id,
            'egg_count' => 1,
            'logged_via' => 'sensor',
        ]);
    }
}
