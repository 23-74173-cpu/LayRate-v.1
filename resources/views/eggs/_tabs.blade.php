<div class="border-b border-[#D9D9D9]">
    <nav class="-mb-px flex gap-6 overflow-x-auto overflow-y-hidden scrollbar-thin">
        @foreach([
            'logging'     => ['label' => 'Egg Logging', 'icon' => 'egg',         'route' => 'eggs.logging',            'subtitle' => 'Log daily egg production per cage slot'],
            'recent-logs' => ['label' => 'Recent Logs', 'icon' => 'clipboard',   'route' => 'eggs.recent-logs',        'subtitle' => 'Review and manage egg production records'],
            'stocks'      => ['label' => 'Egg Stocks',  'icon' => 'package',     'route' => 'eggs.stocks',             'subtitle' => 'Track harvested egg inventory by size and freshness'],
            'preorders'   => ['label' => 'Pre-Orders',  'icon' => 'shopping-bag', 'route' => 'eggs.preorders',          'subtitle' => 'Customer orders and fulfillment tracking'],
            'history'     => ['label' => 'History',     'icon' => 'history',     'route' => 'egg-production-history',  'subtitle' => 'Full timeline of eggs logged since day 1'],
        ] as $key => $tab)
            @php
                $isActive = $key === $activeTab;
                $classes = 'pb-2 text-sm font-medium border-b-2 transition-colors shrink-0 whitespace-nowrap ' .
                    ($isActive ? 'border-[#002D5E] text-[#002D5E]' : 'border-transparent text-[#6B7280] hover:text-[#333]');
            @endphp
            <a href="{{ route($tab['route']) }}" class="{{ $classes }}"
               data-turbo-frame="egg-content"
               data-turbo-prefetch
               data-tab-key="{{ $key }}" data-subtitle="{{ $tab['subtitle'] }}">
                <i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4 inline mr-1"></i>
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>

    {{--
        Header sync payloads for tabs that need page-header actions. Kept as
        hidden templates, outside the frame, so they survive every frame swap
        untouched. The sync script below clones the one matching the active
        tab's key (id="egg-fab-actions-{tabKey}") into the FAB menu
        (#egg-fab-menu).
    --}}
    <template id="egg-fab-actions-stocks">
        <button type="button" onclick="openEggWeightsModal()"
                class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Egg Weights</span>
            <div class="w-8 h-8 rounded-full bg-[#6B4C8A]/10 flex items-center justify-center">
                <i data-lucide="weight" class="w-4 h-4 text-[#6B4C8A]"></i>
            </div>
        </button>
        <button type="button" onclick="openThresholdsModal()"
                class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Thresholds</span>
            <div class="w-8 h-8 rounded-full bg-[#C2703E]/10 flex items-center justify-center">
                <i data-lucide="sliders" class="w-4 h-4 text-[#C2703E]"></i>
            </div>
        </button>
        <button type="button" onclick="document.getElementById('addStockModal').style.display = 'flex'"
                class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Add Stock</span>
            <div class="w-8 h-8 rounded-full bg-[#002D5E]/10 flex items-center justify-center">
                <i data-lucide="plus" class="w-4 h-4 text-[#002D5E]"></i>
            </div>
        </button>
    </template>
    <template id="egg-fab-actions-preorders">
        <button type="button" onclick="document.getElementById('addOrderModal').style.display = 'flex'"
                class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Add Pre-Order</span>
            <div class="w-8 h-8 rounded-full bg-[#002D5E]/10 flex items-center justify-center">
                <i data-lucide="plus" class="w-4 h-4 text-[#002D5E]"></i>
            </div>
        </button>
    </template>

    <script>
    (function() {
        var links = document.querySelectorAll('nav a[data-tab-key]');
        var MENU_ID = 'egg-fab-menu';

        function syncActive() {
            var activeLink = null;
            document.querySelectorAll('nav a[data-tab-key]').forEach(function(a) {
                if (a.pathname === window.location.pathname) activeLink = a;
            });
            if (!activeLink) return;

            // Active-tab highlight must always match the current URL. It is
            // derived here (on every load/frame-load) instead of being set by a
            // persistent window flag in the click handler, so returning to the
            // section via the sidebar or the Back button restores it correctly.
            links.forEach(function(a) {
                a.classList.remove('border-[#002D5E]', 'text-[#002D5E]');
                a.classList.add('border-transparent', 'text-[#6B7280]', 'hover:text-[#333]');
            });
            activeLink.classList.remove('border-transparent', 'text-[#6B7280]', 'hover:text-[#333]');
            activeLink.classList.add('border-[#002D5E]', 'text-[#002D5E]');

            var subtitleEl = document.getElementById('egg-header-subtitle');
            if (subtitleEl) subtitleEl.textContent = activeLink.dataset.subtitle;

            var menu = document.getElementById(MENU_ID);
            if (!menu) return;
            var fab = menu.closest('.fab');
            var tpl = document.getElementById('egg-fab-actions-' + activeLink.dataset.tabKey);
            if (!tpl) {
                if (fab) fab.style.display = 'none';
                return;
            }
            if (fab) fab.style.display = '';
            menu.innerHTML = '';
            menu.appendChild(tpl.content.cloneNode(true));
            if (typeof lucide !== 'undefined') lucide.createIcons({ els: menu.querySelectorAll('[data-lucide]') });
        }

        // NOTE: no preventDefault() / persistent flag here. A window-level
        // __eggActiveTab flag survives Turbo visits, so a later click on the
        // same tab (after returning via the sidebar or the Back button) was
        // swallowed by preventDefault and the page never loaded. The
        // history.replaceState keeps the address bar in sync (Turbo frame
        // visits in this app don't update the URL themselves); Turbo drives
        // the frame content swap.
        links.forEach(function(link) {
            link.addEventListener('click', function(e) {
                links.forEach(function(a) {
                    a.classList.remove('border-[#002D5E]', 'text-[#002D5E]');
                    a.classList.add('border-transparent', 'text-[#6B7280]', 'hover:text-[#333]');
                });
                this.classList.remove('border-transparent', 'text-[#6B7280]', 'hover:text-[#333]');
                this.classList.add('border-[#002D5E]', 'text-[#002D5E]');

                history.replaceState({}, '', this.getAttribute('href'));
            });
        });

        // Populate the FAB menu for the initially-rendered tab (the server-side
        // frame content doesn't fire turbo:frame-load on the first paint).
        syncActive();

        if (!window.__eggHeaderSyncBound) {
            window.__eggHeaderSyncBound = true;
            document.addEventListener('turbo:frame-load', function(e) {
                if (!e.target || e.target.id !== 'egg-content') return;
                syncActive();
            });
        }
    })();
    </script>
</div>

{{--
    Floating Action Button shared by all Egg Management pages. The menu is
    populated at runtime (see the sync script above) from the hidden
    templates. Rendered OUTSIDE the tabs wrapper (as a direct child of the
    page's space-y-5, matching the Forecast FAB) because the global entrance
    animation leaves a retained transform on its space-y-5 children — fill-mode
    both keeps translateY(0), which creates a fixed-position containing block.
    Inside that, the FAB is pinned to the tabs row instead of the viewport's
    bottom-right corner. It sits outside the #egg-content frame, so it survives
    every frame swap untouched.

    Only the tabs that actually give the FAB menu actions render it (stocks,
    pre-orders); on the rest the menu would be empty, so the FAB is omitted and
    the client-side tab sync hides it if one is ever reached via navigation.
--}}
@if(in_array($activeTab ?? '', ['stocks', 'preorders']))
    <x-fab menu-id="egg-fab-menu"></x-fab>
@endif
