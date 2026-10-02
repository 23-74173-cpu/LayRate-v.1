<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Device;
use App\Models\HardwareItem;
use App\Models\Hen;
use App\Models\Note;
use App\Models\ProductionLog;
use App\Models\User;
use App\Services\ReportingDateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * KPI cards added to Egg Logging, Cages, Recent Logs, Production History
 * (calendar) and Notes: each card shows the number its data says.
 */
class SectionKpiCardsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Cage $cage;
    /** @var CageSlot[] */
    private array $slots = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);

        // 1 active cage: 1 row x 2 slots x 4 hens = capacity 8.
        $this->cage = Cage::create([
            'cage_code' => 'CAGE-K', 'location' => 'Test', 'rows' => 1, 'slots_per_row' => 2,
            'max_chickens_per_slot' => 4, 'total_capacity' => 8, 'is_active' => 1,
        ]);
        foreach ([1, 2] as $n) {
            $this->slots[] = CageSlot::create([
                'cage_id' => $this->cage->id, 'slot_number' => $n, 'row_number' => 1,
                'column_number' => $n, 'current_occupancy' => $n === 1 ? 4 : 0,
            ]);
        }
        // 4 active hens, all in slot 1.
        for ($i = 1; $i <= 4; $i++) {
            $hen = Hen::create([
                'tag_code' => "KPI-HEN{$i}", 'breed' => 'ISA Brown', 'flock_age_weeks' => 30,
                'date_acquired' => now()->subMonths(3)->toDateString(),
                'placement_date' => now()->subMonths(3)->toDateString(),
                'age_at_placement_weeks' => 18, 'is_active' => 1,
            ]);
            $hen->cage_slot_id = $this->slots[0]->id;
            $hen->save();
        }
    }

    private function log(CageSlot $slot, string $date, int $eggs, string $via = 'manual', bool $overridden = false): ProductionLog
    {
        $log = new ProductionLog();
        $log->cage_slot_id = $slot->id;
        $log->log_date = $date;
        $log->egg_count = $eggs;
        $log->hen_count = 4;
        $log->logged_via = $via;
        if ($overridden) {
            $log->overridden_by_user_id = $this->user->id;
        }
        $log->save();

        return $log;
    }

    public function test_egg_logging_cards(): void
    {
        $today = ReportingDateService::reportingDateString();
        $this->log($this->slots[0], $today, 3);

        $this->actingAs($this->user)->get(route('eggs.logging'))
            ->assertOk()
            ->assertSee('<span id="elKpiEggs">3</span>', false)
            ->assertSee('<span id="elKpiHdep">75.0</span>%', false)   // 3 eggs / 4 hens
            ->assertSee('<span id="elKpiSlots">1</span>', false)
            ->assertSee('<span id="elKpiSlotsLeft">1</span> left to log', false)
            ->assertSee('<span id="elKpiCages">0</span>', false)
            ->assertSee('4 hens placed');

        $this->log($this->slots[1], $today, 2);
        $this->actingAs($this->user)->get(route('eggs.logging'))
            ->assertSee('<span id="elKpiEggs">5</span>', false)
            ->assertSee('<span id="elKpiCages">1</span>', false)
            ->assertSee('<span id="elKpiSlotsLeft">0</span> left to log', false);
    }

    public function test_cages_cards(): void
    {
        Cage::create([
            'cage_code' => 'CAGE-OFF', 'location' => 'Old', 'rows' => 1, 'slots_per_row' => 1,
            'max_chickens_per_slot' => 4, 'total_capacity' => 4, 'is_active' => 0,
        ]);
        $device = Device::create(['name' => 'Pi', 'api_key_hash' => Hash::make('x'), 'is_active' => true]);
        HardwareItem::create([
            'device_type' => 'IR_breakbeam', 'serial_number' => 'IRBBS-KPI-1',
            'cage_slot_id' => $this->slots[0]->id, 'device_id' => $device->id, 'status' => 'active',
        ]);

        $page = $this->actingAs($this->user)->get(route('cages.index'))->assertOk();
        $html = $page->getContent();
        $this->assertKpi($html, 'Active Cages', '1');
        $page->assertSee('2 total · 2 slots');
        $this->assertKpi($html, 'Hens Housed', '4');
        $page->assertSee('50.0% of 8 capacity');
        $this->assertKpi($html, 'Open Spaces', '4');
        $page->assertSee('1 empty slot');
        $this->assertKpi($html, 'Sensor Coverage', '1');
        $page->assertSee('of 2 slots have an IR sensor');
    }

    public function test_recent_logs_cards_follow_filters(): void
    {
        $other = Cage::create([
            'cage_code' => 'CAGE-Z', 'location' => 'Z', 'rows' => 1, 'slots_per_row' => 1,
            'max_chickens_per_slot' => 4, 'total_capacity' => 4, 'is_active' => 1,
        ]);
        $otherSlot = CageSlot::create(['cage_id' => $other->id, 'slot_number' => 1, 'row_number' => 1, 'column_number' => 1, 'current_occupancy' => 0]);

        $this->log($this->slots[0], now()->subDays(1)->toDateString(), 4, 'sensor');
        $this->log($this->slots[0], now()->subDays(2)->toDateString(), 3, 'sensor', true);
        $this->log($this->slots[1], now()->subDays(1)->toDateString(), 2, 'manual');
        $this->log($otherSlot, now()->subDays(1)->toDateString(), 9, 'manual');

        $all = $this->actingAs($this->user)->get(route('eggs.logging.logs'))->assertOk()->getContent();
        $this->assertKpi($all, 'Records', '4');
        $this->assertKpi($all, 'Eggs Logged', '18');
        $this->assertKpi($all, 'Logged by IR', '50%');
        $this->assertKpi($all, 'Overridden', '1');

        $one = $this->actingAs($this->user)->get(route('eggs.logging.logs', ['cage_id' => $this->cage->id]))->getContent();
        $this->assertKpi($one, 'Records', '3');
        $this->assertKpi($one, 'Eggs Logged', '9');
        $this->assertKpi($one, 'Logged by IR', '67%');
    }

    public function test_calendar_cards_for_a_past_month(): void
    {
        $this->log($this->slots[0], '2026-03-05', 10);
        $this->log($this->slots[1], '2026-03-05', 6);
        $this->log($this->slots[0], '2026-03-09', 20);
        $this->log($this->slots[0], '2026-04-01', 99); // next month: not counted

        $page = $this->actingAs($this->user)->get(route('dashboard.calendar', ['month' => 3, 'year' => 2026]))->assertOk();
        $html = $page->getContent();
        $this->assertKpi($html, 'Month Total', '36');
        $this->assertKpi($html, 'Daily Average', '18');   // 36 eggs / 2 logged days
        $this->assertKpi($html, 'Best Day', '20');
        $page->assertSee('03/09/2026');
        $this->assertStringContainsString('2<span class="text-base font-semibold" style="color:#6B7280"> / 31</span>', $html);
    }

    public function test_notes_cards(): void
    {
        Note::create(['body' => 'Fresh note', 'category' => 'Feed', 'cage_id' => $this->cage->id]);
        Note::create(['body' => 'Another feed note', 'category' => 'Feed']);
        $old = Note::create(['body' => 'Old note', 'category' => 'General']);
        $old->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

        $page = $this->actingAs($this->user)->get(route('notes.index'))->assertOk();
        $html = $page->getContent();
        $this->assertKpi($html, 'Total Notes', '3');
        $page->assertSee('across 2 sections');
        $this->assertKpi($html, 'This Week', '2');
        $this->assertKpi($html, 'Top Section', 'Feed');
        $page->assertSee('2 notes');
        $this->assertKpi($html, 'Linked to a Cage', '1');
    }

    /** The value shown in the KPI card with this label. */
    private function assertKpi(string $html, string $label, string $expected): void
    {
        $label = e($label);
        $this->assertMatchesRegularExpression(
            '#<span class="kpi-label[^"]*">\s*' . preg_quote($label, '#') . '\s*</span>.*?<div class="kpi-number kpi-value">(.*?)</div>#s',
            $html,
            "KPI card '{$label}' not found"
        );
        preg_match('#<span class="kpi-label[^"]*">\s*' . preg_quote($label, '#') . '\s*</span>.*?<div class="kpi-number kpi-value">(.*?)</div>#s', $html, $m);
        $this->assertSame($expected, trim(strip_tags(preg_replace('#<span class="text-base.*?</span>#s', '', $m[1]))), "KPI card '{$label}'");
    }
}
