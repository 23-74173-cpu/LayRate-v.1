<?php

namespace Tests\Unit\Services;

use App\Services\ReportingDateService;
use Carbon\Carbon;
use Tests\TestCase;

class ReportingDateServiceTest extends TestCase
{
    public function test_timezone_is_hardcoded_to_asia_manila(): void
    {
        $this->assertEquals('Asia/Manila', ReportingDateService::timezone());
    }

    public function test_reporting_date_is_current_calendar_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19 03:00:00', 'Asia/Manila'));

        $this->assertEquals('2026-08-19', ReportingDateService::reportingDateString());
    }

    public function test_reporting_date_uses_midnight_boundary(): void
    {
        // Any time on a given day returns that calendar date.
        Carbon::setTestNow(Carbon::parse('2026-08-19 14:30:00', 'Asia/Manila'));

        $this->assertEquals('2026-08-19', ReportingDateService::reportingDateString());

        Carbon::setTestNow(Carbon::parse('2026-08-19 00:00:00', 'Asia/Manila'));

        $this->assertEquals('2026-08-19', ReportingDateService::reportingDateString());
    }

    public function test_reporting_date_is_previous_day_before_midnight(): void
    {
        // At midnight exactly, the new day begins.
        Carbon::setTestNow(Carbon::parse('2026-08-19 00:00:00', 'Asia/Manila'));

        $this->assertEquals('2026-08-19', ReportingDateService::reportingDateString());
    }

    public function test_reporting_day_start_and_end_span_full_calendar_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00:00', 'Asia/Manila'));

        $start = ReportingDateService::reportingDayStart();
        $end = ReportingDateService::reportingDayEnd();

        $this->assertEquals('2026-08-19 00:00:00', $start->toDateTimeString());
        $this->assertEquals('Asia/Manila', $start->timezone->getName());
        $this->assertEquals('2026-08-19 23:59:59', $end->toDateTimeString());
    }

    public function test_reporting_date_for_uses_start_of_day(): void
    {
        $instant = Carbon::parse('2026-08-19 15:45:00', 'Asia/Manila');

        $result = ReportingDateService::reportingDateFor($instant);

        $this->assertEquals('2026-08-19', $result->toDateString());
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
    }
}
