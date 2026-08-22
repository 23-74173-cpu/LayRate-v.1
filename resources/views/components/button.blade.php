@props(['variant' => 'primary', 'type' => 'button', 'disabled' => false, 'size' => 'md', 'href' => null])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-2 font-medium transition-colors cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed';

    $sizeClasses = [
        'md' => 'px-4 py-2 text-sm',
        'sm' => 'px-3 py-1.5 text-xs',
    ];

    $variantClasses = [
        'primary'         => 'rounded-lg bg-navy text-white hover:brightness-90',
        'secondary'       => 'rounded-lg border border-hairline text-ink-muted hover:bg-canvas-soft',
        'danger'          => 'rounded-full bg-alert-text text-white hover:brightness-90',
        'outline-primary' => 'rounded border border-navy text-navy hover:bg-navy/5',
        'outline-warning' => 'rounded border border-orange-400 text-orange-600 hover:bg-orange-50',
        'outline-danger'  => 'rounded border border-red-400 text-red-500 hover:bg-red-50',
    ];

    $classes = $baseClasses
        . ' ' . ($sizeClasses[$size] ?? $sizeClasses['md'])
        . ' ' . ($variantClasses[$variant] ?? $variantClasses['primary']);
@endphp

@if($href && !$disabled)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
@else
<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} {{ $disabled ? 'disabled' : '' }}>
    {{ $slot }}
</button>
@endif
