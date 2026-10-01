{{--
/**
 * <x-empty-state>
 *
 * Branded empty state: soft info-tinted icon tile, one line of guidance,
 * optional primary action. Replaces plain-text "No data" placeholders.
 *
 * Props:
 *   @param string $icon     Lucide icon (default "inbox")
 *   @param string $message  Guidance line (required)
 *   @param string|null $actionUrl
 *   @param string|null $actionLabel
 *
 * Slots:
 *   $actions – custom footer (overrides the single action button)
 *
 * Usage:
 *   <x-empty-state icon="egg" message="No production data for the selected period."
 *                  actionUrl="{{ route('eggs.logging') }}" actionLabel="Log Eggs" />
 */
--}}

@props([
    'icon' => 'inbox',
    'message' => '',
    'actionUrl' => null,
    'actionLabel' => null,
])

@php
    $hasActions = isset($actions) && trim((string) $actions) !== '';
@endphp

<div class="rounded-xl border border-hairline bg-white py-8 px-4 text-center">
    <div class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-info-bg text-info">
        <i data-lucide="{{ $icon }}" class="h-5 w-5"></i>
    </div>
    <p class="mx-auto max-w-[42ch] text-sm leading-relaxed text-ink-muted">{{ $message }}</p>
    @if($hasActions)
        <div class="mt-4 flex items-center justify-center gap-3">{{ $actions }}</div>
    @elseif($actionUrl && $actionLabel)
        <div class="mt-4">
            <x-button href="{{ $actionUrl }}" size="sm">{{ $actionLabel }}</x-button>
        </div>
    @endif
</div>
