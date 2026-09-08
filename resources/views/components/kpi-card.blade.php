{{--
/**
 * <x-kpi-card>
 *
 * Extracted from Dashboard's 11 hand-copied KPI cards (see dashboard/_metric-cards*.blade.php).
 * Shared visual structure across all 11:
 *   - icon chip (40x40 rounded, tinted bg + border) + large watermark (48x48, opacity 0.08)
 *   - gradient-clipped big number (text-[32px] font-bold tracking-[-1px] with background-clip:text)
 *   - bottom accent bar (3px, chip bg color)
 *   - label (text-[10px] uppercase tracking, #5b6472)
 *   - optional secondary pill/trend indicator (slot, e.g. delta vs yesterday, "+X today")
 *   - staggered entrance via dash-rise + animation-delay
 *   - clickable card behavior (role=link, data-nav, data-kpi) bound by bindKpiCards() + info button -> openKpiModal()
 *
 * Props (minimal surface covering exactly what the 11 cards need; no speculative props):
 *
 * @param string $label        Uppercase label (e.g. "Total Hens")
 * @param string|null $value   Static value HTML (used when $target is null). Pass formatted string e.g. "₱1,234.00" or "<span>&mdash;</span>".
 * @param string|null $icon    Lucide icon name for chip + watermark (e.g. "bird", "gauge"). Null hides chip/watermark.
 * @param string $iconBg       Chip background (e.g. "#d6f0e3"). Defaults to neutral.
 * @param string $iconColor    Chip icon color + border color.
 * @param string|null $iconBorder Optional chip border override (defaults to $iconColor)
 * @param string|null $gradient CSS background-image value (e.g. "linear-gradient(135deg,#16a34a,#2D7D46)"). If null, value renders as solid #102A4C.
 * @param string|null $accent  Bottom 3px accent bar color. Defaults to $iconBg.
 * @param string $delay        CSS animation-delay (e.g. "60ms")
 * @param string|null $href    Navigation URL -> sets data-nav + role=link / cursor-pointer
 * @param string|null $kpi     Modal key for breakdown (data-kpi) + renders info button
 * @param string|null $ariaLabel Outer aria-label (defaults to "Go to {label}")
 * @param string|null $infoLabel Aria-label for info button (e.g. "Hens per cage breakdown")
 * @param string $variant      "default" (full Dashboard treatment) | "plain" (Forecast-style minimal label+number box)
 * @param mixed $target        Animated target number (when set, renders <span class="kpi-count" data-target> instead of static $value)
 * @param int $decimals        Decimals for animated count
 * @param string|null $suffix  Suffix after animated count (e.g. "%", "°")
 * @param string $extraClass   Not used directly – prefer passing class="" via attributes (e.g. class="col-span-2 sm:col-span-1")
 *
 * Slots:
 *   @slot $slot / $secondary – secondary trend pill markup. Use default slot:
 *     <x-kpi-card> <div class="text-xs ...">▲ 1.2% vs yesterday</div> </x-kpi-card>
 *   or named slot:
 *     <x-kpi-card> <x-slot:secondary>...</x-slot:secondary> </x-kpi-card>
 *
 * Variant decision – Forecast migration:
 *   Forecast's existing KPI pills (Weeks in month, Days in month, Forecast days, Current week)
 *   are plain "label + big number" boxes (bg-white, border #D9D9D9, p-4, text-2xl #333333) with no
 *   icon / gradient / accent bar / watermark. They are derived calendar metadata, not production
 *   KPIs that benefit from navigate/breakdown or high visual emphasis.
 *
 *   Decision: keep Forecast on a lower-emphasis appearance via variant="plain" rather than
 *   forcing full Dashboard styling (icon + gradient + accent). Rationale:
 *     1. Visual hierarchy – Dashboard gradients/chips signal actionable, navigable KPIs; applying
 *        that to auxiliary counts (e.g. "Weeks in month = 5") would inflate their perceived
 *        importance and clash with Forecast's actual high-value metrics (forecast avg, MAE/MAPE).
 *     2. Semantics – inventing icons/gradients for generic counts (weeks, days) would be arbitrary
 *        and misleading; plain preserves honesty.
 *     3. Minimal API – one variant switch reuses the same grid/card semantics without speculative
 *        color/icon props for a page that doesn't need them. If a future page needs graduated
 *        emphasis, add variants then; don't overfit now.
 *
 *   Result: <x-kpi-card variant="plain" label="Weeks in month" :value="$weeksInMonth" />
 *   renders pixel-equivalent to Forecast's original plain box while going through the same
 *   component entry point. The next migration prompt can reference this real API.
 *
 * Usage examples (Dashboard):
 *   <x-kpi-card label="Total Hens" icon="bird" iconBg="#d6f0e3" iconColor="#2D7D46"
 *       gradient="linear-gradient(135deg,#16a34a,#2D7D46)" accent="#d6f0e3" delay="0ms"
 *       :href="route('chickens.index')" kpi="hens" ariaLabel="Go to Hens" infoLabel="Hens per cage breakdown"
 *       :target="$totalHens" />
 *
 *   <x-kpi-card label="Today's HDEP" icon="gauge" ... :target="$todayHdep" :decimals="1" suffix="%">
 *       <div class="text-xs ...">{{ $hdepDelta >=0 ? '▲' : '▼' }} {{ abs($hdepDelta) }}% vs yesterday</div>
 *   </x-kpi-card>
 *
 * Mobile/desktop: outer width controlled by parent grid (e.g. grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3).
 * This component is intentionally layout-agnostic and responsive via its grid container.
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
    'accent' => null,
    'delay' => '0ms',
    'href' => null,
    'kpi' => null,
    'ariaLabel' => null,
    'infoLabel' => null,
    'variant' => 'default',
    'target' => null,
    'decimals' => 0,
    'suffix' => null,
])

@php
    $iconBorder = $iconBorder ?? $iconColor;
    $accent = $accent ?? $iconBg;
    $isPlain = $variant === 'plain';
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
    $infoAriaLabel = $infoLabel ?? ($label ? $label . ' breakdown' : 'Breakdown');
@endphp

@if($isPlain)
    {{-- Plain variant: Forecast-style minimal box – no chip/watermark/gradient/accent/dash-rise/kpi-card --}}
    <div {{ $attributes->merge(['class' => 'bg-white rounded-lg border border-[#D9D9D9] p-4']) }}
         @if($isClickable) role="link" tabindex="0" aria-label="{{ $outerAriaLabel }}" data-nav="{{ $href }}" @if($kpi) data-kpi="{{ $kpi }}" @endif @endif
    >
        <div class="kpi-label">{{ $label }}</div>
        <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333] mt-2">
            @if($target !== null)
                <span class="kpi-count" data-target="{{ $target }}" data-decimals="{{ $decimals }}">0</span>{{ $suffix ?? '' }}
            @elseif($value !== null)
                {!! $value !!}{{ $suffix ?? '' }}
            @endif
        </div>
        @if($hasSecondary)
            <div class="mt-1.5">{{ $secondaryContent }}</div>
        @endif
    </div>
@else
    {{-- Default variant: full Dashboard KPI card – pixel-equivalent to hand-copied original --}}
    <div {{ $attributes->merge(['class' => 'kpi-card dash-rise relative overflow-hidden rounded-2xl border p-3' . ($isClickable ? ' cursor-pointer' : '')]) }}
         style="background-color: #f8f8f8; border-color: #e6e6e6; animation-delay: {{ $delay }};"
         @if($isClickable) role="link" tabindex="0" aria-label="{{ $outerAriaLabel }}" data-nav="{{ $href }}" @endif
         @if($kpi) data-kpi="{{ $kpi }}" @endif
    >
        @if($icon)
            <span class="kpi-watermark" style="color:#CDD2DA;"><i data-lucide="{{ $icon }}" class="w-full h-full"></i></span>
        @endif
        <div class="relative flex items-start justify-between">
            @if($icon)
                <span class="kpi-chip" style="background-color: {{ $iconBg }}; color: {{ $iconColor }}; border: 1px solid {{ $iconBorder }};">
                    <i data-lucide="{{ $icon }}" class="w-4 h-4"></i>
                </span>
            @else
                <span></span>
            @endif
            @if($kpi)
                <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                        onclick="event.stopPropagation(); openKpiModal('{{ $kpi }}')" aria-label="{{ $infoAriaLabel }}">
                    <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                </button>
            @endif
        </div>
        <div class="relative mt-2">
            <div class="kpi-label">{{ $label }}</div>

            @if($target !== null)
                {{-- Animated count (kpi-count) – gradient text if gradient provided, else solid --}}
                @if($gradient)
                    <div class="text-[32px] font-bold leading-none tracking-[-1px] mt-2 kpi-value" style="background-image: {{ $gradient }};">
                        <span class="kpi-count" data-target="{{ $target }}" data-decimals="{{ $decimals }}">0</span>{{ $suffix ?? '' }}
                    </div>
                @else
                    <div class="text-[32px] font-bold leading-none tracking-[-1px] mt-2 text-[#102A4C]">
                        <span class="kpi-count" data-target="{{ $target }}" data-decimals="{{ $decimals }}">0</span>{{ $suffix ?? '' }}
                    </div>
                @endif
            @elseif($value !== null)
                @if($gradient)
                    <div class="text-[32px] font-bold leading-none tracking-[-1px] mt-2 kpi-value" style="background-image: {{ $gradient }};">{!! $value !!}</div>
                @else
                    <div class="text-[32px] font-bold leading-none tracking-[-1px] mt-2 text-[#102A4C]">{!! $value !!}</div>
                @endif
            @else
                {{-- Null value fallback – mirrors original feed-cost empty state (muted em dash) --}}
                <div class="text-[32px] font-bold leading-none tracking-[-1px] mt-2 text-[#102A4C]"><span class="text-lg text-[#9CA3AF]">&mdash;</span></div>
            @endif

            @if($hasSecondary)
                <div class="mt-1.5">{{ $secondaryContent }}</div>
            @endif
        </div>
        <span class="kpi-accent" style="background-color: {{ $accent }};"></span>
    </div>
@endif
