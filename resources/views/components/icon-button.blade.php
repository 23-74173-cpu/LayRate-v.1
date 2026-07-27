{{--
    <x-icon-button icon="trash-2" label="Remove hen" color="red" onclick="..." />
    Circular icon-only action button. Replaces the ~9x-per-file hand-rolled
    "p-1.5 rounded-full hover:bg-{color}-50" pattern used for row/card actions
    across chickens, eggs, hardware, and feed.

    Props:
      - icon (string, required) — Lucide icon name
      - label (string, required) — becomes aria-label (icon-only buttons must have one)
      - color (string, default 'neutral') — hover tint: neutral | blue | green | emerald | orange | purple | red
      - iconSize (string, default 'w-3.5 h-3.5')
      - type (string, default 'button')
--}}
@props(['icon', 'label', 'color' => 'neutral', 'iconSize' => 'w-3.5 h-3.5', 'type' => 'button'])

@php
    $colorClasses = [
        'neutral' => 'hover:bg-black/5',
        'blue'    => 'hover:bg-blue-50',
        'green'   => 'hover:bg-green-50',
        'emerald' => 'hover:bg-emerald-50',
        'orange'  => 'hover:bg-orange-50',
        'purple'  => 'hover:bg-purple-50',
        'red'     => 'hover:bg-red-50',
    ];

    $classes = 'p-1.5 rounded-full transition-colors cursor-pointer ' . ($colorClasses[$color] ?? $colorClasses['neutral']);
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} style="color: #a39e98;" aria-label="{{ $label }}">
    <i data-lucide="{{ $icon }}" class="{{ $iconSize }}"></i>
</button>
