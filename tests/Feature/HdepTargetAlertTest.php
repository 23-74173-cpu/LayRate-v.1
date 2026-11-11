<?php

namespace Tests\Feature;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Device;
use App\Models\HardwareItem;
use App\Models\Hen;
use App\Models\ProductionLog;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * hdep_target_reached: one alert per crossing of the Desired HDEP (default
 * 85%), per cage and for the whole flock. After an alert it re-arms only when
 * a finished day ends below the target; days without records neither re-arm
 * nor block it.
 */
class HdepTargetAlertTest extends TestCase
{
    private const FIRST_DAY = '2026-11-10';

    private User $admin;
    private Cage $cageA;
    private CageSlot $slotA;
    private CageSlot $slotB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@layrate.local')->firstOrFail();

        $this->cageA = Cage::create([
            'cage_code' => 'CAGE-HDEP-A', 'location' => 'Test', 'rows' => 1,
            'slots_per_row' => 1, 'max_chickens_per_slot' => 10, 'total_capacity' => 10, 'is_active' => 1,
        ]);
        $cageB = Cage::create([
            'cage_code' => 'CAGE-HDEP-B', 'location' => 'Test', 'rows' => 1,
            'slots_per_row' => 1, 'max_chickens_per_slot' => 10, 'total_capacity' => 10, 'is_active' => 1,
        ]);
        $this->slotA = CageSlot::create(['cage_id' => $this->cageA->id, 'slot_number' => 1, 'row_number' => 1, 'column_number' => 1, 'current_occupancy' => 0]);
        $this->slotB = CageSlot::create(['cage_id' => $cageB->id, 'slot_number' => 1, 'row_number' => 1, 'column_number' => 1, 'current_occupancy' => 0]);

