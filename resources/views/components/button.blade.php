@props(['variant' => 'primary', 'type' => 'button', 'disabled' => false, 'size' => 'md', 'href' => null])

@php
    // No cursor-pointer: the global rule (app.css) covers button:not(:disabled).
    $baseClasses = 'inline-flex items-center justify-center gap-2 font-medium transition-colors disabled:opacity-60 disabled:cursor-not-allowed';

    $sizeClasses = [
        'md' => 'px-4 py-2 text-sm',
        'sm' => 'px-3 py-1.5 text-xs',
    ];

    $variantClasses = [
        'primary'         => 'rounded-lg bg-navy text-white hover:brightness-90',
        'secondary'       => 'rounded-lg border border-hairline text-ink-muted hover:bg-canvas-soft',
        'danger'          => 'rounded-full bg-alert-text text-white hover:brightness-90',
        'outline-primary' => 'rounded border border-navy text-navy hover:bg-navy/5',
        'outline-warning' => 'rounded border border-warning text-warning hover:bg-warning-bg',
        'outline-danger'  => 'rounded border border-danger text-danger hover:bg-danger-bg',
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
