<?php

namespace Tests\Feature;

use App\Models\Forecast;
use App\Models\User;
use App\Services\ForecastGenerationService;
use App\Services\ReportingDateService;
use Tests\TestCase;

class ForecastResetTest extends TestCase
{
    public function test_reset_archives_selected_future_predictions_and_allows_replacement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $today = ReportingDateService::reportingDateString();
        $target = ReportingDateService::reportingDate()->addDay()->toDateString();
        $legacy = Forecast::create(['forecast_date' => $today, 'target_date' => $target, 'predicted_egg_count' => 800, 'superseded_at' => now(), 'forecast_key' => Forecast::fixedKey(null, null, $target)]);
        $old = Forecast::create(['forecast_date' => $today, 'target_date' => $target, 'predicted_egg_count' => 999, 'forecast_key' => null]);
        $past = Forecast::create(['forecast_date' => ReportingDateService::reportingDate()->subDays(2), 'target_date' => $today, 'predicted_egg_count' => 5]);
        $service = \Mockery::mock(ForecastGenerationService::class)->makePartial();
        $service->shouldReceive('dataSufficiency')->andReturn(['has_enough' => true]);
        $this->app->instance(ForecastGenerationService::class, $service);
        $this->actingAs($admin)->postJson(route('forecast.clear'), ['scope' => 'farm', 'horizon' => 1])
            ->assertOk()->assertJsonPath('archived', 2);
        $this->assertNotNull($old->fresh()->superseded_at);
        $this->assertNotNull($old->fresh()->invalidated_at);
        $this->assertNull($old->fresh()->forecast_key);
        $this->assertNull($legacy->fresh()->forecast_key);
        $this->assertNull($past->fresh()->superseded_at);
        $this->assertCount(0, $service->savedForecasts(null, null, $target, 1));
        $new = $service->persistForecasts(['forecast' => [['date' => $target, 'predicted_egg_count' => 10]]], null, null)->first();
        $this->assertNotEquals($old->id, $new->id);
        $this->assertEquals(10, $new->predicted_egg_count);
        $this->assertDatabaseHas('forecasts', ['id' => $old->id, 'predicted_egg_count' => 999]);
    }

    public function test_reset_is_admin_only_and_keeps_forecasts_when_data_is_insufficient(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $this->actingAs($operator)->postJson(route('forecast.clear'), ['scope' => 'farm', 'horizon' => 7])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $service = \Mockery::mock(ForecastGenerationService::class)->makePartial();
        $service->shouldReceive('dataSufficiency')->andReturn(['has_enough' => false]);
        $this->app->instance(ForecastGenerationService::class, $service);
        $this->actingAs($admin)->postJson(route('forecast.clear'), ['scope' => 'farm', 'horizon' => 7])->assertUnprocessable();
    }
}
