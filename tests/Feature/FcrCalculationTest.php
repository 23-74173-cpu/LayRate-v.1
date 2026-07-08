<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\Hen;
use App\Models\ProductionLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\FcrCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FcrCalculationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cage $cage;

    private CageSlot $slot;

    private FeedBatch $feedBatch;

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

        $hen = Hen::create([
            'tag_code' => 'T-HEN1',
            'breed' => 'ISA Brown',
            'flock_age_weeks' => 28,
            'date_acquired' => now()->subMonths(6)->toDateString(),
            'placement_date' => now()->subMonths(6)->toDateString(),
            'age_at_placement_weeks' => 0,
            'is_active' => 1,
        ]);
        $hen->cage_slot_id = $this->slot->id;
        $hen->save();

        $this->feedBatch = FeedBatch::create([
            'batch_code' => 'F-TEST',
            'crude_protein' => 17.0,
            'date_received' => now()->toDateString(),
        ]);

        // Seed egg-weight settings with known defaults for deterministic tests.
        foreach ([
            'egg_weight_small' => 50,
            'egg_weight_medium' => 58,
            'egg_weight_large' => 65,
            'egg_weight_jumbo' => 73,
            'egg_weight_fallback' => 60,
        ] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'label' => $key]);
        }
    }

    private function createLog(int $eggs, string $date, ?array $sizes = null): ProductionLog
    {
        $log = new ProductionLog;
        $log->cage_slot_id = $this->slot->id;
        $log->log_date = $date;
        $log->egg_count = $eggs;
        $log->hen_count = 4;
        $log->hdep = round(($eggs / 4) * 100, 2);
        $log->logged_via = 'manual';
        $log->recorded_by = $this->user->id;
        $log->save();

        if ($sizes !== null) {
            foreach ($sizes as $size => $count) {
                if ($count > 0) {
                    $log->eggSizeLogs()->create(['egg_size' => $size, 'count' => $count]);
                }
            }
        }

        return $log;
    }

    private function createFeed(float $kg, string $date): void
    {
        FeedConsumptionLog::create([
            'cage_id' => $this->cage->id,
            'feed_batch_id' => $this->feedBatch->id,
            'log_date' => $date,
            'feed_consumed_kg' => $kg,
            'recorded_by' => $this->user->id,
        ]);
    }

    /** @test */
    public function egg_mass_uses_size_breakdown_when_available()
    {
        $log = $this->createLog(10, now()->toDateString(), [
            'small' => 1,   // 50g
            'medium' => 2,  // 2 × 58g = 116g
            'large' => 3,   // 3 × 65g = 195g
            'jumbo' => 4,   // 4 × 73g = 292g
        ]);

        // Total = 50 + 116 + 195 + 292 = 653g = 0.653kg
        $this->assertEqualsWithDelta(0.653, FcrCalculator::eggMassForLog($log), 0.0001);
    }

    /** @test */
    public function egg_mass_uses_fallback_weight_when_no_size_data()
    {
        $log = $this->createLog(10, now()->toDateString());

        // 10 eggs × 60g fallback = 600g = 0.6kg
        $this->assertEqualsWithDelta(0.6, FcrCalculator::eggMassForLog($log), 0.0001);
    }

    /** @test */
    public function for_cage_returns_null_when_egg_mass_is_zero()
    {
        $this->createFeed(5.0, now()->toDateString());
        // No production logs => egg mass = 0

        $fcr = FcrCalculator::forCage($this->cage, now()->startOfDay(), now()->endOfDay());

        $this->assertNull($fcr);
    }

    /** @test */
    public function for_cage_returns_null_when_feed_is_zero()
    {
        $this->createLog(10, now()->toDateString()); // 0.6 kg egg mass, no feed

        $fcr = FcrCalculator::forCage($this->cage, now()->startOfDay(), now()->endOfDay());

        $this->assertNull($fcr);
    }

    /** @test */
    public function for_cage_calculates_fcr_correctly()
    {
        $this->createLog(10, now()->toDateString()); // 0.6 kg egg mass
        $this->createFeed(1.2, now()->toDateString()); // 1.2 kg feed

        $fcr = FcrCalculator::forCage($this->cage, now()->startOfDay(), now()->endOfDay());

        // FCR = 1.2 / 0.6 = 2.0
        $this->assertEqualsWithDelta(2.0, $fcr, 0.01);
    }

    /** @test */
    public function timeline_aggregation_matches_manual_db_sums()
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $this->createLog(10, $today);        // 0.6 kg
        $this->createLog(20, $yesterday);    // 1.2 kg
        $this->createFeed(2.0, $today);
        $this->createFeed(3.0, $yesterday);

        $timeline = FcrCalculator::timeline($this->cage, 'day');

        $todayRow = $timeline->firstWhere('period', $today);
        $yesterdayRow = $timeline->firstWhere('period', $yesterday);

        $this->assertNotNull($todayRow);
        $this->assertNotNull($yesterdayRow);

        $this->assertEqualsWithDelta(2.0, $todayRow['feed_kg'], 0.01);
        $this->assertEqualsWithDelta(0.6, $todayRow['egg_mass_kg'], 0.001);
        $this->assertEqualsWithDelta(2.0 / 0.6, $todayRow['fcr'], 0.01);

        $this->assertEqualsWithDelta(3.0, $yesterdayRow['feed_kg'], 0.01);
        $this->assertEqualsWithDelta(1.2, $yesterdayRow['egg_mass_kg'], 0.001);
        $this->assertEqualsWithDelta(3.0 / 1.2, $yesterdayRow['fcr'], 0.01);
    }

    /** @test */
    public function timeline_returns_null_fcr_for_periods_with_feed_but_no_eggs()
    {
        $today = now()->toDateString();
        $this->createFeed(2.0, $today);

        $timeline = FcrCalculator::timeline($this->cage, 'day');
        $row = $timeline->firstWhere('period', $today);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(2.0, $row['feed_kg'], 0.01);
        $this->assertEqualsWithDelta(0.0, $row['egg_mass_kg'], 0.001);
        $this->assertNull($row['fcr']);
    }

    /** @test */
    public function timeline_returns_null_fcr_for_periods_with_eggs_but_no_feed()
    {
        $today = now()->toDateString();
        $this->createLog(10, $today); // 0.6 kg egg mass, no feed

        $timeline = FcrCalculator::timeline($this->cage, 'day');
        $row = $timeline->firstWhere('period', $today);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(0.0, $row['feed_kg'], 0.01);
        $this->assertEqualsWithDelta(0.6, $row['egg_mass_kg'], 0.001);
        $this->assertNull($row['fcr']);
    }

    /** @test */
    public function timeline_groups_by_week_using_shared_service()
    {
        $monday = Carbon::parse('2026-06-29'); // Monday of week 27
        $tuesday = $monday->copy()->addDay();

        $this->createLog(10, $monday->toDateString());
        $this->createLog(20, $tuesday->toDateString());
        $this->createFeed(5.0, $monday->toDateString());

        $timeline = FcrCalculator::timeline($this->cage, 'week');
        $row = $timeline->first();

        $this->assertStringContainsString('Week 27', $row['label']);
        $this->assertEqualsWithDelta(5.0, $row['feed_kg'], 0.01);
        $this->assertEqualsWithDelta(1.8, $row['egg_mass_kg'], 0.001); // 0.6 + 1.2
        $this->assertEqualsWithDelta(5.0 / 1.8, $row['fcr'], 0.01);
    }
}
