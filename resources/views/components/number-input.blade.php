{{--
/**
 * <x-number-input>
 *
 * Native number input with optional +/- stepper buttons (egg counts, feed
 * amounts). The input stays a real <input type="number"> — mobile keyboards,
 * validation and accessibility unchanged. Steppers call stepUp()/stepDown()
 * and dispatch input+change so existing oninput handlers keep working.
 *
 * Props pass through to the input via $attributes (name, min, max, step,
 * value, required, oninput, class …). Layout: input + two buttons joined.
 *
 * Usage:
 *   <x-number-input name="egg_count" id="eggCount" min="0" value="0" required
 *                   oninput="computeHdep()" class="..." />
 */
--}}

@props(['steppers' => true])

<div {{ $attributes->only('class')->merge(['class' => '']) }} style="display: contents;">
    @if($steppers)
    <div class="flex items-stretch gap-1.5">
        <button type="button" tabindex="-1" data-stepper="-1" aria-label="Decrease value"
                class="shrink-0 w-10 rounded-lg border border-hairline bg-white text-lg font-semibold text-ink-muted hover:bg-canvas-soft active:bg-hairline transition-colors">−</button>
        <input type="number" {{ $attributes->except(['class', 'steppers'])->merge(['class' => 'min-w-0 flex-1']) }}>
        <button type="button" tabindex="-1" data-stepper="1" aria-label="Increase value"
                class="shrink-0 w-10 rounded-lg border border-hairline bg-white text-lg font-semibold text-ink-muted hover:bg-canvas-soft active:bg-hairline transition-colors">+</button>
    </div>
    @else
    <input type="number" {{ $attributes }}>
    @endif
</div>

<script>
(function () {
    // Guarded: the component may render once per input and re-execute on
    // Turbo visits; the delegated listener must attach exactly once.
    if (window.__numberInputWired) return;
    window.__numberInputWired = true;
    // Delegated: works for Turbo-rendered steppers, never double-binds.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-stepper]');
        if (!btn) return;
        var wrap = btn.parentElement;
        var input = wrap && wrap.querySelector('input[type="number"]');
        if (!input || input.disabled || input.readOnly) return;
        e.preventDefault();
        if (btn.getAttribute('data-stepper') === '1') input.stepUp();
        else input.stepDown();
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
})();
</script>
