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
        tab's key (id="egg-header-actions-{tabKey}") into #egg-header-actions.
    --}}
    <template id="egg-header-actions-stocks">
        <div class="flex items-center gap-2">
            <x-button variant="secondary" onclick="openEggWeightsModal()">
                <i data-lucide="weight" class="w-4 h-4"></i> Egg Weights
            </x-button>
            <x-button variant="secondary" onclick="openThresholdsModal()">
                <i data-lucide="sliders" class="w-4 h-4"></i> Thresholds
            </x-button>
            <x-button onclick="document.getElementById('addStockModal').style.display = 'flex'">
                <i data-lucide="plus" class="w-4 h-4"></i> Add Stock
            </x-button>
        </div>
    </template>
    <template id="egg-header-actions-preorders">
        <div class="flex items-center gap-2">
            <x-button onclick="document.getElementById('addOrderModal').style.display = 'flex'">
                <i data-lucide="plus" class="w-4 h-4"></i> Add Pre-Order
            </x-button>
        </div>
    </template>

    <script>
    (function() {
        var links = document.querySelectorAll('nav a[data-tab-key]');
        var frame = document.querySelector('turbo-frame#egg-content');

        links.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                if (window.__eggActiveTab === this.getAttribute('href')) return;
                window.__eggActiveTab = this.getAttribute('href');

                history.replaceState({}, '', this.getAttribute('href'));

                links.forEach(function(a) {
                    a.classList.remove('border-[#002D5E]', 'text-[#002D5E]');
                    a.classList.add('border-transparent', 'text-[#6B7280]', 'hover:text-[#333]');
                });
                this.classList.remove('border-transparent', 'text-[#6B7280]', 'hover:text-[#333]');
                this.classList.add('border-[#002D5E]', 'text-[#002D5E]');

                var subtitleEl = document.getElementById('egg-header-subtitle');
                if (subtitleEl) subtitleEl.textContent = this.dataset.subtitle;

                if (frame) {
                    frame.setAttribute('src', this.getAttribute('href'));
                }
            });
        });

        if (!window.__eggHeaderSyncBound) {
            window.__eggHeaderSyncBound = true;
            document.addEventListener('turbo:frame-load', function(e) {
                if (!e.target || e.target.id !== 'egg-content') return;

                var activeLink = null;
                document.querySelectorAll('nav a[data-tab-key]').forEach(function(a) {
                    if (a.pathname === window.location.pathname) activeLink = a;
                });
                if (!activeLink) return;

                var subtitleEl = document.getElementById('egg-header-subtitle');
                if (subtitleEl) subtitleEl.textContent = activeLink.dataset.subtitle;

                var actionsEl = document.getElementById('egg-header-actions');
                if (!actionsEl) return;
                var tpl = document.getElementById('egg-header-actions-' + activeLink.dataset.tabKey);
                actionsEl.innerHTML = '';
                if (tpl) {
                    actionsEl.appendChild(tpl.content.cloneNode(true));
                    if (typeof lucide !== 'undefined') lucide.createIcons({ els: actionsEl.querySelectorAll('[data-lucide]') });
                }
            });
        }
    })();
    </script>
</div>
