@props(['header' => null, 'padding' => 'p-5', 'class' => '', 'noPadding' => false])

<div {{ $attributes->merge(['class' => "bg-surface rounded-lg border border-hairline hover:shadow-md transition-shadow {$class}"]) }}>
    @if($header)
    <div class="px-5 py-3 border-b border-hairline bg-canvas-soft">
        <{{ is_string($header) ? 'h3' : 'div' }} class="text-sm font-semibold text-ink-secondary">{{ is_string($header) ? $header : '' }}{{ !is_string($header) && $header->isNotEmpty() ? $header : '' }}</{{ is_string($header) ? 'h3' : 'div' }}>
    </div>
    @endif
    @if(isset($headerSlot))
    <div class="px-5 py-3 border-b border-hairline bg-canvas-soft">
        {{ $headerSlot }}
    </div>
    @endif
    <div class="{{ $noPadding ? '' : $padding }}">
        {{ $slot }}
    </div>
</div>
