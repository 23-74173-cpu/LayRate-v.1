{{--
/**
 * <x-filter-select>
 *
 * Standard labeled select for x-filter-bar. 44px minimum touch target.
 *
 * Props:
 *   @param string $name      Field name (standard param: cage_id, size, status, breed, read, sort)
 *   @param string $label     Visible label
 *   @param array  $options   [value => display]
 *   @param mixed  $value     Current value (selected)
 *   @param string $allLabel  Label for the empty option (omit to skip it)
 * }
--}}
@props(['name', 'label', 'options' => [], 'value' => null, 'allLabel' => null, 'id' => null])

@php $selectId = $id ?? $name . '-' . substr(md5($name . $label), 0, 6); @endphp
<div class="min-w-0">
    <label for="{{ $selectId }}" class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">{{ $label }}</label>
    <select name="{{ $name }}" id="{{ $selectId }}"
            class="w-full min-w-0 sm:w-auto min-h-[44px] border border-[#D9D9D9] rounded-lg px-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-navy/30 focus:border-navy">
        @if($allLabel !== null)
        <option value="">{{ $allLabel }}</option>
        @endif
        @foreach($options as $optValue => $optLabel)
        <option value="{{ $optValue }}" {{ (string) $value === (string) $optValue ? 'selected' : '' }}>{{ $optLabel }}</option>
        @endforeach
    </select>
</div>
