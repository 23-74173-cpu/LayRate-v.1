<div class="border-b border-[#D9D9D9]">
    <nav class="-mb-px flex gap-6 overflow-x-auto overflow-y-hidden scrollbar-thin">
        @foreach([
            'production-history' => ['label' => 'Production History', 'icon' => 'calendar-days', 'route' => 'eggs.production-history', 'subtitle' => 'Daily egg logs calendar'],
            'logging'     => ['label' => 'Egg Logging', 'icon' => 'egg',         'route' => 'eggs.logging',            'subtitle' => 'Log daily egg production per cage slot'],
            'recent-logs' => ['label' => 'Recent Logs', 'icon' => 'clipboard',   'route' => 'eggs.recent-logs',        'subtitle' => 'Review and manage egg production records'],
            'stocks'      => ['label' => 'Egg Stocks',  'icon' => 'package',     'route' => 'eggs.stocks',             'subtitle' => 'Track harvested egg inventory by size and freshness'],
            'preorders'        => ['label' => 'Pre-Orders',       'icon' => 'shopping-bag', 'route' => 'eggs.preorders',          'subtitle' => 'Customer orders and fulfillment tracking'],
            'history'          => ['label' => 'History',          'icon' => 'history',      'route' => 'egg-production-history',  'subtitle' => 'Full timeline of eggs logged since day 1'],
        ] as $key => $tab)
            @php
                $isActive = $key === $activeTab;
                $classes = 'pb-2 text-sm font-medium border-b-2 transition-colors shrink-0 whitespace-nowrap ' .
                    ($isActive ? 'border-navy text-navy' : 'border-transparent text-[#6B7280] hover:text-[#333]');
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
        tab's key (id="egg-fab-actions-{tabKey}") into the dock "+" menu
        (#dockFabMenu, rendered once in the global dock).
    --}}
    <template id="egg-fab-actions-stocks">
        {{-- Phones only, like the button on the Egg Stocks tab. --}}
        <button type="button" onclick="openEggQrScanner()"
                class="md:hidden flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Scan Egg QR</span>
            <div class="w-8 h-8 rounded-full bg-info-bg flex items-center justify-center">
                <i data-lucide="scan-qr-code" class="w-4 h-4 text-navy"></i>
            </div>
        </button>
        <button type="button" onclick="document.getElementById('eggLabelsModal').style.display = 'flex'"
                class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Print All QR Labels</span>
            <div class="w-8 h-8 rounded-full bg-info-bg flex items-center justify-center">
                <i data-lucide="printer" class="w-4 h-4 text-navy"></i>
            </div>
        </button>
        <button type="button" onclick="openEggWeightsModal()"
                class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Egg Weights</span>
            <div class="w-8 h-8 rounded-full bg-[#6B4C8A]/10 flex items-center justify-center">
                <i data-lucide="weight" class="w-4 h-4 text-navy"></i>
            </div>
        </button>
        <button type="button" onclick="openThresholdsModal()"
                class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Thresholds</span>
            <div class="w-8 h-8 rounded-full bg-[#C2703E]/10 flex items-center justify-center">
                <i data-lucide="sliders" class="w-4 h-4 text-navy"></i>
            </div>
        </button>
        <button type="button" onclick="document.getElementById('addStockModal').style.display = 'flex'"
                class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Add Stock</span>
            <div class="w-8 h-8 rounded-full bg-info-bg flex items-center justify-center">
                <i data-lucide="plus" class="w-4 h-4 text-navy"></i>
            </div>
        </button>
        @if(auth()->user()->isAdmin())
        <button type="button" onclick="openEggPricesModal()"
                class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Egg Prices</span>
            <div class="w-8 h-8 rounded-full bg-info-bg flex items-center justify-center">
                <i data-lucide="tag" class="w-4 h-4 text-navy"></i>
            </div>
        </button>
        @endif
    </template>
    <template id="egg-fab-actions-preorders">
        <button type="button" onclick="document.getElementById('addOrderModal').style.display = 'flex'"
                class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Add Pre-Order</span>
            <div class="w-8 h-8 rounded-full bg-info-bg flex items-center justify-center">
                <i data-lucide="plus" class="w-4 h-4 text-navy"></i>
            </div>
        </button>
        @if(auth()->user()->isAdmin())
        <button type="button" onclick="openEggPricesModal()"
                class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Egg Prices</span>
            <div class="w-8 h-8 rounded-full bg-info-bg flex items-center justify-center">
                <i data-lucide="tag" class="w-4 h-4 text-navy"></i>
            </div>
        </button>
        @endif
    </template>

    {{-- Egg Prices modal (admin-only entry via the dock). The form loads on
         demand through the frame so egg pages never pay for it unopened. --}}
    <div id="eggPricesModal" data-modal data-close="closeEggPricesModal" style="display: none;" class="fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEggPricesModal()"></div>
        <div class="relative w-full max-w-md rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-1">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Egg Prices</h2>
                <button type="button" onclick="closeEggPricesModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>
            <p class="text-xs text-[#6B7280] mb-4">Per-tray (30 eggs) and per-piece selling prices in pesos. Typing one fills in the other — clear a field to leave it unset.</p>
            <turbo-frame id="eggPricesFrame" target="_top">
                <p class="text-xs" style="color: #a39e98;">Loading prices…</p>
            </turbo-frame>
        </div>
    </div>
    <script>
    function openEggPricesModal() {
        document.getElementById('eggPricesFrame').src = '{{ route('settings.egg-prices.index') }}';
        document.getElementById('eggPricesModal').style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
    function closeEggPricesModal() {
        document.getElementById('eggPricesModal').style.display = 'none';
    }
    </script>

    <script>
    (function() {
        var links = document.querySelectorAll('nav a[data-tab-key]');
        var MENU_ID = 'dockFabMenu';

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
                a.classList.remove('border-navy', 'text-navy');
                a.classList.add('border-transparent', 'text-[#6B7280]', 'hover:text-[#333]');
            });
            activeLink.classList.remove('border-transparent', 'text-[#6B7280]', 'hover:text-[#333]');
            activeLink.classList.add('border-navy', 'text-navy');

            var subtitleEl = document.getElementById('egg-header-subtitle');
            if (subtitleEl) subtitleEl.textContent = activeLink.dataset.subtitle;

            var menu = document.getElementById(MENU_ID);
            if (!menu) {
                // Full page load: this script runs before the global dock at
                // the end of <body> exists, which left the "+" menu empty (and
                // hidden) after a refresh. Fill it once the page is parsed.
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', syncActive, { once: true });
                }
                return;
            }
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
                    a.classList.remove('border-navy', 'text-navy');
                    a.classList.add('border-transparent', 'text-[#6B7280]', 'hover:text-[#333]');
                });
                this.classList.remove('border-transparent', 'text-[#6B7280]', 'hover:text-[#333]');
                this.classList.add('border-navy', 'text-navy');

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

    Only the tabs that actually give the FAB menu actions populate it (stocks,
    pre-orders); the client-side tab sync hides the FAB on every other tab. It is
    therefore ALWAYS rendered here: the tabs bar + FAB live outside the
    turbo-frame#egg-content, so a `@if($activeTab == ...)` guard would only ever
    include it when the initially loaded page was stocks/pre-orders. Navigating
    to those tabs from any other egg page swaps only the frame, leaving the FAB
    (and its create buttons) permanently absent. Rendering it unconditionally and
    letting syncActive() hide it where no action template exists keeps the create
    buttons available no matter which tab you start on. The "+" toggle itself
    now lives in the global dock; syncActive() below targets #dockFabMenu.
--}}
