{{-- Page Header — consistent boxed two-tier header across all sections --}}
@props(['title', 'subtitle' => null, 'subtitleId' => null, 'subtitleClass' => '', 'actionsId' => null])

<style>
    .page-header-actions:empty { display: none; }
</style>

<div class="relative overflow-hidden bg-linear-to-br from-secondary to-sidebar-bg rounded-lg p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
    {{-- Decorative eggs clustered along the right edge only, static, with a
         soft gradient fade toward the left — same egg motif as the login page. --}}
    <div class="page-header-egg-decor" aria-hidden="true"
         style="position:absolute; inset:0; z-index:0; pointer-events:none; color:#9ca3af;"></div>

    <div class="relative z-[1]">
        <h1 class="text-xl font-bold text-white">{{ $title }}</h1>
        @if($subtitle)
        <p @if($subtitleId) id="{{ $subtitleId }}" @endif class="text-sm text-white/75 mt-1 {{ $subtitleClass }}">{{ $subtitle }}</p>
        @endif
    </div>
    {{--
        Always rendered, even with no actions/slot content, so pages that live
        behind a Turbo Frame (the Egg Management tabs) have a stable element to
        sync into on tab clicks. Those clicks only swap turbo-frame#egg-content —
        this header sits outside it and is otherwise never touched by them, which
        was the root cause of the "buttons disappear/reappear" bug: the header
        only repainted on a genuine full page load, never on a frame-scoped tab
        click. See eggs/_tabs.blade.php for the client-side sync.

        The white surface keeps the action buttons (which are styled for light
        backgrounds) readable on the gradient; :empty hides it when there are no
        buttons, and the runtime tab sync repopulates it.
    --}}
    <div class="page-header-actions relative z-[1] shrink-0 bg-white rounded-xl p-2 shadow-sm flex flex-wrap items-center justify-end gap-2"
         @if($actionsId) id="{{ $actionsId }}" @endif>@if(isset($actions)){{ $actions }}@elseif(isset($slot) && trim($slot)){{ $slot }}@endif</div>
</div>

<script>
(function () {
    var decor = document.querySelector('.page-header-egg-decor');
    if (!decor || decor.getAttribute('data-eggs')) return;
    decor.setAttribute('data-eggs', '1');
    var opacities = ['0.30', '0.38', '0.46', '0.55'];
    var count = 34;
    for (var i = 0; i < count; i++) {
        var egg = document.createElement('div');
        egg.style.position = 'absolute';
        // Eggs live only on the right half of the header.
        egg.style.left = (45 + Math.random() * 55).toFixed(2) + '%';
        egg.style.top = (Math.random() * 100).toFixed(2) + '%';
        egg.style.width = (12 + Math.random() * 24).toFixed(1) + 'px';
        egg.style.height = (12 + Math.random() * 24).toFixed(1) + 'px';
        egg.style.opacity = opacities[i % opacities.length];
        egg.style.transform = 'rotate(' + (Math.random() * 360).toFixed(0) + 'deg)';
        egg.innerHTML = '<i data-lucide="egg" class="w-full h-full"></i>';
        decor.appendChild(egg);
    }
    // Soft gradient fade: eggs dissolve toward the left edge.
    decor.style.webkitMaskImage = 'linear-gradient(to right, transparent 0%, black 40%)';
    decor.style.maskImage = 'linear-gradient(to right, transparent 0%, black 40%)';
    if (window.lucide) lucide.createIcons();
})();
</script>
