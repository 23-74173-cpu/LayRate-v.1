@props(['id', 'height' => 160, 'empty' => null])
<div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
    {{ $slot ?? '' }}
    @if($empty)
    <div id="{{ $id }}Empty" class="h-[{{ $height }}px] flex items-center justify-center text-sm" style="color: #a39e98;">{{ $empty }}</div>
    @endif
    <canvas id="{{ $id }}" height="{{ $height }}" class="{{ $empty ? 'hidden' : '' }}"></canvas>
</div>
