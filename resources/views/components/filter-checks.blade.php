{{--
/**
 * <x-filter-checks>
 *
 * Checkbox group for x-filter-bar (multi-value filters, e.g. Logged Via).
 * Auto-applies on change like the other controls.
 *
 * Props:
 *   @param string $name    Field name, submitted as name[] (e.g. "logged_via")
 *   @param string $label   Visible label
 *   @param array  $options [value => display]
 *   @param array  $values  Currently checked values
 * }
--}}
@props(['name', 'label', 'options' => [], 'values' => []])

@php $values = array_map('strval', (array) $values); @endphp
<div class="col-span-2 sm:col-span-1 min-w-0">
    <span class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">{{ $label }}</span>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 min-h-[44px]" role="group" aria-label="{{ $label }}">
        @foreach($options as $optValue => $optLabel)
        <label class="inline-flex items-center gap-2 text-sm cursor-pointer min-h-[44px]" style="color: #31302e;">
            <input type="checkbox" name="{{ $name }}[]" value="{{ $optValue }}"
                   {{ in_array((string) $optValue, $values, true) ? 'checked' : '' }}
                   class="rounded border-gray-300 text-navy focus:ring-navy">
            {{ $optLabel }}
        </label>
        @endforeach
    </div>
</div>
