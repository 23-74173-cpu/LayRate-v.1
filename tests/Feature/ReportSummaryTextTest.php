<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\MortalityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Report summaries are plain "Label: value" text with units, and the
 * mortality "Most Affected" / "Top Cause" lines are rebuilt from the same
 * filtered rows as the table, naming every tied cage instead of one.
 */
class ReportSummaryTextTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    /** @var array<string, Cage> */
    private array $cages = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);

        foreach (['CAGE-A', 'CAGE-B', 'CAGE-C'] as $code) {
            $this->cages[$code] = Cage::create([
                'cage_code' => $code, 'location' => 'Test', 'rows' => 1,
                'slots_per_row' => 1, 'max_chickens_per_slot' => 4, 'total_capacity' => 4, 'is_active' => 1,
            ]);
        }
    }

    private function deaths(string $cage, int $count, string $reason = 'Disease', int $daysAgo = 1): void
    {
        MortalityLog::create([
            'cage_id'     => $this->cages[$cage]->id,
            'log_date'    => now()->subDays($daysAgo)->toDateString(),
            'count'       => $count,
            'reason'      => $reason,
            'recorded_by' => $this->user->id,
        ]);
    }

    private function mortalityReport(array $extra = [])
    {
        return $this->actingAs($this->user)->get(route('reports', array_merge([
            'type' => 'mortality',
            'from' => now()->subDays(10)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ], $extra)));
    }

    public function test_single_most_affected_cage_shows_its_count(): void
    {
        $this->deaths('CAGE-A', 5);
        $this->deaths('CAGE-B', 2);

        $this->mortalityReport()
            ->assertOk()
            ->assertSee('Most Affected:')
            ->assertSee('CAGE-A (5 hens)')
            ->assertSee('Total Deaths:')
            ->assertSee('7 hens');
    }

    public function test_two_tied_cages_are_both_named(): void
    {
        $this->deaths('CAGE-B', 3);
        $this->deaths('CAGE-A', 3);
        $this->deaths('CAGE-C', 1);

        $this->mortalityReport()
            ->assertOk()
            ->assertSee('CAGE-A and CAGE-B are equally affected (3 hens each)');
    }

    public function test_three_tied_cages_are_all_named(): void
    {
        $this->deaths('CAGE-C', 2);
        $this->deaths('CAGE-A', 2);
        $this->deaths('CAGE-B', 1);
        $this->deaths('CAGE-B', 1, 'Injury', 2);

        $this->mortalityReport()
            ->assertOk()
            ->assertSee('CAGE-A, CAGE-B, and CAGE-C are equally affected (2 hens each)');
    }

    public function test_summary_follows_the_reason_filter(): void
    {
        // Without the filter CAGE-A leads; with reason=Predator only CAGE-B
        // has deaths, and the summary must say so (it used to keep showing
        // CAGE-A from the unfiltered data).
        $this->deaths('CAGE-A', 5, 'Disease');
        $this->deaths('CAGE-B', 2, 'Predator');

        $this->mortalityReport(['reason' => 'Predator'])
            ->assertOk()
            ->assertSee('CAGE-B (2 hens)')
            ->assertDontSee('CAGE-A (5 hens)')
            ->assertSee('Predator (2 hens)')
            ->assertSee('2 hens');
    }

    public function test_printable_report_uses_the_same_filtered_summary(): void
    {
        $this->deaths('CAGE-A', 4, 'Disease');
        $this->deaths('CAGE-B', 4, 'Disease');
        $this->deaths('CAGE-C', 9, 'Heat Stress');

        $this->mortalityReport(['reason' => 'Disease', 'full' => 1])
            ->assertOk()
            ->assertSee('CAGE-A and CAGE-B are equally affected (4 hens each)')
            ->assertDontSee('CAGE-C (9 hens)');
    }

    public function test_tied_top_cause_names_every_cause(): void
    {
        $this->deaths('CAGE-A', 2, 'Disease');
        $this->deaths('CAGE-B', 2, 'Heat Stress');

        $this->mortalityReport()
            ->assertOk()
            ->assertSee('Disease and Heat Stress (tied, 2 hens each)');
    }

    public function test_all_reports_mortality_section_uses_reason_filter(): void
    {
        $this->deaths('CAGE-A', 5, 'Disease');
        $this->deaths('CAGE-B', 2, 'Predator');

        $this->actingAs($this->user)->get(route('reports', [
            'type'   => 'all',
            'from'   => now()->subDays(10)->toDateString(),
            'to'     => now()->toDateString(),
            'cage'   => 'all',
            'reason' => 'Predator',
        ]))
            ->assertOk()
            ->assertSee('CAGE-B (2 hens)')
            ->assertDontSee('CAGE-A (5 hens)');
    }

    public function test_no_mortality_names_no_cage(): void
    {
        // Single-type report with no rows shows the empty message, no summary.
        $this->mortalityReport()
            ->assertOk()
            ->assertSee('No data found for the selected filters.')
            ->assertDontSee('equally affected');

        // All Reports always shows each section's summary: no cage is named.
        $this->actingAs($this->user)->get(route('reports', [
            'type' => 'all',
            'from' => now()->subDays(10)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]))
            ->assertOk()
            ->assertSee('Total Deaths:')
            ->assertSee('0 hens')
            ->assertDontSee('CAGE-A (');
    }

    public function test_summary_is_plain_text_not_scorecards(): void
    {
        $this->deaths('CAGE-A', 1);

        $html = $this->mortalityReport()->assertOk()->getContent();

        $this->assertStringContainsString('class="report-summary', $html);
        $this->assertStringContainsString('Days Covered:', $html);
        $this->assertStringContainsString('1 day', $html);
        // The summary no longer renders dashboard KPI cards.
        $this->assertStringNotContainsString('kpi-card', $html);
    }

    public function test_feed_summary_has_units_and_real_per_day_average(): void
    {
        $batch = FeedBatch::create(['batch_code' => 'FB-U', 'brand' => 'T', 'crude_protein' => 17, 'total_quantity_kg' => 100, 'date_received' => now()->subDays(5)]);
        // Two feedings on the same day: 6 + 4 kg = 10 kg in 1 day.
        foreach ([6, 4] as $kg) {
            FeedConsumptionLog::create(['cage_id' => $this->cages['CAGE-A']->id, 'feed_batch_id' => $batch->id, 'log_date' => now()->subDay()->toDateString(), 'feed_consumed_kg' => $kg, 'recorded_by' => $this->user->id]);
        }

        $this->actingAs($this->user)->get(route('reports', [
            'type' => 'feed',
            'from' => now()->subDays(10)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]))
            ->assertOk()
            ->assertSee('Total Consumed:')
            ->assertSee('10.0 kg')
            ->assertSee('10.0 kg/day')
            ->assertSee('1 batch');
    }

    public function test_report_dates_are_month_day_year(): void
    {
        MortalityLog::create([
            'cage_id' => $this->cages['CAGE-A']->id, 'log_date' => '2026-01-15',
            'count' => 1, 'reason' => 'Disease', 'recorded_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)->get(route('reports', [
            'type' => 'mortality', 'from' => '2026-01-01', 'to' => '2026-01-31', 'cage' => 'all',
        ]))
            ->assertOk()
            ->assertSee('01/15/2026')
            ->assertSee('01/01/2026 — 01/31/2026');
    }

    public function test_bad_date_filter_does_not_break_the_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('reports', ['type' => 'mortality', 'from' => 'abc', 'to' => 'xyz']))
            ->assertOk();
    }

    public function test_pdf_export_still_renders(): void
    {
        $this->deaths('CAGE-A', 3);
        $this->deaths('CAGE-B', 3);

        $response = $this->actingAs($this->user)->get(route('reports.pdf', [
            'type' => 'mortality',
            'from' => now()->subDays(10)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
