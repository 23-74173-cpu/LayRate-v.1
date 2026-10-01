{{--
/**
 * <x-logo>
 *
 * THE LayRate logo — single source of truth. All brand surfaces (login,
 * landing header/footer, sidebar + collapsed rail + mobile drawer, reports,
 * favicons/meta) use this component or the same file:
 *   public/images/layrate-logo.png  (663×885, transparent, egg + rooster
 *   emblem, no shadow, no wordmark — do not redraw, recolor or recreate)
 *
 * The egg is portrait (~3:4). Aspect ratio is always preserved
 * (object-contain + intrinsic width/height attrs, so no layout shift and
 * never stretched).
 *
 * Props:
 *   @param string $size        Tailwind height, e.g. "h-11" (sidebar ~44px),
 *                              "h-10" (landing ~40px), "h-28" (login ~112px).
 *   @param bool $showWordmark  Append a live-text "LayRate" wordmark next to
 *                              the egg (the logo file itself has no wordmark).
 *   @param string $wordmarkClass Styling for the wordmark text
 *                              (e.g. "text-lg font-bold text-navy").
 *   @param string $alt         Defaults to "LayRate logo".
 *   @param bool $eager         Above-the-fold usage: eager + fetchpriority high.
 *
 * Usage:
 *   <x-logo size="h-11" />
 *   <x-logo size="h-10" :showWordmark="true" wordmarkClass="text-lg font-bold text-white" />
 */
--}}

@props([
    'size' => 'h-11',
    'showWordmark' => false,
    'wordmarkClass' => '',
    'alt' => 'LayRate logo',
    'eager' => false,
])

@if($showWordmark)
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <img src="/images/layrate-logo.png" alt="{{ $alt }}" width="663" height="885"
         class="{{ $size }} w-auto object-contain shrink-0"
         loading="{{ $eager ? 'eager' : 'lazy' }}" @if($eager) fetchpriority="high" @endif>
    <span class="{{ $wordmarkClass }}" aria-hidden="true">LayRate</span>
</span>
@else
<img src="/images/layrate-logo.png" alt="{{ $alt }}" width="663" height="885"
     {{ $attributes->merge(['class' => $size . ' w-auto object-contain']) }}
     loading="{{ $eager ? 'eager' : 'lazy' }}" @if($eager) fetchpriority="high" @endif>
@endif
