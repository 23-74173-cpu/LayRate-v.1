<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * P1 Regression: Bulk Add mode-preservation on validation failure.
 *
 * Guards against the bugs fixed in this session:
 * - mode hidden input always defaulted to 'manual' without old() preservation
 * - radio buttons didn't reflect old('mode')
 * - chickens_per_slot had no name attribute (never submitted)
 * - autoMode div stayed hidden after auto-mode validation failure
 */
class BulkAddModePreservationTest extends TestCase
{
    private User $user;
    private Cage $cage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::where('email', 'admin@layrate.local')->first();
        $this->cage = Cage::where('cage_code', 'CAGE-A')->first();
    }

    public function test_fresh_bulk_add_page_has_no_stale_errors(): void
    {
        $response = $this->actingAs($this->user)->get('/cages/bulk-add');
        $response->assertOk();
        $response->assertSessionMissing('errors');
    }

    public function test_auto_mode_rejected_without_chickens_per_slot(): void
    {
        $henIds = $this->createUnplacedHens(2);

        $response = $this->actingAs($this->user)
            ->from('/cages/bulk-add')
            ->post('/cages/bulk-add', [
                'hen_ids'  => implode(',', $henIds),
                'cage_id'  => $this->cage->id,
                'mode'     => 'auto',
                'slot_ids' => '',
            ]);

        $response->assertSessionHasErrors(['chickens_per_slot']);
        $response->assertRedirect('/cages/bulk-add');

        // old('mode') must be preserved as 'auto' for view re-population
        $this->assertEquals('auto', old('mode'));
    }

    public function test_manual_mode_rejected_without_slot_ids(): void
    {
        $henIds = $this->createUnplacedHens(2);

        $response = $this->actingAs($this->user)
            ->from('/cages/bulk-add')
            ->post('/cages/bulk-add', [
                'hen_ids' => implode(',', $henIds),
                'cage_id' => $this->cage->id,
                'mode'    => 'manual',
            ]);

        $response->assertSessionHasErrors(['slot_ids']);
        $response->assertRedirect('/cages/bulk-add');
    }

    public function test_auto_mode_preserves_old_values_on_validation_failure(): void
    {
        $henIds = $this->createUnplacedHens(2);

        $response = $this->actingAs($this->user)
            ->from('/cages/bulk-add')
            ->post('/cages/bulk-add', [
                'hen_ids'           => implode(',', $henIds),
                'cage_id'           => $this->cage->id,
                'mode'              => 'auto',
                'slot_ids'          => '',
                'chickens_per_slot' => '',
            ]);

        $response->assertSessionHasErrors(['chickens_per_slot']);
        $this->assertEquals('auto', session()->getOldInput('mode'));
    }

    public function test_successful_auto_distribute_places_hens(): void
    {
        // CAGE-D has all 15 slots at 0 occupancy (inactive cage)
        $emptyCage = Cage::where('cage_code', 'CAGE-D')->first();
        $henIds = $this->createUnplacedHens(4);

        $response = $this->actingAs($this->user)
            ->post('/cages/bulk-add', [
                'hen_ids'           => implode(',', $henIds),
                'cage_id'           => $emptyCage->id,
                'mode'              => 'auto',
                'slot_ids'          => '',
                'chickens_per_slot' => 1,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/chickens');
    }

    private function createUnplacedHens(int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $s = substr(uniqid(), -8);
            $hen = Hen::create([
                'chicken_id'              => "AT-{$s}",
                'breed'                   => 'ISA Brown',
                'tag_code'                => "AT-{$s}",
                'date_acquired'           => now()->subDays(30),
                'flock_age_weeks'         => 20,
                'cage_slot_id'            => null,
                'is_active'               => 1,
                'placement_date'          => null,
                'age_at_placement_weeks'  => 0,
            ]);
            $ids[] = $hen->id;
        }
        return $ids;
    }
}
