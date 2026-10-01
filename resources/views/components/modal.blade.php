{{--
/**
 * <x-modal>
 *
 * Shared centered message-dialog component. Same visual language as
 * <x-confirm-modal> (both styled once in app.css: .modal-card, .modal-icon-*,
 * .modal-title, .modal-message, .modal-actions, .modal-btn-*).
 *
 * Layout: large circular tinted icon centered on top, centered bold title,
 * centered muted message, centered buttons (side-by-side equal width on
 * sm+, full-width stacked on mobile). Small X in the top-right corner.
 *
 * Behavior contract (matches every legacy data-modal dialog, so the global
 * ESC / Enter handling in layouts/app.blade.php keeps working):
 *   - outer wrapper: hidden by default, shown by removing `hidden` + adding
 *     `flex` (or style.display). Pass data-modal + data-close through.
 *   - backdrop click and X / Cancel call the same close expression.
 *   - focus: autofocus the primary action via the `autofocus` slot prop pattern
 *     or data-modal-enter on the action button.
 *
 * Props:
 *   @param string $intent  confirm|danger|warning|success|info|neutral
 *   @param string|null $icon   Lucide icon override (defaults per intent)
 *   @param string $title
 *   @param string|null $message  Plain message (or use the `message` slot)
 *   @param string $cancelLabel
 *   @param string $actionLabel
 *   @param string $maxWidth  e.g. "max-w-md" (default), "max-w-sm"
 *   @param string $close   JS close expression, e.g. "closeFoo()".
 *                          Wired to backdrop, X and Cancel.
 *
 * Slots:
 *   default $slot – extra body between message and actions (e.g. stat rows,
 *     rich message markup)
 *   $actions – full custom footer (overrides the Cancel/Action buttons)
 *
 * Usage:
 *   <x-modal id="noHensModal" data-modal data-close="closeNoHensModal"
 *            intent="info" title="No Unplaced Hens"
 *            message="All hens are assigned…" close="closeNoHensModal()"
 *            cancelLabel="Maybe Later" actionLabel="Register Hens" />
 */
--}}

@props([
    'intent' => 'confirm',
    'icon' => null,
    'title' => '',
    'message' => null,
    'cancelLabel' => 'Cancel',
    'actionLabel' => 'Confirm',
    'maxWidth' => 'max-w-md',
    'close' => null,
])

@php
    $intents = [
        'confirm' => ['icon' => 'circle-check',   'btn' => 'modal-btn--primary'],
        'neutral' => ['icon' => 'circle-check',   'btn' => 'modal-btn--neutral'],
        'danger'  => ['icon' => 'alert-triangle', 'btn' => 'modal-btn--danger'],
        'warning' => ['icon' => 'alert-triangle', 'btn' => 'modal-btn--warning'],
        'success' => ['icon' => 'circle-check',   'btn' => 'modal-btn--success'],
        'info'    => ['icon' => 'circle-info',    'btn' => 'modal-btn--primary'],
    ];
    $resolved = $intents[$intent] ?? $intents['confirm'];
    $intentClass = array_key_exists($intent, $intents) ? $intent : 'confirm';
    $iconName = $icon ?? $resolved['icon'];
    $closeJs = $close ?? '';
    // Plain-text message prop; for rich markup use the default slot instead.
    $hasMessage = $message !== null && trim((string) $message) !== '';
    $hasActions = isset($actions) && trim((string) $actions) !== '';
    $hasBody = isset($slot) && trim((string) $slot) !== '';
@endphp

<div {{ $attributes->merge(['class' => 'fixed inset-0 z-50 hidden min-h-screen min-h-[100dvh] items-center justify-center p-4']) }}
     role="dialog" aria-modal="true" aria-label="{{ $title }}">
    {{-- Backdrop --}}
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]"
         style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);"
         @if($closeJs) onclick="{{ $closeJs }}" @endif></div>

    {{-- Card --}}
    <div class="modal-card relative w-full {{ $maxWidth }} p-6 sm:p-8 max-h-screen max-h-[100dvh] overflow-y-auto">
        {{-- Close X --}}
        <button type="button"
                @if($closeJs) onclick="{{ $closeJs }}" @endif
                class="absolute top-4 right-4 p-1.5 rounded-full hover:bg-black/5 transition-colors"
                aria-label="Close">
            <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
        </button>

        {{-- Intent icon --}}
        <div class="modal-icon modal-icon--{{ $intentClass }}">
            <i data-lucide="{{ $iconName }}"></i>
        </div>

        {{-- Title + message --}}
        <h3 class="modal-title mt-4">{{ $title }}</h3>
        @if($hasMessage)
            <p class="modal-message">{{ $message }}</p>
        @endif

        {{-- Optional extra body --}}
        @if($hasBody)
            <div class="mt-4">{{ $slot }}</div>
        @endif

        {{-- Actions --}}
        @if($hasActions)
            <div class="modal-actions">{{ $actions }}</div>
        @else
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-btn--secondary"
                        @if($closeJs) onclick="{{ $closeJs }}" @endif>
                    {{ $cancelLabel }}
                </button>
                <button type="button" class="modal-btn {{ $resolved['btn'] }}">
                    {{ $actionLabel }}
                </button>
            </div>
        @endif
    </div>
</div>
