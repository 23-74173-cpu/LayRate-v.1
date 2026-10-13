<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EggSizeLog;
use App\Models\ProductionLog;
use App\Models\User;
use Tests\TestCase;

class ProductionHistoryDayTest extends TestCase
{
    private function log(string $date, int $eggs, bool $demo = false): ProductionLog
    {
        $cage = Cage::create(['cage_code' => 'TEST-'.uniqid(), 'rows' => 1, 'slots_per_row' => 1, 'max_chickens_per_slot' => 4, 'total_capacity' => 4, 'is_active' => true]);
        $slot = CageSlot::create(['cage_id' => $cage->id, 'slot_number' => 1, 'row_number' => 1, 'column_number' => 1]);
        return ProductionLog::create(['cage_slot_id' => $slot->id, 'log_date' => $date, 'egg_count' => $eggs, 'hen_count' => 4, 'hdep' => 50, 'is_demo' => $demo]);
    }

    public function test_details_aggregate_all_cages_and_exclude_demo_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = $this->log('2026-10-13', 2);
        $this->log('2026-10-13', 3);
        $this->log('2026-10-12', 4);
        $this->log('2026-10-13', 99, true);
        EggSizeLog::create(['production_log_id' => $first->id, 'egg_size' => 'large', 'count' => 2]);
        $this->actingAs($admin)->getJson(route('eggs.production-history.day', ['date' => '2026-10-13']))
            ->assertOk()->assertJsonPath('eggs', 5)->assertJsonPath('records', 2)
            ->assertJsonCount(2, 'by_cage')->assertJsonPath('by_size.0.eggs', 2);
    }

    public function test_deletion_removes_only_that_days_real_records_and_sizes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = $this->log('2026-10-13', 2);
        $second = $this->log('2026-10-13', 3);
        $other = $this->log('2026-10-12', 4);
        $demo = $this->log('2026-10-13', 99, true);
        EggSizeLog::create(['production_log_id' => $first->id, 'egg_size' => 'large', 'count' => 2]);
        $this->actingAs($admin)->deleteJson(route('eggs.production-history.day.delete', ['date' => '2026-10-13']))
            ->assertOk()->assertJsonPath('deleted', 2);
        $this->assertDatabaseMissing('production_logs', ['id' => $first->id]);
        $this->assertDatabaseMissing('production_logs', ['id' => $second->id]);
        $this->assertDatabaseMissing('egg_size_logs', ['production_log_id' => $first->id]);
        $this->assertDatabaseHas('production_logs', ['id' => $other->id]);
        $this->assertDatabaseHas('production_logs', ['id' => $demo->id]);
    }

    public function test_operator_can_view_but_cannot_delete_and_invalid_dates_are_rejected(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $log = $this->log('2026-10-13', 2);
        $this->actingAs($user)->getJson(route('eggs.production-history.day', ['date' => '2026-10-13']))
            ->assertOk()->assertJsonPath('can_delete', false);
        $this->deleteJson(route('eggs.production-history.day.delete', ['date' => '2026-10-13']))->assertForbidden();
        $this->assertDatabaseHas('production_logs', ['id' => $log->id]);
        $this->getJson(route('eggs.production-history.day', ['date' => '2026-02-30']))->assertUnprocessable();
    }
}
