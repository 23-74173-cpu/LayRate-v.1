<?php

namespace Tests\Unit\Services;

use App\Models\Setting;
use App\Services\ReportingDateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReportingDateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Avoid leaking cached values between tests.
        Cache::flush();
    }

    public function test_timezone_is_hardcoded_to_asia_manila(): void
    {
        $this->assertEquals('Asia/Manila', ReportingDateService::timezone());
    }

    public function test_default_reset_time_is_six_am(): void
    {
        $this->assertEquals('06:00', ReportingDateService::resetTime());
    }

    public function test_reset_time_can_be_changed_via_setting(): void
    {
        Setting::set('day_reset_time', '05:30');

        $this->assertEquals('05:30', ReportingDateService::resetTime());
    }

    public function test_invalid_reset_time_falls_back_to_six_am(): void
    {
        Setting::set('day_reset_time', 'not-a-time');

        $this->assertEquals('06:00', ReportingDateService::resetTime());
    }

    public function test_reporting_date_is_previous_day_before_reset(): void
    {
        Setting::set('day_reset_time', '06:00');

        // 05:30 Manila is before the 06:00 reset, so the reporting date is yesterday.
        Carbon::setTestNow(Carbon::parse('2026-08-19 05:30:00', 'Asia/Manila'));

        $this->assertEquals('2026-08-18', ReportingDateService::reportingDateString());
    }

    public function test_reporting_date_is_current_day_after_reset(): void
    {
        Setting::set('day_reset_time', '06:00');

        // 07:44 Manila is after the 06:00 reset, so the reporting date is today.
        Carbon::setTestNow(Carbon::parse('2026-08-19 07:44:00', 'Asia/Manila'));

        $this->assertEquals('2026-08-19', ReportingDateService::reportingDateString());
    }

    public function test_reporting_day_start_and_end_span_twenty_four_hours_from_reset(): void
    {
        Setting::set('day_reset_time', '06:00');

        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00:00', 'Asia/Manila'));

        $start = ReportingDateService::reportingDayStart();
        $end = ReportingDateService::reportingDayEnd();

        $this->assertEquals('2026-08-19 06:00:00', $start->toDateTimeString());
        $this->assertEquals('Asia/Manila', $start->timezone->getName());
        $this->assertEquals('2026-08-20 06:00:00', $end->toDateTimeString());
    }

    public function test_reporting_day_start_rolls_back_to_previous_day_before_reset(): void
    {
        Setting::set('day_reset_time', '06:00');

        Carbon::setTestNow(Carbon::parse('2026-08-19 05:59:00', 'Asia/Manila'));

        $start = ReportingDateService::reportingDayStart();

        $this->assertEquals('2026-08-18 06:00:00', $start->toDateTimeString());
    }
}
