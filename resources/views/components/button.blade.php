@props(['variant' => 'primary', 'type' => 'button', 'disabled' => false])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors';

    $variantClasses = [
        'primary' => 'bg-[#002D5E] text-white hover:bg-[#001F42]',
        'secondary' => 'border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]',
        'danger' => 'bg-[#9b1c24] text-white hover:bg-[#7a161d]',
    ];

    $classes = $baseClasses . ' ' . ($variantClasses[$variant] ?? $variantClasses['primary']);

    if ($disabled) {
        $classes .= ' opacity-60 cursor-not-allowed';
    }
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} {{ $disabled ? 'disabled' : '' }}>
    {{ $slot }}
</button>
