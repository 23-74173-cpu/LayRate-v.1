<?php

namespace Tests\Unit\Services;

use App\Services\FcrStatusService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FcrStatusServiceTest extends TestCase
{
    #[Test]
    public function null_fcr_returns_na(): void
    {
        $this->assertSame('na', FcrStatusService::status(null));
        $this->assertSame('N/A', FcrStatusService::label(null));
    }

    #[Test]
    public function fcr_at_good_threshold_is_good(): void
    {
        $this->assertSame('good', FcrStatusService::status(2.5));
    }

    #[Test]
    public function fcr_below_good_threshold_is_good(): void
    {
        $this->assertSame('good', FcrStatusService::status(2.0));
        $this->assertSame('good', FcrStatusService::status(1.5));
        $this->assertSame('good', FcrStatusService::status(0.01));
    }

    #[Test]
    public function fcr_just_above_good_threshold_is_warning(): void
    {
        $this->assertSame('warning', FcrStatusService::status(2.51));
        $this->assertSame('warning', FcrStatusService::status(3.0));
    }

    #[Test]
    public function fcr_at_warning_threshold_is_warning(): void
    {
        $this->assertSame('warning', FcrStatusService::status(4.0));
    }

    #[Test]
    public function fcr_above_warning_threshold_is_critical(): void
    {
        $this->assertSame('critical', FcrStatusService::status(4.01));
        $this->assertSame('critical', FcrStatusService::status(5.0));
        $this->assertSame('critical', FcrStatusService::status(106.25));
    }

    #[Test]
    public function badge_classes_are_returned_for_each_status(): void
    {
        $good = FcrStatusService::badgeClasses(2.0);
        $this->assertSame('text-green-800', $good['text']);

        $warning = FcrStatusService::badgeClasses(3.0);
        $this->assertStringContainsString('yellow', $warning['bg']);

        $critical = FcrStatusService::badgeClasses(5.0);
        $this->assertStringContainsString('red', $critical['bg']);

        $na = FcrStatusService::badgeClasses(null);
        $this->assertStringContainsString('gray', $na['bg']);
    }

    #[Test]
    public function label_matches_expected_strings(): void
    {
        $this->assertSame('Good', FcrStatusService::label(2.0));
        $this->assertSame('Warning', FcrStatusService::label(3.0));
        $this->assertSame('Critical', FcrStatusService::label(5.0));
        $this->assertSame('N/A', FcrStatusService::label(null));
    }
}
