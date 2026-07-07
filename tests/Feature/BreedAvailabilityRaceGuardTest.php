<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P4: Breed-availability race guard (Item C).
 *
 * Guards against TOCTOU race where two overlapping bulk-add requests
 * select the same hens. Only the first should succeed; the second must
 * be rejected with "no longer available" / "not still unplaced" message.
 *
 * True concurrent-process testing is not practical in PHPUnit. Instead:
 * 1. Verify sequential requests — first succeeds, second is rejected.
 * 2. Assert lockForUpdate() is used on the breed-select query.
 */
class BreedAvailabilityRaceGuardTest extends TestCase
{
    private User $user;
    private Cage $cage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::where('email', 'admin@layrate.local')->first();
        $this->cage = Cage::where('cage_code', 'CAGE-D')->first();
    }

    public function test_second_bulk_add_with_same_hens_is_rejected(): void
    {
        $henIds = [];
        for ($i = 0; $i < 2; $i++) {
            $s = substr(uniqid(), -8);
            $hen = Hen::create([
                'chicken_id'             => "RT-{$s}",
                'breed'                  => 'ISA Brown',
                'tag_code'               => "RT-{$s}",
                'date_acquired'          => now()->subDays(30),
                'flock_age_weeks'        => 20,
                'cage_slot_id'           => null,
                'is_active'              => 1,
                'placement_date'         => null,
                'age_at_placement_weeks' => 0,
            ]);
            $henIds[] = $hen->id;
        }

        $slot = $this->cage->cageSlots->first(fn($s) => $s->remaining > 0);
        if (!$slot) {
            $this->markTestSkipped('No slots with remaining capacity');
        }

        // First request — should succeed
        $this->actingAs($this->user)
            ->post('/cages/bulk-add', [
                'hen_ids'  => implode(',', $henIds),
                'cage_id'  => $this->cage->id,
                'mode'     => 'manual',
                'slot_ids' => (string) $slot->id,
            ]);

        // Second request with same hen IDs — must be rejected (hens now placed)
        $response = $this->actingAs($this->user)
            ->from('/cages/bulk-add')
            ->post('/cages/bulk-add', [
                'hen_ids'  => implode(',', $henIds),
                'cage_id'  => $this->cage->id,
                'mode'     => 'manual',
                'slot_ids' => (string) $slot->id,
            ]);

        $response->assertRedirect('/cages/bulk-add');
        $response->assertSessionHasErrors();

        $errors = session('errors');
        $found = false;
        if ($errors) {
            foreach ($errors->all() as $msg) {
                if (str_contains($msg, 'no longer available') || str_contains($msg, 'still unplaced')) {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found, 'Expected "no longer available" or "still unplaced" error');
    }

    public function test_breed_re_validation_uses_lock_for_update(): void
    {
        DB::enableQueryLog();

        $s = substr(uniqid(), -8);
        $hen = Hen::create([
            'chicken_id'             => "LT-{$s}",
            'breed'                  => 'ISA Brown',
            'tag_code'               => "LT-{$s}",
            'date_acquired'          => now()->subDays(30),
            'flock_age_weeks'        => 20,
            'cage_slot_id'           => null,
            'is_active'              => 1,
            'placement_date'         => null,
            'age_at_placement_weeks' => 0,
        ]);

        $slot = $this->cage->cageSlots->first(fn($s) => $s->remaining > 0);
        if (!$slot) {
            $this->markTestSkipped('No slots with remaining capacity');
        }

        $this->actingAs($this->user)
            ->post('/cages/bulk-add', [
                'hen_ids'  => (string) $hen->id,
                'cage_id'  => $this->cage->id,
                'mode'     => 'manual',
                'slot_ids' => (string) $slot->id,
            ]);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $found = false;
        foreach ($queries as $q) {
            $sql = strtolower($q['query']);
            if (str_contains($sql, 'from `hens`') && str_contains($sql, 'for update')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected hens query with lockForUpdate()');
    }
}
