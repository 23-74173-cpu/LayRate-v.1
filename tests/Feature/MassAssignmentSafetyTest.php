<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Forecast;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\MortalityLogHen;
use App\Models\ProductionLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * P4 regression guards: mass-assignment of FK/controller-controlled
 * fields is silently ignored for all affected models.
 */
class MassAssignmentSafetyTest extends TestCase
{
    private User $user;
    private Cage $cage;
    private CageSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->user = User::where('email', 'admin@layrate.local')->first();
        $this->cage = Cage::where('cage_code', 'CAGE-A')->first();
        $this->slot = $this->cage->cageSlots->first();
    }

    public function test_hen_mass_assign_cage_slot_id_is_ignored(): void
    {
        $hen = new Hen;
        $hen->fill([
            'tag_code'     => 'MASS-ASSIGN-TEST-1',
            'cage_slot_id' => $this->slot->id,
            'breed'        => 'ISA Brown',
            'is_active'    => 1,
        ]);

        $this->assertNull($hen->cage_slot_id, 'cage_slot_id should not be mass-assignable');
        $this->assertEquals('MASS-ASSIGN-TEST-1', $hen->tag_code, 'tag_code should be mass-assignable');
    }

    public function test_production_log_mass_assign_fks_are_ignored(): void
    {
        $log = new ProductionLog;
        $log->fill([
            'cage_slot_id'       => $this->slot->id,
            'log_date'           => now()->toDateString(),
            'egg_count'          => 5,
            'hen_count'          => 4,
            'hdep'               => 80.0,
            'recorded_by'        => $this->user->id,
            'overridden_by_user_id' => $this->user->id,
            'overridden_at'      => now(),
        ]);

        $this->assertNull($log->cage_slot_id, 'cage_slot_id should not be mass-assignable');
        $this->assertNull($log->recorded_by, 'recorded_by should not be mass-assignable');
        $this->assertNull($log->overridden_by_user_id, 'overridden_by_user_id should not be mass-assignable');
        $this->assertNull($log->overridden_at, 'overridden_at should not be mass-assignable');
        $this->assertEquals(5, $log->egg_count, 'egg_count should be mass-assignable');
    }

    public function test_forecast_mass_assign_fks_are_ignored(): void
    {
        $forecast = new Forecast;
        $forecast->fill([
            'cage_id'        => $this->cage->id,
            'cage_slot_id'   => $this->slot->id,
            'breed'          => 'ISA Brown',
            'forecast_date'  => now()->toDateString(),
            'target_date'    => now()->addDays(7)->toDateString(),
            'predicted_hdep' => 85.0,
        ]);

        $this->assertNull($forecast->cage_id, 'cage_id should not be mass-assignable');
        $this->assertNull($forecast->cage_slot_id, 'cage_slot_id should not be mass-assignable');
        $this->assertNull($forecast->breed, 'breed should not be mass-assignable');
        $this->assertNull($forecast->forecast_date, 'forecast_date should not be mass-assignable');
        $this->assertNull($forecast->predicted_hdep, 'predicted_hdep should not be mass-assignable');
        $this->assertNotNull($forecast->target_date, 'target_date should be mass-assignable');
    }

    public function test_mortality_log_hen_mass_assign_all_blocked(): void
    {
        $this->assertEmpty(
            (new MortalityLogHen)->getFillable(),
            'MortalityLogHen should have no fillable attributes'
        );

        $this->expectException(\Illuminate\Database\Eloquent\MassAssignmentException::class);
        (new MortalityLogHen)->fill([
            'mortality_log_id' => 999,
            'hen_id'           => 999,
            'cage_slot_id'     => 999,
        ]);
    }

    public function test_mortality_log_hen_direct_assignment_works(): void
    {
        $slot = $this->cage->cageSlots->first();
        $log = MortalityLog::where('cage_id', $this->cage->id)->first();
        $hen = Hen::where('cage_slot_id', $slot->id)->first();

        $pivot = new MortalityLogHen;
        $pivot->mortality_log_id = $log->id;
        $pivot->hen_id = $hen->id;
        $pivot->cage_slot_id = $slot->id;
        $pivot->save();

        $this->assertNotNull($pivot->id, 'Direct assignment + save should create the record');
        $this->assertEquals($log->id, $pivot->mortality_log_id, 'FK set via direct property should persist');
    }
}
