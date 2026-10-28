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
<div class="filter-field min-w-0">
    <label for="{{ $selectId }}" class="filter-label">{{ $label }}</label>
    <select name="{{ $name }}" id="{{ $selectId }}"
            class="filter-control w-full min-w-0 border border-[#D9D9D9] rounded-lg px-3 text-sm bg-white focus:border-navy">
        @if($allLabel !== null)
        <option value="">{{ $allLabel }}</option>
        @endif
        @foreach($options as $optValue => $optLabel)
        <option value="{{ $optValue }}" {{ (string) $value === (string) $optValue ? 'selected' : '' }}>{{ $optLabel }}</option>
        @endforeach
    </select>
</div>
