@props(['variant' => 'primary', 'type' => 'button', 'disabled' => false, 'size' => 'md', 'href' => null])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-2 font-medium transition-colors disabled:opacity-60 disabled:cursor-not-allowed';

    $sizeClasses = [
        'md' => 'px-4 py-2 text-sm',
        'sm' => 'px-3 py-1.5 text-xs',
    ];

    $variantClasses = [
        'primary'         => 'rounded-lg bg-[#002D5E] text-white hover:bg-[#001F42]',
        'secondary'       => 'rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]',
        'danger'          => 'rounded-full bg-[#9b1c24] text-white hover:bg-[#7a161d]',
        'outline-primary' => 'rounded border border-[#002D5E] text-[#002D5E] hover:bg-[#002D5E]/5',
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