        $this->onDay(0);
    }

    private function day(int $offset): string
    {
        return Carbon::parse(self::FIRST_DAY)->addDays($offset)->toDateString();
    }

    private function onDay(int $offset): void
    {
        $this->travelTo(Carbon::parse($this->day($offset).' 12:00', 'Asia/Manila'));
    }

    private function log(CageSlot $slot, int $offset, int $eggs, int $hens = 10): void
    {
        ProductionLog::updateOrCreate(
            ['cage_slot_id' => $slot->id, 'log_date' => $this->day($offset)],
            ['egg_count' => $eggs, 'hen_count' => $hens, 'hdep' => round($eggs / $hens * 100, 2), 'logged_via' => 'manual']
        );
    }

    private function check(CageSlot $slot, int $offset): void
    {
        (new class extends Controller {
            public function run(int $cageId, string $date): void
            {
                $this->checkHdepTarget($cageId, $date);
            }
        })->run($slot->cage_id, $this->day($offset));
    }

    private function alerts(?int $cageId)
    {
        return Alert::where('alert_type', 'hdep_target_reached')
            ->where('dedup_key', Alert::dedupKey($cageId, 'hdep_target_reached'))
            ->orderBy('alert_day')
            ->get();
    }

    private function addHens(CageSlot $slot, int $count): void
    {
        $slot->update(['current_occupancy' => $count]);
        foreach (range(1, $count) as $i) {
            $hen = new Hen([
                'tag_code' => "HDEP-{$slot->id}-{$i}", 'breed' => 'ISA Brown',
                'flock_age_weeks' => 30, 'date_acquired' => now()->subMonths(6), 'placement_date' => now()->subMonths(6),
                'age_at_placement_weeks' => 0, 'is_active' => 1,
            ]);
            $hen->cage_slot_id = $slot->id;
            $hen->save();
        }
    }

    public function test_reaching_the_target_alerts_the_cage_and_the_flock_once_with_cage_hdep_target_and_date(): void
    {
        $this->log($this->slotA, 0, 9);  // 90%
        $this->log($this->slotB, 0, 8);  // 80%, flock 17/20 = 85%
        $this->check($this->slotA, 0);
        $this->check($this->slotB, 0);

        $cageAlerts = $this->alerts($this->cageA->id);
        $this->assertCount(1, $cageAlerts);
        $this->assertSame('CAGE-HDEP-A reached the Desired HDEP: 90.0% (target 85%) on Nov 10, 2026.', $cageAlerts[0]->message);
        $this->assertSame($this->day(0), $cageAlerts[0]->alert_day->toDateString());
        $this->assertSame($this->cageA->id, $cageAlerts[0]->cage_id);

        $flockAlerts = $this->alerts(null);
        $this->assertCount(1, $flockAlerts);
        $this->assertSame('All cages reached the Desired HDEP: 85.0% (target 85%) on Nov 10, 2026.', $flockAlerts[0]->message);
        $this->assertNull($flockAlerts[0]->cage_id);

        $this->assertCount(0, $this->alerts($this->slotB->cage_id));
    }

    public function test_more_readings_the_same_day_and_later_days_above_target_do_not_repeat_it(): void
    {
        $this->log($this->slotA, 0, 9);
        $this->check($this->slotA, 0);
        $this->log($this->slotA, 0, 10);
        $this->check($this->slotA, 0);

        foreach ([1, 2] as $offset) {
            $this->onDay($offset);
            $this->log($this->slotA, $offset, 9);
            $this->check($this->slotA, $offset);
        }

        $this->assertCount(1, $this->alerts($this->cageA->id));
        $this->assertCount(1, $this->alerts(null));
    }

    public function test_a_low_reading_early_in_the_day_does_not_rearm_it(): void
    {
        $this->log($this->slotA, 0, 9);
        $this->check($this->slotA, 0);

        $this->onDay(1);
        $this->log($this->slotA, 1, 2);  // morning: 20%, eggs still coming in
        $this->check($this->slotA, 1);
        $this->log($this->slotA, 1, 9);
        $this->check($this->slotA, 1);

        $this->assertCount(1, $this->alerts($this->cageA->id));
    }

    public function test_a_full_day_below_target_rearms_it_for_the_next_crossing(): void
    {
        $this->log($this->slotA, 0, 9);
        $this->check($this->slotA, 0);

        $this->onDay(1);
        $this->log($this->slotA, 1, 7);  // the day ends at 70%
        $this->check($this->slotA, 1);

        $this->onDay(2);
        $this->log($this->slotA, 2, 9);
        $this->check($this->slotA, 2);

        $alerts = $this->alerts($this->cageA->id);
        $this->assertCount(2, $alerts);
        $this->assertSame($this->day(2), $alerts[1]->alert_day->toDateString());
    }

    public function test_a_gap_in_the_data_neither_refires_it_nor_leaves_it_stuck(): void
    {
        $this->log($this->slotA, 0, 9);
        $this->check($this->slotA, 0);

        // Days 1-3 have no records (Pi offline): back above target, no repeat.
        $this->onDay(4);
        $this->log($this->slotA, 4, 9);
        $this->check($this->slotA, 4);
        $this->assertCount(1, $this->alerts($this->cageA->id));

        // A full day below target, then another gap: the next crossing alerts.
        $this->onDay(5);
        $this->log($this->slotA, 5, 7);
        $this->check($this->slotA, 5);
        $this->onDay(9);
        $this->log($this->slotA, 9, 9);
        $this->check($this->slotA, 9);

        $alerts = $this->alerts($this->cageA->id);
        $this->assertCount(2, $alerts);
        $this->assertSame($this->day(9), $alerts[1]->alert_day->toDateString());
    }

    public function test_back_filled_days_and_a_higher_desired_hdep_do_not_alert(): void
    {
        $this->onDay(5);
        $this->log($this->slotA, 2, 10);  // entered three days late
        $this->check($this->slotA, 2);
        $this->assertCount(0, $this->alerts($this->cageA->id));

        Setting::set('desired_hdep', 95);
        $this->log($this->slotA, 5, 9);  // 90% < 95%
        $this->check($this->slotA, 5);
        $this->assertCount(0, $this->alerts($this->cageA->id));
        $this->assertCount(0, $this->alerts(null));
    }

    public function test_manual_egg_logging_raises_the_alert(): void
    {
        $this->addHens($this->slotA, 10);

        $this->actingAs($this->admin)->post(route('eggs.logging.store'), [
            'log_date' => $this->day(0),
            'cage_slot_id' => $this->slotA->id,
            'egg_count' => 9,
        ])->assertRedirect();

        $this->assertCount(1, $this->alerts($this->cageA->id));
        $this->assertCount(1, $this->alerts(null));
    }

    public function test_sensor_counts_raise_the_alert_once_when_the_count_crosses_the_target(): void
    {
        // Morning in Manila, same calendar date in UTC (the app clock).
        $this->travelTo(Carbon::parse($this->day(0).' 10:00', 'Asia/Manila'));
        $this->addHens($this->slotA, 4);
        $device = Device::create(['name' => 'Test Pi', 'api_key_hash' => Hash::make('lr_testkey_known'), 'is_active' => true]);
        HardwareItem::create([
            'device_type' => 'IR_breakbeam', 'serial_number' => 'IRBBS-HDEP-001',
            'cage_slot_id' => $this->slotA->id, 'device_id' => $device->id, 'status' => 'active',
        ]);

        $post = fn (int $count, int $new, int $second) => $this->postJson('/api/sensor-readings', [
            'readings' => [['serial_number' => 'IRBBS-HDEP-001', 'count' => $count, 'trigger_count' => $count, 'new_trigger_count' => $new]],
            'recorded_at' => now()->startOfSecond()->addSeconds($second)->toDateTimeString(),
        ], ['X-Device-Key' => 'lr_testkey_known'])->assertOk();

        $post(0, 0, 1);  // first reading is only the counter baseline
        $post(3, 3, 2);  // 3 of 4 hens: 75%
        $this->assertCount(0, $this->alerts($this->cageA->id));

        $post(4, 1, 3);  // 4 of 4 hens: 100%
        $post(4, 0, 4);  // heartbeat, same count
        $this->assertSame(4, (int) ProductionLog::where('cage_slot_id', $this->slotA->id)->value('egg_count'));
        $this->assertCount(1, $this->alerts($this->cageA->id));
        $this->assertCount(1, $this->alerts(null));
    }
}
