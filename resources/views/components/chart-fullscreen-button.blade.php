{{--
    <x-chart-fullscreen-button chart="canvasId" title="Chart title" />

    Opens the chart in the full-screen viewer (public/js/chart-fullscreen.js).
    Works for any chart drawn through LayRateChart. Clicks are handled by one
    delegated listener, so it works inside lazily loaded Turbo frames too.
    Hidden automatically while its canvas isn't on the page (empty states).

    floating: absolute top-right corner of the nearest `relative` card, for
    charts without an <x-card-header> actions slot.
--}}
@props(['chart', 'title', 'floating' => false])

<button type="button"
        {{ $attributes->merge(['class' => 'chart-fs-btn' . ($floating ? ' chart-fs-btn--floating' : '')]) }}
        data-chart-fullscreen="{{ $chart }}"
        data-chart-title="{{ $title }}"
        aria-label="Open {{ $title }} in full screen"
        title="Full screen">
    <i data-lucide="maximize-2" aria-hidden="true"></i>
</button>
