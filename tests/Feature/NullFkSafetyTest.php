<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EnvironmentalLog;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * P1 Regression: Null-FK safety for pages that display logs after cage deletion.
 *
 * Guards against the crash fixed in the session, where ->cage?->cage_code
 * accessed null cage/cage_slot relationships, causing 500 errors.
 */
class NullFkSafetyTest extends TestCase
{
    private User $user;
    private Cage $cage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::where('email', 'admin@layrate.local')->first();
        $this->cage = Cage::where('cage_code', 'CAGE-A')->first();

        $slot = $this->cage->cageSlots()->first();
        $today = now()->toDateString();

        // Objects with null FKs — simulates preserved logs after cage deletion
        MortalityLog::create([
            'cage_id'     => null,
            'log_date'    => $today,
            'count'       => 2,
            'reason'      => 'Unknown',
            'notes'       => 'Null-cage test',
            'recorded_by' => $this->user->id,
        ]);

        $pl = new ProductionLog;
        $pl->cage_slot_id = null;
        $pl->log_date = $today;
        $pl->egg_count = 0;
        $pl->hen_count = 0;
        $pl->hdep = 0;
        $pl->recorded_by = $this->user->id;
        $pl->notes = 'Null-slot test';
        $pl->save();

        EnvironmentalLog::create([
            'cage_id'       => null,
            'recorded_at'   => now(),
            'temperature_c' => 25.0,
            'humidity_pct'  => 60.0,
        ]);

        // feed_batch_id is NOT NULL; use a valid feed batch
        $feedBatch = FeedBatch::first();
        FeedConsumptionLog::create([
            'cage_id'          => null,
            'feed_batch_id'    => $feedBatch->id,
            'log_date'         => $today,
            'feed_consumed_kg' => 0,
            'recorded_by'      => $this->user->id,
        ]);
    }

    public function test_dashboard_returns_200_with_null_fk_logs(): void
    {
        $this->actingAs($this->user)->get('/')->assertOk();
    }

    public function test_chickens_index_returns_200_with_null_fk_logs(): void
    {
        $this->actingAs($this->user)->get('/chickens')->assertOk();
    }

    public function test_mortality_index_returns_200_with_null_fk_logs(): void
    {
        $this->actingAs($this->user)->get('/mortality')->assertOk();
    }

    public function test_feed_index_returns_200_with_null_fk_logs(): void
    {
        $this->actingAs($this->user)->get('/feed')->assertOk();
    }

    public function test_reports_index_returns_200_with_null_fk_logs(): void
    {
        $this->actingAs($this->user)->get('/reports')->assertOk();
    }

    public function test_environment_index_returns_200_with_null_fk_logs(): void
    {
        $this->actingAs($this->user)->get('/environment')->assertOk();
    }
}
