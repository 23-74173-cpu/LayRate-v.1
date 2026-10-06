{{--
/**
 * <x-filter-search>
 *
 * Text search box for x-filter-bar. Desktop/tablet: 300 ms debounced
 * auto-apply (filter-ui.js). Renders first in the bar on desktop.
 *
 * Props:
 *   @param string $name        Default "q" (alias "search" handled server-side)
 *   @param string $label       Visible label (default "Search")
 *   @param string $value       Current query
 *   @param string $placeholder Placeholder text
 * }
--}}
@props(['name' => 'q', 'label' => 'Search', 'value' => null, 'placeholder' => 'Search…'])

<div class="col-span-2 sm:col-span-1 min-w-0 sm:min-w-[12rem]">
    <label for="filter-{{ $name }}-search" class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">{{ $label }}</label>
    <input type="search" name="{{ $name }}" id="filter-{{ $name }}-search" value="{{ $value }}" placeholder="{{ $placeholder }}" autocomplete="off"
           data-filter-debounce
           class="w-full min-w-0 min-h-[44px] border border-[#D9D9D9] rounded-lg px-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-navy/30 focus:border-navy">
</div>
