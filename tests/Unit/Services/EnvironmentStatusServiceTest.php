<?php

namespace Tests\Unit\Services;

use App\Services\EnvironmentStatusService;
use PHPUnit\Framework\TestCase;

class EnvironmentStatusServiceTest extends TestCase
{
    private array $thresholds = [
        'temp_min' => 18.0,
        'temp_max' => 30.0,
        'hum_min' => 40.0,
        'hum_max' => 70.0,
    ];

    /** @test */
    public function temperature_below_min_is_alert()
    {
        $this->assertSame('Alert', EnvironmentStatusService::tempStatus(17.9, $this->thresholds));
    }

    /** @test */
    public function temperature_at_min_is_watch()
    {
        $this->assertSame('Watch', EnvironmentStatusService::tempStatus(18.0, $this->thresholds));
    }

    /** @test */
    public function temperature_inside_range_is_ok()
    {
        $this->assertSame('OK', EnvironmentStatusService::tempStatus(24.0, $this->thresholds));
    }

    /** @test */
    public function temperature_at_max_is_watch()
    {
        $this->assertSame('Watch', EnvironmentStatusService::tempStatus(30.0, $this->thresholds));
    }

    /** @test */
    public function temperature_above_max_is_alert()
    {
        $this->assertSame('Alert', EnvironmentStatusService::tempStatus(30.1, $this->thresholds));
    }

    /** @test */
    public function humidity_below_min_is_alert()
    {
        $this->assertSame('Alert', EnvironmentStatusService::humStatus(39.9, $this->thresholds));
    }

    /** @test */
    public function humidity_at_min_is_watch()
    {
        $this->assertSame('Watch', EnvironmentStatusService::humStatus(40.0, $this->thresholds));
    }

    /** @test */
    public function humidity_inside_range_is_ok()
    {
        $this->assertSame('OK', EnvironmentStatusService::humStatus(55.0, $this->thresholds));
    }

    /** @test */
    public function humidity_at_max_is_watch()
    {
        $this->assertSame('Watch', EnvironmentStatusService::humStatus(70.0, $this->thresholds));
    }

    /** @test */
    public function humidity_above_max_is_alert()
    {
        $this->assertSame('Alert', EnvironmentStatusService::humStatus(70.1, $this->thresholds));
    }

    /** @test */
    public function summary_returns_alert_when_either_metric_is_alert()
    {
        $this->assertSame('Alert', EnvironmentStatusService::summary(15.0, 55.0, $this->thresholds));
        $this->assertSame('Alert', EnvironmentStatusService::summary(24.0, 35.0, $this->thresholds));
    }

    /** @test */
    public function summary_returns_watch_when_no_alert_but_any_watch()
    {
        $this->assertSame('Watch', EnvironmentStatusService::summary(18.0, 55.0, $this->thresholds));
        $this->assertSame('Watch', EnvironmentStatusService::summary(24.0, 70.0, $this->thresholds));
    }

    /** @test */
    public function summary_returns_normal_when_both_metrics_are_ok()
    {
        $this->assertSame('Normal', EnvironmentStatusService::summary(24.0, 55.0, $this->thresholds));
    }
}
