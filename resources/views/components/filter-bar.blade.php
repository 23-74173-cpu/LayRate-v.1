{{--
/**
 * <x-filter-bar>
 *
 * One consistent filter form for every record table. Desktop/tablet render
 * the fields inline (one row, wraps); on phones the same fields move into a
 * bottom sheet behind a "Filters (N)" button — a single set of controls,
 * reparented by filter-ui.js, so state can never diverge.
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
 *   @param string $title    Accessible name, used for role="search" label
 *                           and the sheet heading (e.g. "Filter stock batches")
 * }
--}}
@props(['formId', 'frameId', 'action', 'title' => 'Filter records'])

<form id="{{ $formId }}" data-filter-bar data-frame="{{ $frameId }}" data-action="{{ $action }}"
      role="search" aria-label="{{ $title }}" onsubmit="return false" novalidate>
    {{-- Inline fields (desktop/tablet). On phones filter-ui.js moves this
         wrapper into the sheet body and back; one set of controls only. --}}
    <div data-filter-fields class="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap sm:items-end sm:gap-x-4 sm:gap-y-3">
        {{ $slot }}
        <div class="hidden sm:flex sm:items-end">
            <button type="button" data-filter-reset
                    class="min-h-[44px] inline-flex items-center px-4 text-xs font-medium rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] transition-colors">
                Reset
            </button>
        </div>
    </div>

    {{-- Phone-only entry point: search stays inline above; the rest hides here. --}}
    <div class="mt-3 sm:hidden">
        <button type="button" data-filter-open aria-haspopup="dialog"
                class="w-full inline-flex items-center justify-center gap-2 rounded-lg border border-[#D9D9D9] bg-white px-4 min-h-[44px] text-sm font-medium text-[#333333] hover:bg-[#F5F6F8] transition-colors">
            <i data-lucide="sliders-horizontal" class="w-4 h-4 text-[#6B7280]"></i>
            Filters
            <span data-filter-count
                  class="min-w-5 h-5 px-1.5 rounded-full text-xs font-bold text-white items-center justify-center"
                  style="background-color: var(--color-navy); display: none;">0</span>
        </button>
    </div>

    {{-- Phone bottom sheet. Hidden on sm+ even if opened (CSS guard); the
         fields above are moved into [data-filter-sheet-body] while open. --}}
    <div data-filter-sheet hidden class="fixed inset-0 z-50 sm:hidden" role="dialog" aria-modal="true" aria-label="{{ $title }}">
        <div data-filter-sheet-backdrop class="absolute inset-0" style="background-color: rgba(0,0,0,0.4);"></div>
        <div class="absolute inset-x-0 bottom-0 max-h-[85dvh] overflow-y-auto rounded-t-2xl bg-white"
             style="padding: 1.25rem 1.25rem calc(env(safe-area-inset-bottom) + 1.25rem);">
            <div class="flex items-center justify-between mb-1">
                <h2 class="text-base font-semibold" style="color: #1f1f1f;">{{ $title }}</h2>
                <button type="button" data-filter-sheet-close aria-label="Close filters"
                        class="p-2.5 -m-1 rounded-full hover:bg-black/5 transition-colors">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>
            <p class="text-xs mb-4" style="color: #6B7280;">Applies when you tap Apply.</p>
            <div data-filter-sheet-body></div>
            <p data-filter-error class="hidden text-xs mt-3" style="color: #9b1c24;" role="alert"></p>
            <div class="flex gap-3 mt-4">
                <button type="button" data-filter-clear
                        class="flex-1 min-h-[44px] text-sm font-medium rounded-lg border border-[#e6e6e6] text-[#1f1f1f] hover:bg-[#f6f5f4] transition-colors">Clear</button>
                <button type="button" data-filter-apply
                        class="flex-1 min-h-[44px] inline-flex items-center justify-center gap-2 rounded-lg text-sm font-medium text-white hover:brightness-95 transition-colors"
                        style="background-color: var(--color-navy);">Apply</button>
            </div>
        </div>
    </div>
</form>
