@props(['tabs' => [], 'active' => '', 'turboFrame' => null])

{{-- overflow-x-auto + shrink-0 + whitespace-nowrap: on narrow screens these
     scroll horizontally instead of wrapping to a second line or silently
     overflowing — there is no wrap fallback here on purpose. --}}
<div class="border-b border-hairline mobile-tab-rail" data-underline-tabs data-active-tab="{{ $active }}">
    <nav class="-mb-px flex gap-6 overflow-x-auto overflow-y-hidden [&::-webkit-scrollbar]:hidden [scrollbar-width:none]" role="tablist" aria-label="Page sections">
        @foreach($tabs as $key => $tab)
            @php
                $isActive = $key === $active;
                // No cursor-pointer: global rules cover <button> and a[href].
                $classes = 'max-sm:min-h-[44px] inline-flex items-center pb-2 text-sm font-medium border-b-2 transition-colors shrink-0 whitespace-nowrap ' .
                    ($isActive ? 'border-navy text-navy' : 'border-transparent text-ink-muted hover:text-ink');
            @endphp
            @if(isset($tab['route']))
                <a href="{{ route($tab['route']) }}" class="{{ $classes }}" role="tab" aria-selected="{{ $isActive ? 'true' : 'false' }}" @if($isActive) aria-current="page" @endif @if($turboFrame) data-turbo-frame="{{ $turboFrame }}" @endif>
                    @if(isset($tab['icon']))<i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4 inline mr-1"></i>@endif
                    {{ $tab['label'] }}
                </a>
            @else
                <button onclick="{{ $tab['onclick'] ?? "switchTab('$key')" }}" class="{{ $classes }}" role="tab" data-tab-key="{{ $key }}" aria-selected="{{ $isActive ? 'true' : 'false' }}" @if($isActive) aria-current="page" @endif>
                    @if(isset($tab['icon']))<i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4 inline mr-1"></i>@endif
                    {{ $tab['label'] }}
                </button>
            @endif
        @endforeach
    </nav>
</div>

<script>
(function () {
    // Keep aria-selected in sync when tabs switch client-side (each page's
    // own switch fn only toggles classes). Delegated + guarded so multiple
    // tab bars and Turbo re-runs stay safe.
    if (window.__underlineTabsAriaBound) return;
    window.__underlineTabsAriaBound = true;
    function revealActiveTabs(root) {
        if (!window.matchMedia('(max-width: 639.98px)').matches) return;
        (root || document).querySelectorAll('[data-underline-tabs]').forEach(function (bar) {
            var active = bar.querySelector('[role="tab"][aria-selected="true"]');
            var nav = bar.querySelector('nav');
            if (active && nav) {
                var navRect = nav.getBoundingClientRect();
                var activeRect = active.getBoundingClientRect();
                if (activeRect.left < navRect.left || activeRect.right > navRect.right) {
                    nav.scrollLeft += activeRect.left - navRect.left;
                }
            }
        });
    }
    revealActiveTabs();
    document.addEventListener('turbo:load', function () { revealActiveTabs(); });
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-underline-tabs] [role="tab"]') : null;
        if (!btn) return;
        var bar = btn.closest('[data-underline-tabs]');
        bar.dataset.activeTab = btn.dataset.tabKey || btn.getAttribute('href') || '';
        bar.querySelectorAll('[role="tab"]').forEach(function (t) {
            t.setAttribute('aria-selected', t === btn ? 'true' : 'false');
            if (t === btn) t.setAttribute('aria-current', 'page');
            else t.removeAttribute('aria-current');
        });
        requestAnimationFrame(function () { revealActiveTabs(bar); });
    });
    document.addEventListener('turbo:frame-load', function (e) { if (e.target) revealActiveTabs(e.target); });
})();
</script>
