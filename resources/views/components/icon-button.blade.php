{{--
    <x-icon-button icon="trash-2" label="Remove hen" color="red" onclick="..." />
    Circular icon action button with a visible border, colored icon, hover
    highlight, and a native hover tooltip (title). Used across chickens,
    eggs, hardware, and feed for row/card actions.

    Props:
      - icon (string, required) — Lucide icon name
      - label (string, required) — becomes aria-label AND the hover tooltip
      - color (string, default 'neutral') — tint: neutral | blue | green | emerald | orange | purple | red
      - iconSize (string, default 'w-3.5 h-3.5')
      - type (string, default 'button')
--}}
@props(['icon', 'label', 'color' => 'neutral', 'iconSize' => 'w-3.5 h-3.5', 'type' => 'button'])

@php
    $palette = [
        'neutral' => ['c' => '#615d59', 'b' => '#e6e6e6', 'hb' => '#f0efee', 'hbc' => '#d9d7d4'],
        'blue'    => ['c' => '#0075de', 'b' => '#c9e4fb', 'hb' => '#e8f3fe', 'hbc' => '#9ccdf7'],
        'green'   => ['c' => '#1f6b3a', 'b' => '#cfe8d6', 'hb' => '#e8f5ec', 'hbc' => '#a9d8b6'],
        'emerald' => ['c' => '#0e7c5a', 'b' => '#c6ebe0', 'hb' => '#e0f5ee', 'hbc' => '#93d9c6'],
        'orange'  => ['c' => '#c05621', 'b' => '#f5dcc4', 'hb' => '#fdf0e3', 'hbc' => '#ecc29c'],
        'purple'  => ['c' => '#6d5bd0', 'b' => '#ddd6f6', 'hb' => '#f0edfc', 'hbc' => '#c3b8ec'],
        'red'     => ['c' => '#9b1c24', 'b' => '#f3cdd0', 'hb' => '#fbe4e6', 'hbc' => '#e8a3a9'],
    ];
    $p = $palette[$color] ?? $palette['neutral'];
    $classes = 'inline-flex items-center justify-center p-1.5 rounded-full border transition-all cursor-pointer shrink-0';
@endphp

<button type="{{ $type }}"
        {{ $attributes->merge(['class' => $classes]) }}
        style="color: {{ $p['c'] }}; border-color: {{ $p['b'] }};"
        aria-label="{{ $label }}"
        title="{{ $label }}"
        onmouseenter="this.style.backgroundColor='{{ $p['hb'] }}'; this.style.borderColor='{{ $p['hbc'] }}';"
        onmouseleave="this.style.backgroundColor='transparent'; this.style.borderColor='{{ $p['b'] }}';">
    <i data-lucide="{{ $icon }}" class="{{ $iconSize }}"></i>
</button>
