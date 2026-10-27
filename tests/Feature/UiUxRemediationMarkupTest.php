<?php

namespace Tests\Feature;

use Tests\TestCase;

class UiUxRemediationMarkupTest extends TestCase
{
    public function test_authenticated_viewport_allows_user_scaling(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('width=device-width, initial-scale=1.0, viewport-fit=cover', $layout);
        $this->assertStringNotContainsString('maximum-scale=1.0', $layout);
        $this->assertStringNotContainsString('user-scalable=no', $layout);
    }

    public function test_cage_delete_page_requires_a_final_review_before_delete_submission(): void
    {
        $view = file_get_contents(resource_path('views/cages/confirm-delete.blade.php'));

        $this->assertStringContainsString('id="deleteReview"', $view);
        $this->assertStringContainsString('data-review-submit', $view);
        $this->assertStringContainsString('id="confirmDeleteReview"', $view);
        $this->assertStringContainsString('data-review-back', $view);
    }

    public function test_final_review_summarizes_each_destructive_or_preserved_effect(): void
    {
        $view = file_get_contents(resource_path('views/cages/confirm-delete.blade.php'));

        foreach ([
            'Cage slots', 'Active hen records', 'Production logs', 'Environmental logs',
            'Feed consumption logs', 'Mortality records', 'Alerts', 'Active hen records deleted', 'Sensor disposition',
        ] as $effect) {
            $this->assertStringContainsString($effect, $view);
        }
        $this->assertStringContainsString('form.reportValidity()', $view);
        $this->assertStringContainsString("'preserve_production'", $view);
    }

    public function test_route_links_in_shared_underline_tabs_are_not_tabs(): void
    {
        $view = file_get_contents(resource_path('views/components/underline-tabs.blade.php'));

        $this->assertStringContainsString('<nav', $view);
        $this->assertStringContainsString("isset(\$tab['route'])", $view);
        $this->assertStringContainsString('@if($allClientTabs && $hasClientPanels) role="tablist" aria-orientation="horizontal" @endif', $view);
        $this->assertStringContainsString("\$tab['tabBehavior'] === 'authoritative'", $view);
        $this->assertStringContainsString('aria-current="page"', $view);
        $this->assertStringContainsString('button[role="tab"]', $view);
        $this->assertStringContainsString('aria-controls="{{ $tab[\'panelId\'] }}"', $view);
    }

    public function test_form_labels_and_live_feedback_are_present_on_remediated_forms(): void
    {
        $eggLog = file_get_contents(resource_path('views/egg-logging.blade.php'));
        $stocks = file_get_contents(resource_path('views/eggs/stocks.blade.php'));
        $feed = file_get_contents(resource_path('views/feed.blade.php'));
        $orders = file_get_contents(resource_path('views/eggs/pre-orders.blade.php'));
        $setup = file_get_contents(resource_path('views/dashboard.blade.php'));

        $this->assertStringContainsString('for="eggCount"', $eggLog);
        $this->assertStringContainsString('id="eggSizeSmall"', $eggLog);
        $this->assertStringContainsString('aria-live="polite"', $stocks);
        $this->assertStringContainsString('for="addFeedProtein"', $feed);
        $this->assertStringContainsString('for="addOrderCustomerName"', $orders);
        $this->assertStringContainsString('for="onboardingRows"', $setup);
        $this->assertStringContainsString('for="reportFrom"', file_get_contents(resource_path('views/reports.blade.php')));
        $this->assertStringContainsString('for="mortalityDate"', file_get_contents(resource_path('views/mortality.blade.php')));
    }

    public function test_shared_client_tabs_only_use_tab_semantics_when_all_panels_are_mapped(): void
    {
        $view = file_get_contents(resource_path('views/components/underline-tabs.blade.php'));

        $this->assertStringContainsString('$allClientTabs && $hasClientPanels', $view);
        $this->assertStringContainsString("\$tab['tabBehavior'] === 'authoritative'", $view);
        $this->assertStringContainsString('aria-controls="{{ $tab[\'panelId\'] }}"', $view);
        $this->assertStringContainsString("e.key === 'ArrowRight'", $view);
        $this->assertStringContainsString("e.key === 'Home'", $view);
    }

