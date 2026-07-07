@props(['variant' => 'default', 'lines' => 3, 'class' => ''])

@php
$variants = [
    'default' => 'space-y-2.5',
    'card' => 'space-y-3',
    'table' => 'space-y-2',
    'text' => 'space-y-2',
    'metric' => 'space-y-3',
];
$wrapper = $variants[$variant] ?? $variants['default'];
@endphp

<div {{ $attributes->merge(['class' => "animate-pulse " . $wrapper . " " . $class]) }} aria-busy="true" aria-label="Loading…">
    @if($variant === 'metric')
        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
        <div class="h-8 bg-gray-200 rounded w-3/4"></div>
        <div class="h-3 bg-gray-200 rounded w-1/3"></div>
    @elseif($variant === 'card')
        <div class="h-5 bg-gray-200 rounded w-3/4"></div>
        <div class="h-3 bg-gray-200 rounded w-full"></div>
        <div class="h-3 bg-gray-200 rounded w-5/6"></div>
    @elseif($variant === 'table')
        @for($i = 0; $i < max(2, $lines); $i++)
        <div class="flex items-center space-x-4">
            <div class="h-3 bg-gray-200 rounded w-1/4"></div>
            <div class="h-3 bg-gray-200 rounded w-1/4"></div>
            <div class="h-3 bg-gray-200 rounded w-1/4"></div>
            <div class="h-3 bg-gray-200 rounded w-1/4"></div>
        </div>
        @endfor
    @else
        @for($i = 0; $i < max(1, $lines); $i++)
        <div class="h-3 bg-gray-200 rounded {{ ['w-full', 'w-5/6', 'w-4/5', 'w-3/4'][$i % 4] }}"></div>
        @endfor
    @endif
</div>
