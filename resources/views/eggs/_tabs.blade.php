<div class="border-b border-[#D9D9D9]">
    <nav class="-mb-px flex gap-6 overflow-x-auto overflow-y-hidden scrollbar-thin">
        @foreach([
            'logging'     => ['label' => 'Egg Logging', 'icon' => 'egg',         'route' => 'eggs.logging'],
            'recent-logs' => ['label' => 'Recent Logs', 'icon' => 'clipboard',   'route' => 'eggs.recent-logs'],
            'stocks'      => ['label' => 'Egg Stocks',  'icon' => 'package',     'route' => 'eggs.stocks'],
            'preorders'   => ['label' => 'Pre-Orders',  'icon' => 'shopping-bag', 'route' => 'eggs.preorders'],
            'history'     => ['label' => 'History',     'icon' => 'history',     'route' => 'egg-production-history'],
        ] as $key => $tab)
            @php
                $isActive = $key === $activeTab;
                $classes = 'pb-2 text-sm font-medium border-b-2 transition-colors shrink-0 whitespace-nowrap ' .
                    ($isActive ? 'border-[#002D5E] text-[#002D5E]' : 'border-transparent text-[#6B7280] hover:text-[#333]');
            @endphp
            <a href="{{ route($tab['route']) }}" class="{{ $classes }}" data-turbo-frame="egg-content">
                <i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4 inline mr-1"></i>
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>

    <script>
    (function() {
        var links = document.querySelectorAll('nav a[data-turbo-frame="egg-content"]');
        links.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                if (window.__eggActiveTab === this.getAttribute('href')) return;
                window.__eggActiveTab = this.getAttribute('href');

                var frame = document.querySelector('turbo-frame#egg-content');
                if (frame) {
                    frame.setAttribute('src', this.getAttribute('href'));
                }

                history.replaceState({}, '', this.getAttribute('href'));

                links.forEach(function(a) {
                    a.classList.remove('border-[#002D5E]', 'text-[#002D5E]');
                    a.classList.add('border-transparent', 'text-[#6B7280]', 'hover:text-[#333]');
                });
                this.classList.remove('border-transparent', 'text-[#6B7280]', 'hover:text-[#333]');
                this.classList.add('border-[#002D5E]', 'text-[#002D5E]');
            });
        });
    })();
    </script>
</div>
