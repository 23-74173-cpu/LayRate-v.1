<?php

namespace Tests\Feature;

use App\Http\Controllers\PrintableTagsController;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EggStockBatch;
use App\Models\Hen;
use App\Models\User;
use App\Services\QrSvg;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintableTagsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Cage $cageA;
    private Cage $cageB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
        $this->cageA = Cage::create(['cage_code' => 'CAGE-A', 'location' => 'T', 'rows' => 1, 'slots_per_row' => 2, 'max_chickens_per_slot' => 4, 'total_capacity' => 8, 'is_active' => 1]);
        $this->cageB = Cage::create(['cage_code' => 'CAGE-B', 'location' => 'T', 'rows' => 1, 'slots_per_row' => 1, 'max_chickens_per_slot' => 4, 'total_capacity' => 4, 'is_active' => 1]);
    }

    private function batch(int $daysAgo, string $size = 'large', int $count = 90, ?Cage $cage = null): EggStockBatch
    {
        return EggStockBatch::create(['cage_id' => ($cage ?? $this->cageA)->id, 'egg_size' => $size, 'count' => $count,
            'harvested_date' => now()->subDays($daysAgo)->toDateString()]);
    }

    private function hen(Cage $cage, int $slotNumber, string $tag, bool $active = true): Hen
    {
        $slot = CageSlot::firstOrCreate(['cage_id' => $cage->id, 'slot_number' => $slotNumber],
            ['row_number' => 1, 'column_number' => $slotNumber, 'current_occupancy' => 1]);
        $hen = Hen::create(['tag_code' => $tag, 'chicken_id' => 'CHK-' . $tag, 'breed' => 'ISA Brown', 'flock_age_weeks' => 30,
            'date_acquired' => now()->subMonths(2)->toDateString(), 'placement_date' => now()->subMonths(2)->toDateString(),
            'age_at_placement_weeks' => 18, 'is_active' => $active ? 1 : 0]);
        $hen->cage_slot_id = $slot->id;
        $hen->save();

        return $hen;
    }

    /** Render the view the PDF is built from, to check its content. */
    private function labelsHtml(array $query = []): string
    {
        $captured = null;
        \Illuminate\Support\Facades\View::composer('printables.*', function ($view) use (&$captured) { $captured = $view; });
        $this->actingAs($this->user)->get(route('eggs.stocks.labels-pdf', $query))->assertOk();

        return $captured->render();
    }

    public function test_pdf_payload_is_identical_to_the_screen_qr_page(): void
    {
        $b = $this->batch(2);
        $page = $this->actingAs($this->user)->get(route('eggs.stocks.qr', $b))->assertOk();
        $payload = PrintableTagsController::eggStockPayload($b->fresh('cage'));
        $this->assertSame("LAYRATE|{$b->id}|{$b->harvested_date->toDateString()}|CAGE-A|large|90", $payload);
        $page->assertSee(json_encode($payload), false);   // the page encodes this exact string
        $page->assertSee('Print')->assertSee('Download PDF');
        $page->assertSee(route('eggs.stocks.labels-pdf', ['ids' => [$b->id]]), false);
    }

    public function test_egg_labels_pdf_streams_and_lists_every_batch_with_details(): void
    {
        $this->batch(1, 'large', 90);
        $this->batch(10, 'small', 31, $this->cageB);

        $res = $this->actingAs($this->user)->get(route('eggs.stocks.labels-pdf'));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $res->getContent());

        $html = $this->labelsHtml();
        $this->assertSame(2, substr_count($html, 'data:image/svg+xml;base64,'), 'one QR per label');
        $this->assertStringContainsString('90 eggs · 3 trays', $html);
        $this->assertStringContainsString('31 eggs · 2 trays', $html);
        $this->assertStringContainsString('CAGE-B', $html);
        $this->assertStringContainsString('Fresh until', $html);
        $this->assertStringContainsString('2 labels', $html);
    }

    public function test_egg_labels_filters_by_range_and_ids(): void
    {
        $recent = $this->batch(2);
        $old = $this->batch(20, 'small');

        $this->assertStringContainsString('1 label', $this->labelsHtml(['range' => '7']));
        $this->assertStringContainsString('2 labels', $this->labelsHtml(['range' => 'all']));
        $one = $this->labelsHtml(['ids' => [$old->id]]);
        $this->assertStringContainsString('#' . $old->id, $one);
        $this->assertStringNotContainsString('#' . $recent->id . '<', $one);
    }

    public function test_label_qr_encodes_the_batch_payload(): void
    {
        $b = $this->batch(3, 'jumbo', 12);
        $html = $this->labelsHtml(['ids' => [$b->id]]);
        $expected = QrSvg::dataUri(PrintableTagsController::eggStockPayload($b->fresh('cage')));
        $this->assertStringContainsString($expected, $html);
    }

    public function test_every_paper_size_renders_and_empty_sheet_is_not_an_error(): void
    {
        foreach (array_keys(PrintableTagsController::PAPERS) as $paper) {
            $this->actingAs($this->user)->get(route('eggs.stocks.labels-pdf', ['paper' => $paper]))->assertOk();
            $this->actingAs($this->user)->get(route('chickens.foot-tags-pdf', ['paper' => $paper]))->assertOk();
        }
        $this->assertStringContainsString('No egg stock batches match', $this->labelsHtml());
        $this->actingAs($this->user)->get(route('eggs.stocks.labels-pdf', ['paper' => 'poster']))->assertSessionHasErrors('paper');
    }

    public function test_foot_tags_list_active_placed_hens_and_filter_by_cage(): void
    {
        $this->hen($this->cageA, 1, 'CAGE-A-S01-H1');
        $this->hen($this->cageA, 2, 'CAGE-A-S02-H1');
        $this->hen($this->cageB, 1, 'CAGE-B-S01-H1');
        $this->hen($this->cageB, 1, 'CAGE-B-S01-H2', false);   // inactive: no tag

        $captured = null;
        \Illuminate\Support\Facades\View::composer('printables.*', function ($view) use (&$captured) { $captured = $view; });

        $res = $this->actingAs($this->user)->get(route('chickens.foot-tags-pdf'));
        $res->assertOk();
        $this->assertStringStartsWith('%PDF-', $res->getContent());
        $html = $captured->render();
        $this->assertSame(3, substr_count($html, 'class="code"'));
        $this->assertStringNotContainsString('CAGE-B-S01-H2', $html);
        $this->assertStringContainsString('CAGE-A · slot 1-1 · ISA Brown · CHK-CAGE-A-S01-H1', $html);
        // Sorted by cage, then slot.
        $this->assertLessThan(strpos($html, 'CAGE-A-S02-H1'), strpos($html, 'CAGE-A-S01-H1'));
        $this->assertLessThan(strpos($html, 'CAGE-B-S01-H1'), strpos($html, 'CAGE-A-S02-H1'));

        $this->actingAs($this->user)->get(route('chickens.foot-tags-pdf', ['cage_id' => $this->cageB->id]))->assertOk();
        $html = $captured->render();
        $this->assertSame(1, substr_count($html, 'class="code"'));
        $this->assertStringContainsString('CAGE-B · 1 hen', $html);
    }

    public function test_guests_cannot_print(): void
    {
        $this->get(route('eggs.stocks.labels-pdf'))->assertRedirect(route('login'));
        $this->get(route('chickens.foot-tags-pdf'))->assertRedirect(route('login'));
    }

    public function test_print_options_are_on_both_pages(): void
    {
        $this->actingAs($this->user)->get(route('eggs.stocks'))->assertOk()
            ->assertSee('id="eggLabelsModal"', false)
            ->assertSee('Print All QR Labels')
            ->assertSee(route('eggs.stocks.labels-pdf'), false);
        $this->actingAs($this->user)->get(route('chickens.index'))->assertOk()
            ->assertSee('id="henFootTagsModal"', false)
            ->assertSee('Print Foot Tags')
            ->assertSee(route('chickens.foot-tags-pdf'), false);
    }
}
