<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\EggStockBatch;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EggStockQrScanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Cage $cage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);
        $this->cage = Cage::create([
            'cage_code' => 'CAGE-Q',
            'location' => 'Test',
            'rows' => 1,
            'slots_per_row' => 1,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 4,
            'is_active' => 1,
        ]);
        Setting::set('egg_freshness_fresh_days', 7);
        Setting::set('egg_freshness_aging_days', 14);
    }

    private function batch(int $daysAgo, string $size = 'large', int $count = 60): EggStockBatch
    {
        return EggStockBatch::create([
            'cage_id' => $this->cage->id,
            'egg_size' => $size,
            'count' => $count,
            'harvested_date' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }

    /** The exact payload EggStockController::qr() prints on the label. */
    private function label(EggStockBatch $b): string
    {
        return "LAYRATE|{$b->id}|{$b->harvested_date->toDateString()}|{$this->cage->cage_code}|{$b->egg_size}|{$b->count}";
    }

    private function scan(string $code)
    {
        return $this->actingAs($this->user)->getJson(route('eggs.stocks.scan', ['code' => $code]));
    }

    public function test_label_printed_by_qr_page_is_accepted(): void
    {
        $b = $this->batch(2);
        $page = $this->actingAs($this->user)->get(route('eggs.stocks.qr', $b));
        $page->assertOk();
        $page->assertSee($this->label($b), false);

        $this->scan($this->label($b))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('found', true)
            ->assertJsonPath('changed', false)
            ->assertJsonPath('batch.id', $b->id)
            ->assertJsonPath('batch.cage_code', 'CAGE-Q')
            ->assertJsonPath('batch.count', 60)
            ->assertJsonPath('batch.trays', 2)
            ->assertJsonPath('batch.harvested', $b->harvested_date->format('m/d/Y'))
            ->assertJsonPath('freshness.status', 'fresh')
            ->assertJsonPath('freshness.days_old', 2);
    }

    public function test_freshness_matches_the_batch_table_status(): void
    {
        foreach ([0 => 'fresh', 7 => 'fresh', 8 => 'aging', 14 => 'aging', 15 => 'old', 40 => 'old'] as $days => $expected) {
            $b = $this->batch($days);
            $this->assertSame($expected, $b->freshness_status, "table status at {$days} days");
            $this->scan($this->label($b))->assertJsonPath('freshness.status', $expected);
        }
    }

    public function test_uses_current_batch_details_and_flags_edited_batch(): void
    {
        $b = $this->batch(1);
        $printed = $this->label($b);
        $b->update(['harvested_date' => now()->subDays(10)->toDateString(), 'count' => 30]);

        $this->scan($printed)
            ->assertJsonPath('found', true)
            ->assertJsonPath('changed', true)
            ->assertJsonPath('batch.count', 30)
            ->assertJsonPath('freshness.status', 'aging');
    }

    public function test_deleted_batch_falls_back_to_label_date(): void
    {
        $b = $this->batch(20, 'small', 45);
        $printed = $this->label($b);
        $b->delete();

        $this->scan($printed)
            ->assertOk()
            ->assertJsonPath('found', false)
            ->assertJsonPath('batch.size', 'small')
            ->assertJsonPath('batch.count', 45)
            ->assertJsonPath('batch.cage_code', 'CAGE-Q')
            ->assertJsonPath('freshness.status', 'old');
    }

    public function test_codes_that_are_not_layrate_labels_are_rejected(): void
    {
        foreach (['https://example.com', '', 'LAYRATE|1|2026-02-30|CAGE-Q|large|10', 'LAYRATE|x|2026-01-01|A|large|1'] as $code) {
            $this->scan($code)->assertStatus(422)->assertJsonPath('ok', false);
        }
    }

    public function test_guests_cannot_scan(): void
    {
        $b = $this->batch(1);
        $this->getJson(route('eggs.stocks.scan', ['code' => $this->label($b)]))->assertUnauthorized();
    }

    public function test_stocks_page_includes_mobile_scanner(): void
    {
        $this->actingAs($this->user)->get(route('eggs.stocks'))
            ->assertOk()
            ->assertSee('id="eggQrScanner"', false)
            ->assertSee('openEggQrScanner()', false)
            ->assertSee('/js/jsqr.min.js', false);
    }
}
