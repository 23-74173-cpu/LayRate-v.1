<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Services\FcrCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoricalCsvImportTest extends TestCase
{
    public function test_aggregate_csv_import_reconciles_exact_totals_historical_snapshots_and_is_idempotent(): void
    {
        $this->assertSame('layrate_qa_20261027', DB::connection()->getDatabaseName());

        $this->artisan('db:seed', ['--class' => \Database\Seeders\DatabaseSeeder::class])->assertSuccessful();
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->artisan('farm:import-historical-csv')->assertSuccessful();

        $this->assertSame(3, Cage::whereIn('cage_code', ['CAGE-A', 'CAGE-B', 'CAGE-C'])->count());
        $this->assertSame(74990, (int) ProductionLog::where('source_key', 'like', 'production_data_clean:%:production')->sum('egg_count'));
        $this->assertSame(9626.94, (float) FeedConsumptionLog::where('source_key', 'like', 'production_data_clean:%:feed')->sum('feed_consumed_kg'));
        $this->assertSame(474, ProductionLog::where('source_key', 'like', 'production_data_clean:%:production')->count());
        $this->assertSame(474, FeedConsumptionLog::where('source_key', 'like', 'production_data_clean:%:feed')->count());
        $this->assertSame(474, EnvironmentalLog::where('source_key', 'like', 'production_data_clean:%:environment')->count());
        $this->assertSame(17.0, (float) FeedConsumptionLog::where('source_key', 'production_data_clean:2026-08-27:CAGE-A:feed')->value('crude_protein_pct'));
        $this->assertSame('Dekalb White', Cage::where('cage_code', 'CAGE-A')->value('breed_snapshot'));
        $this->assertSame(true, (bool) EnvironmentalLog::where('source_key', 'production_data_clean:2026-08-27:CAGE-A:environment')->value('is_override'));
        $this->assertSame(false, (bool) EnvironmentalLog::where('source_key', 'production_data_clean:2026-08-27:CAGE-A:environment')->value('humidity_corrected'));
        $this->assertSame(57, (int) MortalityLog::where('source_key', 'like', 'production_data_clean:%:mortality')->sum('count'));
        $this->assertSame(15, MortalityLog::where('source_key', 'like', 'production_data_clean:%:mortality')->where('cage_id', Cage::where('cage_code', 'CAGE-A')->value('id'))->count());
        $this->assertSame('2026-03-23', ProductionLog::where('source_key', 'like', 'production_data_clean:%:production')->min('log_date'));
        $this->assertSame('2026-08-27', ProductionLog::where('source_key', 'like', 'production_data_clean:%:production')->max('log_date'));

        $aug27 = ProductionLog::where('source_key', 'production_data_clean:2026-08-27:CAGE-A:production')->firstOrFail();
        $this->assertSame(240, (int) $aug27->hen_count);
        $this->assertSame(90.42, (float) $aug27->hdep);
        $this->assertSame(0, DB::table('hens')->count());
        $this->assertSame(0, DB::table('mortality_log_hens')->count());
        $this->assertSame(0, DB::table('egg_size_logs')->whereIn('production_log_id', ProductionLog::where('source_key', 'like', 'production_data_clean:%:production')->select('id'))->count());

        $dashboardController = app(\App\Http\Controllers\DashboardController::class);
        $farmProduction = new \ReflectionMethod($dashboardController, 'farmProductionForDay');
        $farmProduction->setAccessible(true);
        $this->assertSame([
            'eggs' => 472,
            'hens' => 528,
            'hdep' => 89.4,
        ], $farmProduction->invoke($dashboardController, '2026-08-27'));

        $cageA = Cage::where('cage_code', 'CAGE-A')->firstOrFail();
        $allFcr = FcrCalculator::forAllCages(Carbon::parse('2026-08-27'), Carbon::parse('2026-08-27'));
        $this->assertSame(2.15, $allFcr);

        $fcr = FcrCalculator::forCage($cageA, Carbon::parse('2026-08-27'), Carbon::parse('2026-08-27'));
        $this->assertSame(2.13, $fcr);

        $report = $this->actingAs($admin)
            ->get(route('reports.data', ['type' => 'production', 'from' => '2026-08-27', 'to' => '2026-08-27', 'charts' => 1]));
        $report->assertOk();
        $payload = $report->json();
        $this->assertStringContainsString('Total Eggs:</dt>', $payload['html']);
        $this->assertStringContainsString('472 eggs', $payload['html']);
        $this->assertStringContainsString('528 hens', $payload['html']);
        $this->assertStringContainsString('not individual hen inventory', $payload['html']);
        $this->assertSame([472], $payload['charts']['production']['eggs']);
        $this->assertSame([89.4], $payload['charts']['production']['hdep']);
        $this->assertStringContainsString('Historical cage/day aggregate', $payload['html']);

        $feedReport = $this->actingAs($admin)->get(route('reports.data', [
            'type' => 'feed', 'from' => '2026-08-27', 'to' => '2026-08-27', 'charts' => 1,
        ]));
        $feedReport->assertOk()->assertSee('Unbatched historical import');
        $this->assertSame([60.9], $feedReport->json('charts.feed.kg'));

        $environmentReport = $this->actingAs($admin)->get(route('reports.data', [
            'type' => 'environment', 'from' => '2026-08-27', 'to' => '2026-08-27',
        ]));
        $environmentReport->assertOk()->assertSee('not sensor telemetry');

        $this->artisan('farm:import-historical-csv')->assertSuccessful();
        $this->assertSame(474, ProductionLog::where('source_key', 'like', 'production_data_clean:%:production')->count());
        $this->assertSame(474, FeedConsumptionLog::where('source_key', 'like', 'production_data_clean:%:feed')->count());
        $this->assertSame(474, EnvironmentalLog::where('source_key', 'like', 'production_data_clean:%:environment')->count());
        $this->assertSame(45, MortalityLog::where('source_key', 'like', 'production_data_clean:%:mortality')->count());

        $dashboard = $this->actingAs($admin)
            ->get(route('dashboard.stats', ['from_date' => '2026-08-27', 'to_date' => '2026-08-27']));
        $dashboard->assertOk();
        $this->assertSame(528, (int) $dashboard->viewData('totalHens'));
        $this->assertSame(89.4, (float) $dashboard->viewData('todayHdep'));
        $this->assertSame(472, (int) $dashboard->viewData('eggsToday'));
        $this->assertSame(0, $dashboard->viewData('liveReadings')->count());

        $cageDashboard = $this->actingAs($admin)->get(route('dashboard.stats', ['cage' => 'CAGE-A', 'from_date' => '2026-08-27', 'to_date' => '2026-08-27']));
        $cageDashboard->assertOk();
        $this->assertSame(240, (int) $cageDashboard->viewData('totalHens'));
        $this->assertSame(217, (int) $cageDashboard->viewData('eggsToday'));
        $this->assertSame(90.4, (float) $cageDashboard->viewData('todayHdep'));

        $environment = $this->actingAs($admin)->get(route('environment.live-data'));
        $environment->assertOk()->assertDontSee('33.3°C')->assertDontSee('86%');

        $feed = $this->actingAs($admin)
            ->get(route('feed.live-data', ['tab' => 'fcr', 'fcr_cage_id' => $cageA->id]));
        $feed->assertOk()->assertSee('2.13');
    }
}
