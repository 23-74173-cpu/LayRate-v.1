<?php

namespace Tests\Feature;

use Tests\TestCase;

class UnifiedFilterBarMarkupTest extends TestCase
{
    public function test_filter_shell_exposes_accessible_icon_disclosure_and_compact_actions(): void
    {
        $html = view('components.filter-bar', [
            'formId' => 'test-filter-form',
            'frameId' => 'test-filter-results',
            'action' => '/test/results',
            'title' => 'Filter test results',
            'slot' => new \Illuminate\Support\HtmlString(''),
            'attributes' => new \Illuminate\View\ComponentAttributeBag(),
        ])->render();

        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-controls="test-filter-form-fields"', $html);
        $this->assertStringContainsString('id="test-filter-form-fields"', $html);
        $this->assertStringContainsString('data-filter-toggle', $html);
        $this->assertStringContainsString('aria-label="Show filters"', $html);
        $this->assertStringContainsString('title="Show filters"', $html);
        $this->assertStringNotContainsString('data-filter-toggle-label', $html);
        $this->assertStringContainsString('data-filter-cancel', $html);
        $this->assertStringContainsString('class="filter-control', $html);
        $this->assertStringContainsString('filter-icon-toggle', $html);
        $this->assertStringContainsString('filter-action', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
    }

    public function test_feed_parent_does_not_render_a_duplicate_filter_form(): void
    {
        $view = file_get_contents(resource_path('views/feed.blade.php'));

        $this->assertSame(1, substr_count($view, 'id="feed-live-data"'));
        $this->assertStringNotContainsString('id="feed-filter-reset"', $view);
        $this->assertStringNotContainsString('id="feed-preset"', $view);
        $this->assertStringNotContainsString("document.getElementById('feed-global-filters')", $view);
    }

    public function test_feed_reset_action_is_a_separate_full_width_grid_item(): void
    {
        $view = file_get_contents(resource_path('views/feed/_live-data.blade.php'));
        $fieldsStart = strpos($view, 'id="feed-filter-fields"');
        $fieldsEnd = strpos($view, '</div>', $fieldsStart);
        $actionsStart = strpos($view, 'data-filter-action="feed-reset"');

        $this->assertNotFalse($fieldsStart);
        $this->assertNotFalse($fieldsEnd);
        $this->assertNotFalse($actionsStart);
        $this->assertGreaterThan($fieldsStart, $actionsStart, 'Reset belongs after the fields as its own full-width grid item.');
        $this->assertStringContainsString('data-filter-action="feed-reset"', $view);
        $this->assertStringContainsString('filter-legacy-actions mt-3 flex justify-end', $view);
    }

    public function test_feed_batch_status_legend_is_inside_the_table_card_with_a_divider(): void
    {
        $view = file_get_contents(resource_path('views/feed/_live-data.blade.php'));
        $panelStart = strpos($view, 'id="tab-batches"');
        $panelEnd = strpos($view, '{{-- Daily Consumption Panel --}}', $panelStart);
        $panel = substr($view, $panelStart, $panelEnd - $panelStart);

        $legendStart = strpos($panel, 'Optimal (16–18%)');
        $cardStart = strpos($panel, 'data-feed-batches-card');
        $tableStart = strpos($panel, '<table class="w-full">');

        $this->assertNotFalse($legendStart);
        $this->assertNotFalse($cardStart);
        $this->assertNotFalse($tableStart);
        $this->assertGreaterThan($cardStart, $legendStart, 'The status legend should be in the same card as the table.');
        $this->assertGreaterThan($legendStart, $tableStart, 'A separated legend should appear above the table.');
        $this->assertStringContainsString('border-b border-[#D9D9D9]', substr($panel, $cardStart, $tableStart - $cardStart));
    }

    public function test_legacy_filter_action_restore_does_not_use_a_stale_sibling_reference(): void
    {
        $script = file_get_contents(public_path('js/filter-ui.js'));

        $this->assertStringContainsString('item.__filterHomeParent = fieldsContainer', $script);
        $this->assertStringContainsString('action.__filterHomeParent = action.parentNode', $script);
        $this->assertStringContainsString('homeParent.insertBefore(action, homeNext && homeNext.parentNode === homeParent ? homeNext : null)', $script);
        $this->assertStringContainsString('fieldsParent.insertBefore(fieldsContainer, homeMarker || null)', $script);
    }

    public function test_feed_disclosure_is_rebound_after_live_frame_content_is_replaced(): void
    {
        $view = file_get_contents(resource_path('views/feed/_live-data.blade.php'));
        $script = file_get_contents(public_path('js/filter-ui.js'));

        $this->assertStringContainsString("event.target.id === 'feed-live-data'", $view);
        $this->assertStringContainsString("new CustomEvent('filter:refresh')", $view);
        $this->assertStringContainsString("document.addEventListener('filter:refresh', function () { bindDisclosureButtons()", $script);
    }

    public function test_feed_filter_controls_and_reset_fit_one_desktop_row(): void
    {
        $view = file_get_contents(resource_path('views/feed/_live-data.blade.php'));
        $fieldsStart = strpos($view, 'id="feed-filter-fields"');
        $fieldsEnd = strpos($view, '</div>', $fieldsStart);
        $fields = substr($view, $fieldsStart, $fieldsEnd - $fieldsStart);
        $resetStart = strpos($view, 'data-filter-action="feed-reset"', $fieldsStart);
        $resetEnd = strpos($view, '</div>', $resetStart);
        $reset = substr($view, $resetStart, $resetEnd - $resetStart);

        $this->assertStringContainsString('sm:grid-cols-[repeat(4,minmax(0,1fr))]', $fields);
        $this->assertStringContainsString('min-w-0 rounded-lg', $fields);
        $this->assertStringContainsString('px-1.5 text-xs', $fields);
        $this->assertStringContainsString('sm:col-span-4', $reset);
        $this->assertStringContainsString('flex justify-end sm:col-span-4 sm:mt-0', $reset);
    }

    public function test_legacy_filter_clear_actions_are_dedicated_full_width_grid_items(): void
    {
        foreach ([
            'chickens/index.blade.php' => 'data-filter-action="hen-clear"',
            'eggs/recent-logs.blade.php' => 'data-filter-action="recent-clear"',
            'eggs/pre-orders.blade.php' => 'data-filter-action="preorder-clear"',
        ] as $path => $actionSelector) {
            $view = file_get_contents(resource_path('views/' . $path));
            $fieldsStart = strpos($view, 'data-hen-filter-fields');
            if ($fieldsStart === false) $fieldsStart = strpos($view, 'data-recent-filter-fields');
            if ($fieldsStart === false) $fieldsStart = strpos($view, 'data-preorder-filter-fields');
            $fieldsEnd = strpos($view, 'data-filter-action="' . match ($path) {
                'chickens/index.blade.php' => 'hen-clear',
                'eggs/recent-logs.blade.php' => 'recent-clear',
                default => 'preorder-clear',
            } . '"', $fieldsStart);
            $actionsStart = strpos($view, $actionSelector);

            $this->assertNotFalse($fieldsStart, $path . ' fields container exists');
            $this->assertNotFalse($fieldsEnd, $path . ' fields container closes');
            $this->assertNotFalse($actionsStart, $path . ' has a dedicated action row');
            $this->assertGreaterThan($fieldsStart, $actionsStart, $path . ' action item follows the filter fields');
        }
    }

    public function test_report_export_and_graph_controls_are_not_moved_as_filter_actions(): void
    {
        $script = file_get_contents(public_path('js/filter-ui.js'));

        $this->assertStringContainsString(':not([data-filter-action="export"]):not([data-filter-action="charts"]):not([data-filter-action="forecast"])', $script);
        $this->assertStringContainsString('data-report-filter-actions', file_get_contents(resource_path('views/reports.blade.php')));
    }
}
