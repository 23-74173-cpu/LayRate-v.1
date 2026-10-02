{{--
/**
 * <x-kpi-card>
 *
 * Single reusable KPI card used system-wide (dashboard, analytics, forecast,
 * feed, environment, hardware, finance, chickens, mortality, eggs).
 *
 * Design: solid white card, horizontal two-column layout —
 *   left:  large 56px icon in a soft primary-tinted rounded tile
 *   right: uppercase label, large bold value, trend/subtext line
 *
 * Backward-compatibility notes:
 *   - `cardGradient`, `gradient` and `accent` props are accepted but ignored.
 *     All cards render white with no per-card color variants.
 *   - `variant="plain"` renders the same white treatment (unified).
 *   - `iconBg` / `iconColor` are accepted but ignored; the tile always uses
 *     the primary tint (#DCEBFA tile + var(--color-navy) icon) from the design tokens.
 *   - `kpi` still renders the info button (it opens a real breakdown via
 *     openKpiModal()), kept small in the top-right of the text column.
 *   - `href` still makes the whole card clickable (data-nav + role=link),
 *     bound by bindKpiCards(). `target`/`decimals`/`suffix` still drive the
 *     animated .kpi-count count-up. `value` renders static HTML.
 *
 * Props:
 *   @param string $label
 *   @param string|null $value     Static value HTML (when $target is null)
 *   @param string|null $icon      Lucide icon name. Null hides the tile.
 *   @param string $iconBg         (ignored, kept for compat)
 *   @param string $iconColor      (ignored, kept for compat)
 *   @param string|null $iconBorder (ignored, kept for compat)
 *   @param string|null $gradient  (ignored, kept for compat)
 *   @param string|null $cardGradient (ignored, kept for compat)
 *   @param string|null $accent    (ignored, kept for compat)
 *   @param string $delay          CSS animation-delay (e.g. "60ms")
 *   @param string|null $href      Navigation URL -> data-nav + role=link
 *   @param string|null $kpi       Modal key for breakdown (data-kpi) + info btn
 *   @param string|null $ariaLabel Outer aria-label (defaults to "Go to {label}")
 *   @param string|null $infoLabel Aria-label for info button
 *   @param string $variant        "default" | "plain" (both render white)
 *   @param mixed $target          Animated target number
 *   @param int $decimals          Decimals for animated count
 *   @param string|null $suffix    Suffix after animated count (e.g. "%", "°")
 *
 * Slots:
 *   default / $secondary – trend pill or subtext, e.g.
 *     <div class="trend-pill-up">▲ 1.2% vs yesterday</div>
 *     Prefer .trend-pill-up / .trend-pill-down (app.css) so trends read
 *     well on the white card.
 *
 * Layout: width controlled by the parent grid
 * (e.g. grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3). The text column
 * uses min-w-0 + break-words so long values wrap instead of clipping.
 */
--}}

@props([
    'label' => '',
    'value' => null,
    'icon' => null,
    'iconBg' => '#F3F4F6',
    'iconColor' => '#6B7280',
    'iconBorder' => null,
    'gradient' => null,
    'cardGradient' => null,
    'accent' => null,
    'delay' => '0ms',
    'href' => null,
    'kpi' => null,
    'ariaLabel' => null,
    'infoLabel' => null,
    'info' => null,
    'infoKey' => null,
    'variant' => 'default',
    'target' => null,
    'decimals' => 0,
    'suffix' => null,
])

@php
    $isClickable = $href !== null && $href !== '';
    // Resolve secondary slot: named slot $secondary takes precedence over default $slot
    $secondaryContent = '';
    if (isset($secondary) && trim((string) $secondary) !== '') {
        $secondaryContent = $secondary;
    } elseif (isset($slot) && trim((string) $slot) !== '') {
        $secondaryContent = $slot;
    }
    $hasSecondary = trim((string) $secondaryContent) !== '';
    $outerAriaLabel = $ariaLabel ?? ($label ? 'Go to ' . $label : null);
    $infoAriaLabel = 'About ' . ($label ?: 'this metric');
    // Explanation popover text: literal `info` wins, otherwise the lang key.
    // A card with neither gets a visible warning in debug builds so it is
    // easy to spot (never shown in production).
    $infoText = $info;
    if ($infoText === null && $infoKey !== null && \Illuminate\Support\Facades\Lang::has('kpi-info.' . $infoKey)) {
        $infoText = \Illuminate\Support\Facades\Lang::get('kpi-info.' . $infoKey);
    }
    $missingInfo = ($infoText === null || trim((string) $infoText) === '') && config('app.debug');
    $plainLabel = trim(strip_tags((string) $label));
@endphp

<div {{ $attributes->merge(['class' => 'kpi-card dash-rise relative overflow-hidden rounded-2xl border border-[#E6E6E6] bg-white dark:bg-surface p-4 flex items-start gap-4']) }}
     style="animation-delay: {{ $delay }};"
     @if($isClickable) role="link" tabindex="0" aria-label="{{ $outerAriaLabel }}" data-nav="{{ $href }}" @endif
     @if($kpi) data-kpi="{{ $kpi }}" @endif
>
    {{-- Explanation popover trigger: always rendered, same corner on every
         card. Click is caught in the capture phase by kpi-info.js, which also
         stops card navigation. The per-cage breakdown (data-kpi) moved inside
         the popover as an explicit action. --}}
    <button type="button" class="kpi-info-btn"
            data-kpi-popover
            data-info-title="{{ $plainLabel }}"
            data-info-text="{{ $infoText ?? '' }}"
            @if($kpi) data-kpi-breakdown="{{ $kpi }}" @endif
            aria-label="{{ $infoAriaLabel }}">
        <i data-lucide="info"></i>
    </button>
    @if($icon)
        <span class="kpi-icon-tile shrink-0" aria-hidden="true">
            <i data-lucide="{{ $icon }}"></i>
        </span>
    @endif
    <div class="relative min-w-0 flex-1 pr-6">
        <div class="flex items-start justify-between gap-2">
            <span class="kpi-label min-w-0 flex-1">{{ $label }}</span>
            @if($missingInfo)
                <span class="kpi-info-missing" title="Missing kpi info text: add info= or infoKey= to this card">!</span>
            @endif
        </div>

        @if($target !== null)
            {{-- Animated count (.kpi-count). .kpi-value is kept as a JS hook:
                 chickens/index + eggs/stocks update card numbers at runtime
                 via card.querySelector('.kpi-value'). --}}
            <div class="kpi-number kpi-value">
                <span class="kpi-count" data-target="{{ $target }}" data-decimals="{{ $decimals }}">0</span>{{ $suffix ?? '' }}
            </div>
        @elseif($value !== null)
            <div class="kpi-number kpi-value">{!! $value !!}</div>
        @else
            {{-- Null value fallback – mirrors original feed-cost empty state (muted em dash) --}}
            <div class="kpi-number kpi-value"><span class="text-lg text-[#9CA3AF]">&mdash;</span></div>
        @endif

        @if($hasSecondary)
            <div class="kpi-secondary">{{ $secondaryContent }}</div>
        @endif
    </div>
</div>
