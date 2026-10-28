<?php

namespace Tests\Feature;

use Tests\TestCase;

class RecentLogsFilterPlacementTest extends TestCase
{
    public function test_recent_log_filters_are_outside_the_production_calendar_or_logs_card(): void
    {
        $view = file_get_contents(resource_path('views/eggs/recent-logs.blade.php'));
        $cardStart = strpos($view, '<x-card>');
        $filterStart = strpos($view, '<form id="recentLogsForm"');
        $cardEnd = strpos($view, '</x-card>', $cardStart);

        $this->assertNotFalse($cardStart);
        $this->assertNotFalse($filterStart);
        $this->assertNotFalse($cardEnd);
        $this->assertLessThan($cardStart, $filterStart, 'Filter form should be rendered before the production card.');
    }
}
