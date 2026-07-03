@props(['header' => null, 'padding' => 'p-5', 'class' => '', 'noPadding' => false])

<div {{ $attributes->merge(['class' => "bg-white rounded-lg border border-[#D9D9D9] hover:shadow-md transition-shadow {$class}"]) }}>
    @if($header)
    <div class="px-5 py-3 border-b border-[#D9D9D9] bg-[#F9F9F7]">
        <{{ is_string($header) ? 'h3' : 'div' }} class="text-sm font-semibold text-[#333333]">{{ is_string($header) ? $header : '' }}{{ !is_string($header) && $header->isNotEmpty() ? $header : '' }}</{{ is_string($header) ? 'h3' : 'div' }}>
    </div>
    @endif
    @if(isset($headerSlot))
    <div class="px-5 py-3 border-b border-[#D9D9D9] bg-[#F9F9F7]">
        {{ $headerSlot }}
    </div>
    @endif
    <div class="{{ $noPadding ? '' : $padding }}">
        {{ $slot }}
    </div>
</div>
