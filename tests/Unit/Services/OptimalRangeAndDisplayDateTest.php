<?php

namespace Tests\Unit\Services;

use App\Services\EnvironmentStatusService;
use App\Services\ReportingDateService;
use Tests\TestCase;

class OptimalRangeAndDisplayDateTest extends TestCase
{
    public function test_compare_to_range_states(): void
    {
        $this->assertSame(['state' => 'within', 'diff' => 0.0], EnvironmentStatusService::compareToRange(21.0, 18, 24));
        $this->assertSame(['state' => 'within', 'diff' => 0.0], EnvironmentStatusService::compareToRange(24.0, 18, 24));
        $this->assertSame(['state' => 'within', 'diff' => 0.0], EnvironmentStatusService::compareToRange(18.0, 18, 24));
        $this->assertSame(['state' => 'above', 'diff' => 4.4], EnvironmentStatusService::compareToRange(28.4, 18, 24));
        $this->assertSame(['state' => 'below', 'diff' => 2.5], EnvironmentStatusService::compareToRange(15.5, 18, 24));
        $this->assertSame(['state' => 'none', 'diff' => 0.0], EnvironmentStatusService::compareToRange(null, 18, 24));
    }

    public function test_value_that_rounds_to_the_limit_counts_as_within(): void
    {
        // Shown as 24.0 °C on screen, so it must not read "above by 0.0".
        $this->assertSame('within', EnvironmentStatusService::compareToRange(24.04, 18, 24)['state']);
    }

    public function test_optimal_range_comes_from_config(): void
    {
        config(['environment.optimal' => ['temp_min' => 19, 'temp_max' => 25, 'hum_min' => 45, 'hum_max' => 65]]);

        $this->assertSame(
            ['temp_min' => 19.0, 'temp_max' => 25.0, 'hum_min' => 45.0, 'hum_max' => 65.0],
            EnvironmentStatusService::optimalRange()
        );
    }

    public function test_optimal_range_reads_the_config_file_when_the_key_is_missing(): void
    {
        // e.g. a config cache built before config/environment.php existed.
        config(['environment.optimal' => null]);
        $fromFile = (require config_path('environment.php'))['optimal'];

        $this->assertSame(array_map('floatval', $fromFile), EnvironmentStatusService::optimalRange());
    }

    public function test_display_date_formats_iso_dates_only(): void
    {
        $this->assertSame('09/24/2026', ReportingDateService::displayDate('2026-09-24'));
        $this->assertSame('01/05/2026', ReportingDateService::displayDate('2026-01-05'));
        // Not a real date / not Y-m-d: returned unchanged instead of throwing.
        $this->assertSame('2026-02-30', ReportingDateService::displayDate('2026-02-30'));
        $this->assertSame('abc', ReportingDateService::displayDate('abc'));
        $this->assertSame('', ReportingDateService::displayDate(null));
    }
}
