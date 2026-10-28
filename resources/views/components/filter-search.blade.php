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

<div class="filter-field min-w-0 sm:min-w-[12rem]">
    <label for="filter-{{ $name }}-search" class="filter-label">{{ $label }}</label>
    <input type="search" name="{{ $name }}" id="filter-{{ $name }}-search" value="{{ $value }}" placeholder="{{ $placeholder }}" autocomplete="off"
           data-filter-debounce
           class="filter-control w-full min-w-0 border border-[#D9D9D9] rounded-lg px-3 text-sm bg-white focus:border-navy">
</div>
