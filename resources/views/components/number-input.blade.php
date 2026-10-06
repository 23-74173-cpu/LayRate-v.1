{{--
/**
 * <x-number-input>
 *
 * Native number input with optional +/- stepper buttons (egg counts, feed
 * amounts). The input stays a real <input type="number"> — mobile keyboards,
 * validation and accessibility unchanged. Steppers call stepUp()/stepDown()
 * and dispatch input+change so existing oninput handlers keep working.
 * Press-and-hold on a stepper repeats the step (accelerating) until release.
 *
 * Props pass through to the input via $attributes (name, min, max, step,
 * value, required, oninput, class …). Layout: input on the left, both
 * stepper buttons grouped on the right (− then +) with matching borders
 * so the control reads as one uniform row.
 *
 * Usage:
 *   <x-number-input name="egg_count" id="eggCount" min="0" value="0" required
 *                   oninput="computeHdep()" class="..." />
 */
--}}

@props(['steppers' => true])

<div style="display: contents;">
    @if($steppers)
    <div class="flex items-stretch gap-1.5">
        <input type="number" {{ $attributes->merge(['class' => 'min-w-0 flex-1']) }}>
        <button type="button" tabindex="-1" data-stepper="-1" aria-label="Decrease value"
                class="shrink-0 w-10 rounded-lg border border-hairline bg-white text-lg font-semibold text-ink-muted hover:bg-canvas-soft active:bg-hairline transition-colors touch-none select-none">−</button>
        <button type="button" tabindex="-1" data-stepper="1" aria-label="Increase value"
                class="shrink-0 w-10 rounded-lg border border-hairline bg-white text-lg font-semibold text-ink-muted hover:bg-canvas-soft active:bg-hairline transition-colors touch-none select-none">+</button>
    </div>
    @else
    <input type="number" {{ $attributes }}>
    @endif
</div>

<script>
(function () {
    // Guarded: the component may render once per input and re-execute on
    // Turbo visits; the delegated listeners must attach exactly once.
    if (window.__numberInputWired) return;
    window.__numberInputWired = true;

    function findInput(btn) {
        var wrap = btn && btn.parentElement;
        return wrap && wrap.querySelector('input[type="number"]');
    }

    function stepInput(input, dir) {
        if (!input || input.disabled || input.readOnly) return false;
        if (dir === 1) input.stepUp();
        else input.stepDown();
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    function stopHold(btn) {
        if (!btn) return;
        if (btn.__holdTimer) { clearTimeout(btn.__holdTimer); btn.__holdTimer = null; }
        btn.__holding = false;
    }

    // Single tap / click (keyboard, assistive tech, or any click without a
    // preceding pointerdown). Pointer-driven taps are already stepped on
    // pointerdown and skip here via __holdStepped.
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-stepper]') : null;
        if (!btn) return;
        if (btn.__holdStepped) { btn.__holdStepped = false; return; }
        e.preventDefault();
        stepInput(findInput(btn), btn.getAttribute('data-stepper') === '1' ? 1 : -1);
    });

    // Press-and-hold: step once immediately, then repeat (accelerating)
    // until release. Delegated: works for Turbo-rendered steppers.
    document.addEventListener('pointerdown', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-stepper]') : null;
        if (!btn) return;
        var input = findInput(btn);
        if (!input || input.disabled || input.readOnly) return;
        e.preventDefault();
        var dir = btn.getAttribute('data-stepper') === '1' ? 1 : -1;
        btn.__holdStepped = true;
        btn.__holding = true;
        stepInput(input, dir);
        try { if (e.pointerId !== undefined && btn.setPointerCapture) btn.setPointerCapture(e.pointerId); } catch (err) {}
        var rate = 130;
        var repeat = function () {
            if (!btn.__holding) return;
            if (!stepInput(findInput(btn), dir)) { stopHold(btn); return; }
            rate = Math.max(40, rate - 12); // accelerate while held
            btn.__holdTimer = setTimeout(repeat, rate);
        };
        btn.__holdTimer = setTimeout(repeat, 450); // initial pause before repeat
    });

    document.addEventListener('pointerup', function () {
        document.querySelectorAll('[data-stepper]').forEach(stopHold);
    }, true);
    document.addEventListener('pointercancel', function () {
        document.querySelectorAll('[data-stepper]').forEach(stopHold);
    }, true);
    document.addEventListener('lostpointercapture', function (e) {
        stopHold(e.target && e.target.closest ? e.target.closest('[data-stepper]') : null);
    }, true);
    // Long-press context menu would interrupt the hold on mobile.
    document.addEventListener('contextmenu', function (e) {
        if (e.target && e.target.closest && e.target.closest('[data-stepper]')) e.preventDefault();
    });
})();
</script>
