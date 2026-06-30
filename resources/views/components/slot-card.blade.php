{{--
    <x-slot-card :slot="$slot" :cage="$cage" mode="compact" />
    Reusable slot card used in egg-logging and cages/index.
    Supports "compact" and "detailed" modes via the mode prop.

    Props:
      - slot (CageSlot, required)
      - cage (Cage, required)
      - mode (string, default 'compact') — compact | detailed
      - selectable (bool, default false) — renders as clickable button
      - selected (bool, default false) — highlight as selected
      - onclick (string, default '') — JS handler when selectable
      - class (string, default '') — additional classes on the wrapper

    Compact mode: small square showing occupancy dot + count.
    Detailed mode: full card with row/col, occupancy bar, sensor badge, hen count.
--}}
@props(['slot', 'cage', 'mode' => 'compact', 'selectable' => false, 'selected' => false, 'onclick' => '', 'class' => ''])

@php
    $occupancy = (int) $slot->current_occupancy;
    $max = (int) $cage->max_chickens_per_slot;
    $pct = $max > 0 ? ($occupancy / $max) * 100 : 0;
    $isFull = $occupancy >= $max;
    $isEmpty = $occupancy === 0;
    $cageColor = $cage->color;
    $cageColorSoft = $cage->colorSoft;
@endphp

@if($mode === 'compact')
    {{-- ─── Compact: small square for grid views ─── --}}
    <{{ $selectable ? 'button' : 'div' }}
        @if($selectable)
            type="button"
            onclick="{{ $onclick }}"
            @if($selected) aria-pressed="true" @else aria-pressed="false" @endif
        @endif
        tabindex="{{ $selectable ? '0' : '-1' }}"
        role="{{ $selectable ? 'button' : null }}"
        aria-label="{{ $cage->cage_code }} slot {{ $slot->row_number }}-{{ $slot->column_number }}, {{ $occupancy }} hens"
        class="flex flex-col items-center justify-center w-10 h-10 rounded-xl border transition-all {{ $selectable ? 'cursor-pointer hover:scale-105' : '' }} {{ $class }}"
        style="
            background-color: {{ $selected ? $cageColorSoft : '#ffffff' }};
            border-color: {{ $selected ? $cageColor : '#e6e6e6' }};
            {{ $selected ? 'border-width: 2px;' : '' }}
        "
    >
        @if($slot->has_sensor)
            <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full" style="background-color: #0075de;"></span>
        @endif
        @if($isEmpty)
            <span class="text-xs" style="color: #a39e98;">—</span>
        @else
            <span class="text-xs font-semibold" style="color: {{ $isFull ? '#9b1c24' : '#1f1f1f' }}">
                {{ $occupancy }}
            </span>
        @endif
    </{{ $selectable ? 'button' : 'div' }}>

@else
    {{-- ─── Detailed: full card with occupancy bar ─── --}}
    <{{ $selectable ? 'button' : 'div' }}
        @if($selectable)
            type="button"
            onclick="{{ $onclick }}"
            @if($selected) aria-pressed="true" @else aria-pressed="false" @endif
        @endif
        tabindex="{{ $selectable ? '0' : '-1' }}"
        role="{{ $selectable ? 'button' : null }}"
        aria-label="{{ $cage->cage_code }} slot {{ $slot->row_number }}-{{ $slot->column_number }}, {{ $occupancy }} of {{ $max }} hens"
        class="flex flex-col gap-2 p-3 rounded-xl border transition-all {{ $selectable ? 'cursor-pointer hover:shadow-soft' : '' }} {{ $class }}"
        style="
            background-color: {{ $selected ? $cageColorSoft : '#ffffff' }};
            border-color: {{ $selected ? $cageColor : '#e6e6e6' }};
            {{ $selected ? 'border-width: 2px;' : '' }}
        "
    >
        {{-- Header: slot position + sensor badge --}}
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold" style="color: {{ $cageColor }}">
                {{ $cage->cage_code }} · R{{ $slot->row_number }}C{{ $slot->column_number }}
            </span>
            @if($slot->has_sensor)
                <x-status-badge status="Sensor" type="slot" />
            @endif
        </div>

        {{-- Occupancy bar --}}
        <div>
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs" style="color: #615d59;">
                    {{ $isEmpty ? 'Empty' : $occupancy . ' / ' . $max . ' hens' }}
                </span>
                <span class="text-xs font-medium" style="color: {{ $isFull ? '#9b1c24' : ($isEmpty ? '#a39e98' : '#1f6b3a') }}">
                    {{ number_format($pct, 0) }}%
                </span>
            </div>
            <div class="w-full h-1.5 rounded-full overflow-hidden" style="background-color: #f0f0f0;">
                <div
                    class="h-full rounded-full transition-all"
                    style="
                        width: {{ $pct }}%;
                        background-color: {{ $isFull ? '#9b1c24' : ($pct > 70 ? '#8a5a00' : '#1f6b3a') }};
                    "
                ></div>
            </div>
        </div>

        {{-- Footer: age info if available --}}
        @if($slot->primaryHen)
        <div class="flex items-center gap-1">
            <i data-lucide="bird" class="w-3 h-3" style="color: #a39e98;"></i>
            <span class="text-xs" style="color: #615d59;">
                {{ $slot->primaryHen->breed }} · {{ $slot->primaryHen->flock_age_weeks }}w
            </span>
        </div>
        @endif
    </{{ $selectable ? 'button' : 'div' }}>
@endif
