<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EggStockBatch;
use App\Models\EnvironmentalLog;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Cage $cageA;
    private Cage $cageB;
    private CageSlot $slotA1;
    private CageSlot $slotB1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);

        $this->cageA = Cage::create([
            'cage_code' => 'CAGE-A',
            'location' => 'North',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);

        $this->cageB = Cage::create([
            'cage_code' => 'CAGE-B',
            'location' => 'South',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);

        $this->slotA1 = CageSlot::create([
            'cage_id' => $this->cageA->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 4,
        ]);

        $this->slotB1 = CageSlot::create([
            'cage_id' => $this->cageB->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 4,
        ]);
    }

    private function createHen(CageSlot $slot, string $breed, bool $active, string $tag): Hen
    {
        $hen = Hen::create([
            'tag_code' => $tag,
            'breed' => $breed,
            'flock_age_weeks' => 28,
            'date_acquired' => now()->subMonths(6)->toDateString(),
            'placement_date' => now()->subMonths(6)->toDateString(),
            'age_at_placement_weeks' => 0,
            'is_active' => $active ? 1 : 0,
        ]);
        $hen->cage_slot_id = $slot->id;
        $hen->save();

        return $hen;
    }

    private function createProductionLog(CageSlot $slot, int $eggs, string $date): ProductionLog
    {
        $log = new ProductionLog();
        $log->cage_slot_id = $slot->id;
        $log->log_date = $date;
        $log->egg_count = $eggs;
        $log->hen_count = 4;
        $log->hdep = round(($eggs / 4) * 100, 2);
        $log->logged_via = 'manual';
        $log->recorded_by = $this->user->id;
        $log->save();

        return $log;
    }

    /** @test */
    public function reports_page_loads_without_parameters()
    {
        $response = $this->actingAs($this->user)->get(route('reports'));

        $response->assertOk();
        $response->assertSee('Reports');
    }

    /**
     * Regression test for item #6 (Reports overhaul): every filter change already
     * triggers a live AJAX update, so the redundant "Generate Report" submit
     * button was removed — nothing should still render it.
     *
     * @test
     */
    public function generate_report_button_is_not_rendered()
    {
        $response = $this->actingAs($this->user)->get(route('reports'));

        $response->assertOk();
        $response->assertDontSee('Generate Report');
    }

    /** @test */
    public function production_report_renders_rows_and_summary()
    {
        $this->createHen($this->slotA1, 'ISA Brown', true, 'A-HEN1');
        $this->createProductionLog($this->slotA1, 4, now()->subDays(2)->toDateString());
        $this->createProductionLog($this->slotA1, 3, now()->subDay()->toDateString());

        $response = $this->actingAs($this->user)->get(route('reports', [
            'type' => 'production',
            'from' => now()->subDays(3)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('Total Eggs');
        $response->assertSee('7'); // 4 + 3
        $response->assertSee('ISA Brown');
    }

    /**
     * Regression test for item #93: the Production report must attribute the
     * breed of an ACTIVE hen, not whichever hen happens to come first in an
     * unfiltered collection. The inactive hen is created first (lower id) so
     * an unfiltered ->first() would wrongly pick it.
     *
     * @test
     */
    public function production_report_shows_active_hen_breed_not_inactive_predecessor()
    {
        $this->createHen($this->slotA1, 'Hy-Line Brown', false, 'A-OLD1'); // replaced hen
        $this->createHen($this->slotA1, 'ISA Brown', true, 'A-HEN1');     // current hen
        $this->createProductionLog($this->slotA1, 4, now()->subDay()->toDateString());

        $response = $this->actingAs($this->user)->get(route('reports', [
            'type' => 'production',
            'from' => now()->subDays(2)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('ISA Brown');
        $response->assertDontSee('Hy-Line Brown');
    }

    /**
     * Regression test for item #94 (part 1): the Egg Stock report type exists,
     * and "All Cages" includes farm-level batches whose cage_id is NULL.
     *
     * @test
     */
    public function egg_stock_report_all_cages_includes_null_cage_batches()
    {
        EggStockBatch::create(['egg_size' => 'large', 'count' => 333, 'harvested_date' => now()->subDays(3)->toDateString(), 'cage_id' => $this->cageA->id]);
        EggStockBatch::create(['egg_size' => 'medium', 'count' => 444, 'harvested_date' => now()->subDays(2)->toDateString(), 'cage_id' => $this->cageB->id]);
        EggStockBatch::create(['egg_size' => 'large', 'count' => 555, 'harvested_date' => now()->subDay()->toDateString(), 'cage_id' => null]);

        $response = $this->actingAs($this->user)->get(route('reports', [
            'type' => 'egg_stock',
            'from' => now()->subDays(5)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('Total Stocked');
        $response->assertSee('1332'); // 333 + 444 + 555 — NULL-cage batch included
        $response->assertSee('333');
        $response->assertSee('444');
        $response->assertSee('555');
    }

    /**
     * Regression test for item #94 (part 2): a specific cage filter excludes
     * farm-level (NULL-cage) batches and other cages' batches.
     *
     * @test
     */
    public function egg_stock_report_specific_cage_excludes_farm_level_batches()
    {
        EggStockBatch::create(['egg_size' => 'large', 'count' => 333, 'harvested_date' => now()->subDays(3)->toDateString(), 'cage_id' => $this->cageA->id]);
        EggStockBatch::create(['egg_size' => 'medium', 'count' => 444, 'harvested_date' => now()->subDays(2)->toDateString(), 'cage_id' => $this->cageB->id]);
        EggStockBatch::create(['egg_size' => 'large', 'count' => 555, 'harvested_date' => now()->subDay()->toDateString(), 'cage_id' => null]);

        $response = $this->actingAs($this->user)->get(route('reports', [
            'type' => 'egg_stock',
            'from' => now()->subDays(5)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'CAGE-A',
        ]));

        $response->assertOk();
        $response->assertSee('333');
        $response->assertDontSee('444');
        $response->assertDontSee('555');
    }

    /** @test */
    public function egg_stock_csv_export_streams_correct_rows()
    {
        EggStockBatch::create(['egg_size' => 'large', 'count' => 333, 'harvested_date' => now()->subDay()->toDateString(), 'cage_id' => $this->cageA->id]);

        $response = $this->actingAs($this->user)->get(route('reports.csv', [
            'type' => 'egg_stock',
            'from' => now()->subDays(5)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('date,cage,size,count,freshness', $csv);
        $this->assertStringContainsString('CAGE-A,Large,333', $csv);
    }

    /**
     * Regression test for item #84 (part 1): generating a report lands on a
     * preview first — not straight on the printable/full document — and that
     * preview is paginated (has its own "View Printable Report" link to reach
     * the full document) rather than showing every row.
     *
     * The preview and the printable document deliberately share the same
     * letterhead/summary/table/signature markup (see reports/_letterhead.php,
     * _meta-strip.php, _report-table.php, _signature-block.php) so the two
     * look identical — pagination is the only real difference — so this no
     * longer asserts the letterhead/signature block are full-doc-only.
     *
     * @test
     */
    public function generating_a_report_shows_preview_before_printable_document()
    {
        $this->createHen($this->slotA1, 'ISA Brown', true, 'A-HEN1');
        $this->createProductionLog($this->slotA1, 4, now()->subDay()->toDateString());

        $response = $this->actingAs($this->user)->get(route('reports', [
            'type' => 'production',
            'from' => now()->subDays(2)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('View Printable Report');
        $response->assertDontSee('Back to Preview'); // full-doc-only control
        $response->assertSee('Total Eggs');          // summary pills present in preview
        $response->assertSee('ISA Brown');           // data rows present in preview
        $response->assertSee('LayRate Poultry Farm'); // shared letterhead
        $response->assertSee('Noted by:');            // shared signature block
    }

    /**
     * Regression test for item #84 (part 2): ?full=1 renders the printable
     * letterhead document.
     *
     * @test
     */
    public function full_parameter_shows_printable_document()
    {
        $this->createHen($this->slotA1, 'ISA Brown', true, 'A-HEN1');
        $this->createProductionLog($this->slotA1, 4, now()->subDay()->toDateString());

        $response = $this->actingAs($this->user)->get(route('reports', [
            'type' => 'production',
            'from' => now()->subDays(2)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
            'full' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('LayRate Poultry Farm'); // letterhead
        $response->assertSee('Noted by:');            // signature block
        $response->assertSee('Back to Preview');
        $response->assertDontSee('View Printable Report'); // preview-only control
    }

    /** @test */
    public function empty_date_range_shows_no_data_message()
    {
        $response = $this->actingAs($this->user)->get(route('reports', [
            'type' => 'egg_stock',
            'from' => '2020-01-01',
            'to'   => '2020-01-07',
            'cage' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('No data found for the selected filters.');
    }

    private function seedAllReportTypes(): void
    {
        $this->createHen($this->slotA1, 'ISA Brown', true, 'A-HEN1');
        $this->createProductionLog($this->slotA1, 4, now()->subDay()->toDateString());

        $batch = FeedBatch::create([
            'crude_protein' => 17.5,
            'total_quantity_kg' => 100,
            'date_received' => now()->subDays(4)->toDateString(),
        ]);
        $feedLog = new FeedConsumptionLog();
        $feedLog->cage_id = $this->cageA->id;
        $feedLog->feed_batch_id = $batch->id;
        $feedLog->log_date = now()->subDay()->toDateString();
        $feedLog->feed_consumed_kg = 12.5;
        $feedLog->recorded_by = $this->user->id;
        $feedLog->save();

        EnvironmentalLog::create([
            'cage_id' => $this->cageA->id,
            'recorded_at' => now()->subDay(),
            'temperature_c' => 28.0,
            'humidity_pct' => 65.0,
        ]);

        $mortality = new MortalityLog();
        $mortality->cage_id = $this->cageA->id;
        $mortality->log_date = now()->subDay()->toDateString();
        $mortality->count = 1;
        $mortality->reason = 'Disease';
        $mortality->recorded_by = $this->user->id;
        $mortality->save();

        EggStockBatch::create(['egg_size' => 'large', 'count' => 30, 'harvested_date' => now()->subDay()->toDateString(), 'cage_id' => $this->cageA->id]);
    }

    /** @test */
    public function every_report_type_returns_200_with_data_present()
    {
        $this->seedAllReportTypes();

        foreach (['production', 'feed', 'environment', 'mortality', 'egg_stock'] as $type) {
            $response = $this->actingAs($this->user)->get(route('reports', [
                'type' => $type,
                'from' => now()->subDays(5)->toDateString(),
                'to'   => now()->toDateString(),
                'cage' => 'all',
            ]));

            $response->assertOk();
            $response->assertDontSee('No data found for the selected filters.');
        }
    }

    /**
     * Regression test for item #5 (Reports overhaul): type=all renders all
     * five report types as separate labeled sections on one page.
     *
     * @test
     */
    public function all_report_type_renders_every_section()
    {
        $this->seedAllReportTypes();

        $response = $this->actingAs($this->user)->get(route('reports', [
            'type' => 'all',
            'from' => now()->subDays(5)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('Production Report');
        $response->assertSee('Feed Report');
        $response->assertSee('Environment Report');
        $response->assertSee('Mortality Report');
        $response->assertSee('Egg Stock Report');
    }

    /**
     * Regression test for item #5: the mortality-only `reason` filter must stay
     * scoped to just the mortality section when type=all — it must not filter
     * out data in the other four sections.
     *
     * @test
     */
    public function all_report_type_scopes_reason_filter_to_mortality_section_only()
    {
        $this->seedAllReportTypes();

        // 'Predator' matches nothing seeded (seedAllReportTypes uses 'Disease'),
        // so the mortality section must come back empty while every other
        // section still shows its data.
        $response = $this->actingAs($this->user)->get(route('reports', [
            'type'   => 'all',
            'from'   => now()->subDays(5)->toDateString(),
            'to'     => now()->toDateString(),
            'cage'   => 'all',
            'reason' => 'Predator',
        ]));

        $response->assertOk();
        $response->assertSee('ISA Brown');   // production section unaffected
        $response->assertSee('12.50 kg');    // feed section unaffected
    }

    /** @test */
    public function excel_export_route_returns_a_downloadable_spreadsheet()
    {
        $this->seedAllReportTypes();

        $response = $this->actingAs($this->user)->get(route('reports.excel', [
            'type' => 'production',
            'from' => now()->subDays(5)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function pdf_export_route_returns_a_downloadable_pdf()
    {
        $this->seedAllReportTypes();

        $response = $this->actingAs($this->user)->get(route('reports.pdf', [
            'type' => 'production',
            'from' => now()->subDays(5)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    /**
     * Regression test: the "Export" dropdown's PDF/Excel links are captured by
     * exportReportWithCharts() in reports.blade.php, which POSTs the filters as
     * a JSON body (Content-Type: application/json) instead of a query string —
     * needed so it can attach chart_images alongside them. filtersFromRequest()
     * previously read type/from/to/cage/reason via $request->get(), which only
     * checks route attributes, the query string, and form-urlencoded POST data —
     * never the JSON body (Illuminate\Http\Request::get() just delegates to
     * Symfony's, which Laravel's own docblock marks deprecated in favor of
     * input()). Every JSON POST export silently fell back to the defaults
     * (type=production, from=to=null i.e. "all time"), regardless of what the
     * user actually had selected, so the download never matched the preview.
     *
     * @test
     */
    public function pdf_export_via_json_post_honors_the_posted_filters_not_defaults()
    {
        $this->seedAllReportTypes();

        $response = $this->actingAs($this->user)->postJson(route('reports.pdf'), [
            'type' => 'mortality',
            'from' => now()->subDays(5)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        // The filename embeds $type and the date range — if the JSON body were
        // ignored (the bug) this would be "layrate_production_all_time.pdf".
        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('layrate_mortality_', $disposition);
        $this->assertStringNotContainsString('layrate_production_all_time', $disposition);
    }

    /** @test */
    public function excel_export_via_json_post_honors_the_posted_filters_not_defaults()
    {
        $this->seedAllReportTypes();

        $response = $this->actingAs($this->user)->postJson(route('reports.excel'), [
            'type' => 'mortality',
            'from' => now()->subDays(5)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]);

        $response->assertOk();
        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('layrate_mortality_', $disposition);
        $this->assertStringNotContainsString('layrate_production_all_time', $disposition);
    }

    /**
     * Regression test: chart_images sent as a JSON POST body must land in the
     * type the user actually selected — not silently get dropped because the
     * export resolved to the wrong (default) type/section, per the bug above.
     *
     * @test
     */
    public function pdf_export_via_json_post_embeds_the_posted_chart_image()
    {
        $this->seedAllReportTypes();

        // 1x1 transparent PNG.
        $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        $withChart = $this->actingAs($this->user)->postJson(route('reports.pdf'), [
            'type'         => 'mortality',
            'from'         => now()->subDays(5)->toDateString(),
            'to'           => now()->toDateString(),
            'cage'         => 'all',
            'chart_images' => ['mortality' => 'data:image/png;base64,' . $pngBase64],
        ]);
        $withChart->assertOk();
        $withChart->assertHeader('content-type', 'application/pdf');

        $withoutChart = $this->actingAs($this->user)->postJson(route('reports.pdf'), [
            'type' => 'mortality',
            'from' => now()->subDays(5)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]);
        $withoutChart->assertOk();

        // The letterhead's logo (reports/pdf.blade.php) means every generated
        // PDF already embeds one "/Image" XObject, so presence alone no longer
        // proves the chart made it in — compare counts instead: posting a chart
        // image must add one more "/Image" than the same export without one.
        $withChartCount    = substr_count($withChart->getContent(), '/Image');
        $withoutChartCount = substr_count($withoutChart->getContent(), '/Image');
        $this->assertGreaterThan($withoutChartCount, $withChartCount);
    }

    /** @test */
    public function all_report_type_excel_export_returns_a_downloadable_spreadsheet()
    {
        $this->seedAllReportTypes();

        $response = $this->actingAs($this->user)->get(route('reports.excel', [
            'type' => 'all',
            'from' => now()->subDays(5)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /**
     * Regression test: type=all with charts produces one ReportSheetExport
     * per section (5 for the default types), each holding its own temp chart
     * PNG path. ReportSheetExport used to delete its temp file(s) in its own
     * __destruct() as a "safety net" — but PhpSpreadsheet's Xlsx writer embeds
     * every sheet's drawings into [Content_Types].xml as one of the *last*
     * steps of the whole save(), by which point earlier sheets' export objects
     * had already been garbage-collected (destructing, deleting their temp
     * PNG) while the writer still needed to read them, throwing
     * "File ... does not exist". A single-sheet export (one object, stays
     * referenced throughout) never hit this, which is why only the "all
     * reports" combination reproduced it. The fix removed that destructor —
     * cleanup is the controller's register_shutdown_function only, which
     * correctly waits for the whole request (including the full write) to
     * finish. This test loads the real output file back with PhpSpreadsheet
     * and checks every sheet actually has its drawing, not just that the HTTP
     * response came back 200.
     *
     * @test
     */
    public function all_report_type_excel_export_with_charts_embeds_a_drawing_on_every_sheet()
    {
        $this->seedAllReportTypes();

        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        $response = $this->actingAs($this->user)->postJson(route('reports.excel'), [
            'type' => 'all',
            'from' => now()->subDays(5)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
            'chart_images' => [
                'production'  => $png,
                'feed'        => $png,
                'environment' => $png,
                'mortality'   => $png,
                'egg_stock'   => $png,
            ],
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx_test_');
        file_put_contents($tmpPath, $response->streamedContent());

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpPath);
            $this->assertCount(5, $spreadsheet->getAllSheets());
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $this->assertCount(1, $sheet->getDrawingCollection(), "Sheet '{$sheet->getTitle()}' is missing its chart drawing");
            }
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Regression test for item #4: the "Include Graphs" checkbox (charts=1)
     * must render a chart canvas for the selected type, and must not blow up
     * server-side chart-data queries.
     *
     * @test
     */
    public function charts_checkbox_renders_chart_canvas_for_single_type()
    {
        $this->seedAllReportTypes();

        $response = $this->actingAs($this->user)->get(route('reports', [
            'type'   => 'production',
            'from'   => now()->subDays(5)->toDateString(),
            'to'     => now()->toDateString(),
            'cage'   => 'all',
            'charts' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('id="chart-production"', false);
    }

    /**
     * Regression test for items #1, #4, #5 combined: the printable document
     * for type=all with graphs on must render every section's own chart
     * canvas without error.
     *
     * @test
     */
    public function all_report_type_full_view_with_charts_renders_every_chart_canvas()
    {
        $this->seedAllReportTypes();

        $response = $this->actingAs($this->user)->get(route('reports', [
            'type'   => 'all',
            'from'   => now()->subDays(5)->toDateString(),
            'to'     => now()->toDateString(),
            'cage'   => 'all',
            'full'   => 1,
            'charts' => 1,
        ]));

        $response->assertOk();
        foreach (['production', 'feed', 'environment', 'mortality', 'egg_stock'] as $type) {
            $response->assertSee('id="chart-' . $type . '"', false);
        }
    }
}
