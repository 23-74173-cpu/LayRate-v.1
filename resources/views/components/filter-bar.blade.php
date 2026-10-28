{{--
/**
 * <x-filter-bar>
 *
 * Shared responsive read-only filter form. Fields stay in one DOM location;
 * mobile drawer presents that same field group with staged values.
 *
 * Behavior (see public/js/filter-ui.js):
 * - auto-apply on change for selects/dates/presets on sm+ screens, 300 ms
 *   debounced text; inside the phone sheet nothing applies until Apply.
 * - results render in the given Turbo frame (page never jumps); page resets
 *   to 1 on every change; the overflow-redirect middleware still applies.
 * - Reset clears the form and reloads unfiltered; selection in the table is
 *   naturally cleared because the frame content is replaced.
 *
 * Props:
 *   @param string $formId   Unique form id (e.g. "stocksFilterForm")
 *   @param string $frameId  Turbo frame id that renders the table
 *   @param string $action   Base URL the frame reloads from (no query string)
 *   @param string $title    Accessible name for this filter region
 * }
--}}
@props(['formId', 'frameId', 'action', 'title' => 'Filter records', 'summary' => null])

<form id="{{ $formId }}" data-filter-bar data-frame="{{ $frameId }}" data-action="{{ $action }}" @if($summary) data-active-summary="{{ $summary }}" @endif
      role="search" aria-label="{{ $title }}" onsubmit="return false" novalidate>
    <div class="filter-bar-shell">
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            <button type="button" data-filter-toggle aria-expanded="false" aria-controls="{{ $formId }}-fields"
                    class="filter-control filter-icon-toggle inline-flex shrink-0 items-center justify-center rounded-lg border border-[#D9D9D9] bg-white hover:bg-[#F5F6F8] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy"
                    aria-label="Show filters" title="Show filters">
                <i data-lucide="sliders-horizontal" class="h-4 w-4 shrink-0" aria-hidden="true"></i>
                <span data-filter-count class="hidden min-w-5 rounded-full px-1 text-center text-[10px] font-bold leading-4 text-white" style="background-color:var(--color-navy)">0</span>
            </button>
            <span data-filter-summary-display class="filter-bar-summary" aria-live="polite">{{ $summary }}</span>
        </div>
    </div>

    <div id="{{ $formId }}-fields" data-filter-panel class="filter-panel mt-2" aria-label="{{ $title }} fields" hidden>
        <div data-filter-fields class="filter-fields-grid min-w-0">
            {{ $slot }}
        </div>
        <div data-filter-desktop-actions class="mt-3 flex flex-wrap items-center justify-end gap-2">
            <button type="button" data-filter-reset class="filter-action filter-control inline-flex items-center justify-center rounded-lg border border-[#D9D9D9] bg-white text-sm font-medium text-[#333333] hover:bg-[#F5F6F8] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy">Reset</button>
        </div>
        <span data-filter-desktop-home class="hidden"></span>
    </div>

    <div data-filter-sheet hidden class="fixed inset-0 z-50 sm:hidden" role="dialog" aria-modal="true" aria-labelledby="{{ $formId }}-dialog-title">
        <div data-filter-sheet-backdrop class="absolute inset-0" style="background-color: rgba(0,0,0,0.4);"></div>
        <div data-filter-sheet-content class="absolute inset-x-0 bottom-0 max-h-[85dvh] overflow-y-auto rounded-t-2xl bg-white"
             style="padding: 1.25rem 1.25rem calc(env(safe-area-inset-bottom) + 1.25rem);">
            <div class="flex items-center justify-between mb-1">
                <h2 id="{{ $formId }}-dialog-title" class="text-base font-semibold" style="color: #1f1f1f;">{{ $title }}</h2>
                <button type="button" data-filter-sheet-close aria-label="Close filters"
                        class="filter-control inline-flex w-11 shrink-0 items-center justify-center rounded-full hover:bg-black/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy">
                    <i data-lucide="x" class="h-5 w-5" style="color: #615d59;" aria-hidden="true"></i>
                </button>
            </div>
            <p class="text-xs mb-4" style="color: #6B7280;">Changes are pending until you apply them. Cancel discards changes.</p>
            <div data-filter-sheet-fields class="mt-4"></div>
            <div data-filter-actions class="filter-actions-row mt-4 flex flex-wrap justify-end gap-2">
                <button type="button" data-filter-clear class="filter-action filter-control inline-flex items-center justify-center rounded-lg border border-[#D9D9D9] text-sm font-medium">Clear</button>
                <button type="button" data-filter-cancel class="filter-action filter-control inline-flex items-center justify-center rounded-lg border border-[#D9D9D9] text-sm font-medium">Cancel</button>
                <button type="button" data-filter-apply class="filter-action filter-control inline-flex items-center justify-center gap-2 rounded-lg text-sm font-medium text-white hover:brightness-95 transition-colors" style="background-color: var(--color-navy);">Apply</button>
            </div>
            <p data-filter-error class="hidden text-xs mt-3" style="color: #9b1c24;" role="alert"></p>
        </div>
    </div>
</form>