    public function test_reduced_motion_suppresses_pulse_and_spin_while_loading_markup_remains(): void
    {
        $skeleton = file_get_contents(resource_path('views/components/skeleton.blade.php'));
        $loading = file_get_contents(resource_path('views/components/loading-modal.blade.php'));
        $source = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('motion-reduce:animate-none', $skeleton);
        $this->assertStringContainsString('motion-reduce:animate-none', $loading);
        $this->assertStringContainsString('aria-busy="true"', $skeleton);
        $this->assertStringContainsString('Please wait...', $loading);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $source);
    }

    public function test_forecast_calendar_and_chart_have_disabled_and_tabular_alternatives(): void
    {
        $calendar = file_get_contents(resource_path('views/forecast/_calendar.blade.php'));
        $history = file_get_contents(resource_path('views/dashboard/_production-history.blade.php'));
        $results = file_get_contents(resource_path('views/forecast/_results.blade.php'));

        $this->assertStringContainsString('aria-label="Previous month unavailable: earliest month with production data"', $calendar);
        $this->assertStringContainsString('disabled>', $calendar);
        $this->assertStringContainsString('View chart data', $history);
        $this->assertStringContainsString('array_map(fn ($dataset)', $history);
        $this->assertStringContainsString('Historical and forecast egg counts', $results);
    }

    public function test_cage_layout_is_a_keyboard_focusable_named_scroll_region(): void
    {
        $view = file_get_contents(resource_path('views/cages/index.blade.php'));

        $this->assertStringContainsString('role="region" aria-label="Farm cage layout. Scroll horizontally and vertically to view the full grid" tabindex="0"', $view);
        $this->assertStringContainsString("var scale = 1;", $view);
        $this->assertStringContainsString("canvas.style.width = contentW > containerW ? containerW + 'px' : '';", $view);
        $this->assertStringContainsString('id="cageInfoPopup" data-modal data-close="closeCageInfoPopup"', $view);
    }

    public function test_profile_and_add_note_controls_have_associated_labels(): void
    {
        $profile = file_get_contents(resource_path('views/profile.blade.php'));
        $notes = file_get_contents(resource_path('views/notes/index.blade.php'));

        $this->assertStringContainsString('<label for="profileName"', $profile);
        $this->assertStringContainsString('id="profileName"', $profile);
        $this->assertStringContainsString('<label for="profileEmail"', $profile);
        $this->assertStringContainsString('id="profileEmail"', $profile);
        $this->assertStringContainsString('<label for="noteAddBody" class="sr-only">Note</label>', $notes);
    }

    public function test_hen_filters_and_environment_trend_range_are_labeled(): void
    {
        $hens = file_get_contents(resource_path('views/chickens/index.blade.php'));
        $environment = file_get_contents(resource_path('views/environment/_live-data.blade.php'));

        foreach (['tagSearchInput', 'henCageFilter', 'henBreedFilter', 'henSortFilter'] as $id) {
            $this->assertStringContainsString('for="' . $id . '"', $hens);
            $this->assertStringContainsString('id="' . $id . '"', $hens);
        }
        $this->assertStringContainsString('<label for="trendRange"', $environment);
        $this->assertStringContainsString('id="trendRange"', $environment);
    }

    public function test_enhanced_date_input_inherits_associated_label(): void
    {
        $dateInput = file_get_contents(public_path('js/date-mdy.js'));

        $this->assertStringContainsString('var associatedLabel = native.labels && native.labels[0];', $dateInput);
        $this->assertStringContainsString("display.setAttribute('aria-label', associatedLabel.textContent.replace(/\\s+/g, ' ').trim());", $dateInput);
    }
}
