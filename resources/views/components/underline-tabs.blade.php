@props(['tabs' => [], 'active' => '', 'turboFrame' => null])

{{-- overflow-x-auto + shrink-0 + whitespace-nowrap: on narrow screens these
     scroll horizontally instead of wrapping to a second line or silently
     overflowing — there is no wrap fallback here on purpose. --}}
@php
    $tabListId = $attributes->get('id', 'underline-tabs-' . \Illuminate\Support\Str::uuid());
    $hasRouteLinks = collect($tabs)->contains(fn ($tab) => isset($tab['route']));
    $allClientTabs = ! $hasRouteLinks;
    $hasClientPanels = $allClientTabs && collect($tabs)->every(fn ($tab) => ! empty($tab['panelId'])) && collect($tabs)->every(fn ($tab) => isset($tab['onclick'], $tab['tabBehavior']) && $tab['tabBehavior'] === 'authoritative');
    $hasUnmappedClientTabs = $allClientTabs && ! $hasClientPanels;
@endphp
<div class="border-b border-hairline mobile-tab-rail" data-underline-tabs data-active-tab="{{ $active }}">
    <nav id="{{ $tabListId }}" class="-mb-px flex gap-6 overflow-x-auto overflow-y-hidden [&::-webkit-scrollbar]:hidden [scrollbar-width:none]" aria-label="{{ $hasRouteLinks ? 'Page sections' : 'Content sections' }}" @if($allClientTabs && $hasClientPanels) role="tablist" aria-orientation="horizontal" @endif>
        @foreach($tabs as $key => $tab)
            @php
                $isActive = $key === $active;
                // No cursor-pointer: global rules cover <button> and a[href].
                $classes = 'max-md:min-h-[44px] inline-flex items-center pb-2 text-sm font-medium border-b-2 transition-colors shrink-0 whitespace-nowrap ' .
                    ($isActive ? 'border-navy text-navy' : 'border-transparent text-ink-muted hover:text-ink');
            @endphp
            @if(isset($tab['route']))
                <a href="{{ route($tab['route']) }}" class="{{ $classes }}" @if($isActive) aria-current="page" @endif @if(isset($tab['key'])) data-tab-key="{{ $tab['key'] }}" @endif @if($turboFrame) data-turbo-frame="{{ $turboFrame }}" @endif>
                    @if(isset($tab['icon']))<i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4 inline mr-1"></i>@endif
                    {{ $tab['label'] }}
                </a>
            @else
                @php $tabButtonId = $tabListId . '-' . $key; @endphp
                <button type="button" id="{{ $tabButtonId }}" onclick="{{ $tab['onclick'] ?? "switchTab('$key')" }}" class="{{ $classes }}" @if($allClientTabs && $hasClientPanels) role="tab" aria-controls="{{ $tab['panelId'] }}" aria-selected="{{ $isActive ? 'true' : 'false' }}" tabindex="{{ $isActive ? '0' : '-1' }}" @elseif($hasUnmappedClientTabs) aria-pressed="{{ $isActive ? 'true' : 'false' }}" @endif data-tab-key="{{ $key }}">
                    @if(isset($tab['icon']))<i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4 inline mr-1"></i>@endif
                    {{ $tab['label'] }}
                </button>
            @endif
        @endforeach
    </nav>
</div>

<script>
(function () {
    // Keep client-tab state in sync. Route navigation retains native links.
    if (window.__underlineTabsAriaBound) return;
    window.__underlineTabsAriaBound = true;
    // Only hint at more tabs when the rail actually overflows. The CSS fade
    // is shown below md, where the rail is scrollable.
    function updateTabOverflow(root) {
        (root || document).querySelectorAll('[data-underline-tabs]').forEach(function (bar) {
            var nav = bar.querySelector('nav');
            if (!nav) return;
            bar.dataset.tabsScrollable = nav.scrollWidth > nav.clientWidth + 1 ? 'true' : 'false';
        });
    }
    function revealActiveTabs(root) {
        updateTabOverflow(root);
        if (!window.matchMedia('(max-width: 767.98px)').matches) return;
        (root || document).querySelectorAll('[data-underline-tabs]').forEach(function (bar) {
            var active = bar.querySelector('[role="tab"][aria-selected="true"], a[aria-current="page"]');
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
    function syncTabStops(root) {
        (root || document).querySelectorAll('[data-underline-tabs] nav[role="tablist"]').forEach(function (nav) {
            var bar = nav.closest('[data-underline-tabs]');
            if (!bar) return;
            bar.querySelectorAll('button[role="tab"]').forEach(function (tab) {
                tab.tabIndex = tab.getAttribute('aria-selected') === 'true' ? 0 : -1;
            });
        });
    }
    function syncPressedButtons(root) {
        (root || document).querySelectorAll('[data-underline-tabs]').forEach(function (bar) {
            var buttons = bar.querySelectorAll('button[aria-pressed]');
            var active = Array.from(buttons).find(function (button) { return button.getAttribute('aria-pressed') === 'true'; });
            if (active) {
                bar.dataset.activeTab = active.dataset.tabKey || '';
                buttons.forEach(function (button) {
                    var selected = button === active;
                    button.classList.toggle('border-navy', selected);
                    button.classList.toggle('text-navy', selected);
                    button.classList.toggle('border-transparent', !selected);
                    button.classList.toggle('text-ink-muted', !selected);
                });
            }
        });
    }
    syncTabStops();
    syncPressedButtons();
    revealActiveTabs();
    requestAnimationFrame(function () { updateTabOverflow(); });
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () { updateTabOverflow(); });
    }
    window.addEventListener('resize', function () { updateTabOverflow(); });
    document.addEventListener('turbo:load', function () { revealActiveTabs(); });
    document.addEventListener('focusin', function (e) {
        var item = e.target && e.target.closest ? e.target.closest('[data-underline-tabs] nav a, [data-underline-tabs] nav button') : null;
        if (!item) return;
        var nav = item.closest('nav');
        var rect = item.getBoundingClientRect();
        var navRect = nav.getBoundingClientRect();
        if (rect.left < navRect.left || rect.right > navRect.right) nav.scrollLeft += rect.left - navRect.left;
    });
    function handleTabActivation(e) {
        var btn = e.target && e.target.closest ? e.target.closest('button[data-tab-key]') : null;
        if (!btn) return;
        var bar = btn.closest('[data-underline-tabs]');
        if (!bar) return;
        bar.dataset.activeTab = btn.dataset.tabKey || '';
        if (btn.hasAttribute('aria-pressed')) {
            bar.querySelectorAll('button[aria-pressed]').forEach(function (item) {
                item.setAttribute('aria-pressed', item === btn ? 'true' : 'false');
                item.classList.toggle('border-navy', item === btn);
                item.classList.toggle('text-navy', item === btn);
                item.classList.toggle('border-transparent', item !== btn);
                item.classList.toggle('text-ink-muted', item !== btn);
            });
        }
        if (btn.getAttribute('role') === 'tab') {
            bar.querySelectorAll('button[role="tab"]').forEach(function (item) {
                var selected = item === btn;
                item.setAttribute('aria-selected', selected ? 'true' : 'false');
                item.tabIndex = selected ? 0 : -1;
            });
        }
        requestAnimationFrame(function () { revealActiveTabs(bar); });
    }
    document.addEventListener('click', handleTabActivation);
    document.addEventListener('keydown', function (e) {
        var pressed = e.target && e.target.closest ? e.target.closest('[data-underline-tabs] button[aria-pressed]') : null;
        if (pressed && ['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp', 'Home', 'End'].indexOf(e.key) >= 0) {
            var pressedButtons = Array.from(pressed.closest('[data-underline-tabs]').querySelectorAll('button[aria-pressed]'));
            if (!pressedButtons.length) return;
            var pressedIndex = pressedButtons.indexOf(pressed);
            var pressedNext = e.key === 'Home' ? 0 : e.key === 'End' ? pressedButtons.length - 1 : (pressedIndex + ((e.key === 'ArrowRight' || e.key === 'ArrowDown') ? 1 : -1) + pressedButtons.length) % pressedButtons.length;
            e.preventDefault();
            pressedButtons[pressedNext].focus();
            pressedButtons[pressedNext].click();
            return;
        }
        var tab = e.target && e.target.closest ? e.target.closest('[data-underline-tabs] button[role="tab"]') : null;
        if (!tab) return;
        var tablist = tab.closest('[role="tablist"]');
        if (!tablist) return;
        var tabs = Array.from(tablist.querySelectorAll('button[role="tab"]'));
        if (!tabs.length) return;
        var current = tabs.indexOf(tab);
        var next = null;
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') next = (current + 1) % tabs.length;
        else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') next = (current - 1 + tabs.length) % tabs.length;
        else if (e.key === 'Home') next = 0;
        else if (e.key === 'End') next = tabs.length - 1;
        if (next === null) return;
        e.preventDefault();
        tabs.forEach(function (item, index) { item.tabIndex = index === next ? 0 : -1; });
        tabs[next].focus();
        tabs[next].click();
    });
    document.addEventListener('turbo:frame-load', function (e) {
        if (!e.target) return;
        revealActiveTabs(e.target);
        syncTabStops(e.target);
        syncPressedButtons(e.target);
    });
    document.addEventListener('turbo:load', function () { syncTabStops(); syncPressedButtons(); revealActiveTabs(); });
})();
</script>
