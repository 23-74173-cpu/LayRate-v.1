<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'LayRate' }} — LayRate Farm Monitor</title>

    {{-- Favicons --}}
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

    {{-- Inter Font (Notion typeface) --}}
    <link rel="preconnect" href="https://rsms.me/">
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">

    {{-- Tailwind CSS CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Chart.js CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    {{-- Lucide Icons CDN --}}
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'canvas-soft': '#f6f5f4',
                        'hairline': '#e6e6e6',
                        'ink': { DEFAULT: '#1f1f1f', secondary: '#31302e', muted: '#615d59', faint: '#a39e98' },
                        'primary': { DEFAULT: '#0075de', active: '#005bab' },
                        'sidebar-bg': '#1a2342',
                        'ok': { bg: '#e8f5ec', text: '#1f6b3a', border: '#cfe8d6' },
                        'watch': { bg: '#fdf3e0', text: '#8a5a00', border: '#f3e3bf' },
                        'alert': { bg: '#fbe4e6', text: '#9b1c24', border: '#f3cdd0' },
                        'cage-a': { DEFAULT: '#2a9d6a', soft: '#d6f0e3' },
                        'cage-b': { DEFAULT: '#3b7bd9', soft: '#dcebfa' },
                        'cage-c': { DEFAULT: '#d97a3e', soft: '#fae3d0' },
                        'cage-d': { DEFAULT: '#8a6bbf', soft: '#e9e0f5' },
                    },
                    fontFamily: {
                        sans: ['Inter', '-apple-system', 'system-ui', '"Segoe UI"', 'Helvetica', 'Arial', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        body { background-color: #f6f5f4; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
        .nav-active { background: rgba(255,255,255,.2); box-shadow: inset 0 0 0 1px rgba(255,255,255,.25); }
        .scrollbar-thin::-webkit-scrollbar { width: 4px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #D9D9D9; border-radius: 9999px; }
        [x-cloak] { display: none !important; }

        /* ── Focus-visible (keyboard accessibility) ── */
        :focus-visible {
            outline: 2px solid #0075de;
            outline-offset: 2px;
            border-radius: 4px;
        }

        /* Sidebar collapsed state */
        #sidebar.collapsed { width: 4rem; }
        #sidebar.collapsed .sidebar-label { display: none; }
        #sidebar.collapsed a {
            width: 2.75rem !important;
            height: 2.75rem !important;
            justify-content: center !important;
            margin-left: auto !important;
            margin-right: auto !important;
        }
        #sidebar.collapsed .logo-wrap {
            justify-content: center !important;
        }

        /* Sidebar expanded state */
        #sidebar { width: 13rem; }
        #sidebar a {
            width: 100%;
            padding: 0.625rem 0.625rem;
            height: auto;
            margin-left: 0;
            margin-right: 0;
        }
    </style>

    {{-- Inline script to restore sidebar state before first paint --}}
    <script>
        (function() {
            var stored  = localStorage.getItem('sidebar_expanded');
            var expanded = stored === null ? true : stored === 'true';
            if (!expanded) {
                var sidebar = document.getElementById('sidebar');
                if (sidebar) sidebar.classList.add('collapsed');
            }
        })();
    </script>

    @stack('head')
</head>
<body class="flex h-screen overflow-hidden">

{{-- ─── Sidebar ──────────────────────────────────────────────────────────── --}}
<aside id="sidebar" class="bg-sidebar-bg flex flex-col py-3 shrink-0 justify-between transition-all duration-200 ease-in-out overflow-hidden">

    {{-- Logo --}}
    <div class="flex flex-col gap-1 items-center">
        <div class="logo-wrap flex items-center justify-center mb-4 px-2 w-full">
            <div class="w-9 h-9 rounded-lg bg-[#1F4B7D] flex items-center justify-center shrink-0 border border-white/25">
                <i data-lucide="feather" class="w-5 h-5 text-white"></i>
            </div>
            <div class="sidebar-label ml-2.5 overflow-hidden whitespace-nowrap">
                <div class="text-white text-sm font-semibold">LayRate</div>
                <div class="text-white/75 text-[10px]">Farm Monitor</div>
            </div>
        </div>

        {{-- Nav items --}}
        @php
        $nav = [
            ['icon'=>'home',          'label'=>'Dashboard',       'route'=>'dashboard'],
            ['icon'=>'feather',       'label'=>'Cages',           'route'=>'cages.index'],
            ['icon'=>'bird',          'label'=>'Chickens',        'route'=>'chickens.index'],
            ['icon'=>'egg',           'label'=>'Egg Logging',     'route'=>'egg-logging'],
            ['icon'=>'thermometer',   'label'=>'Environment',     'route'=>'environment'],
            ['icon'=>'leaf',          'label'=>'Feed & Nutrition','route'=>'feed'],
            ['icon'=>'bar-chart-3',   'label'=>'Analytics',       'route'=>'analytics'],
            ['icon'=>'trending-up',   'label'=>'Forecast',        'route'=>'forecast'],
            ['icon'=>'clipboard-list','label'=>'Reports',         'route'=>'reports'],
        ];
        @endphp

        @foreach($nav as $item)
        @php $active = request()->routeIs($item['route']); @endphp
        <a href="{{ route($item['route']) }}"
           class="group flex items-center gap-2.5 rounded-lg transition-colors
                  {{ $active ? 'nav-active text-white' : 'text-white/85 hover:text-white hover:bg-white/10' }}"
           title="{{ $item['label'] }}"
           aria-label="{{ $item['label'] }}">
            <i data-lucide="{{ $item['icon'] }}" class="w-[19px] h-[19px] shrink-0 transition-transform duration-200 group-hover:-translate-y-0.5 group-hover:scale-110"></i>
            <span class="sidebar-label text-sm font-medium whitespace-nowrap overflow-hidden">{{ $item['label'] }}</span>
        </a>
        @endforeach
    </div>

    {{-- Bottom items --}}
    <div class="flex flex-col gap-1 items-center">
        <a href="{{ route('account') }}" class="group flex items-center gap-2.5 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors" title="Settings" aria-label="Settings">
            <i data-lucide="settings" class="w-[19px] h-[19px] shrink-0 transition-transform duration-200 group-hover:-translate-y-0.5 group-hover:scale-110"></i>
            <span class="sidebar-label text-sm font-medium whitespace-nowrap overflow-hidden">Settings</span>
        </a>
        <a href="#" class="group flex items-center gap-2.5 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors" title="Profile" aria-label="Profile">
            <i data-lucide="user" class="w-[19px] h-[19px] shrink-0 transition-transform duration-200 group-hover:-translate-y-0.5 group-hover:scale-110"></i>
            <span class="sidebar-label text-sm font-medium whitespace-nowrap overflow-hidden">Profile</span>
        </a>
    </div>
</aside>

{{-- ─── Main Area ────────────────────────────────────────────────────────── --}}
<div class="flex flex-col flex-1 min-w-0 overflow-hidden">

    {{-- Header --}}
    <header class="bg-[#1A2E1A] h-11 flex items-center justify-between px-4 shrink-0">
        <div class="flex items-center gap-3">
            <button id="sidebarToggle" class="text-white/70 hover:text-white transition-colors p-1 rounded" aria-label="Toggle sidebar">
                <i data-lucide="menu" class="w-4 h-4"></i>
            </button>
            <nav class="text-xs text-white/60">
                <a href="{{ route('dashboard') }}" class="hover:text-white/90 transition-colors">Home</a>
                <span class="mx-1">/</span>
                <span class="text-white">{{ $title ?? 'Dashboard' }}</span>
            </nav>
        </div>

        <div class="flex items-center gap-3">
            <span class="flex items-center gap-1.5 text-[11px] px-2.5 py-1 rounded-full bg-emerald-900/60 text-emerald-300 border border-emerald-600/40">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Offline · Local Network
            </span>
            <a href="#" class="relative text-white/70 hover:text-white transition-colors" aria-label="Notifications">
                <i data-lucide="bell" class="w-4 h-4"></i>
                @php $alertCount = \App\Models\Alert::where('is_read',0)->count(); @endphp
                @if($alertCount > 0)
                <span class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-red-500 text-white text-[8px] rounded-full flex items-center justify-center font-bold">{{ $alertCount }}</span>
                @endif
            </a>
            <div class="flex items-center gap-2 pl-2 border-l border-white/20">
                <div class="text-right hidden sm:block">
                    <div class="text-[11px] text-white/90 leading-tight">{{ auth()->user()->name }}</div>
                    <div class="text-[9px] text-white/50 uppercase tracking-wider">{{ auth()->user()->role }}</div>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" title="Sign out" aria-label="Sign out"
                            class="text-white/60 hover:text-white transition-colors p-1 rounded">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="mx-4 mt-3 flex items-center gap-2 bg-green-50 border border-green-200 text-green-800 px-4 py-2.5 rounded-lg text-sm">
        <i data-lucide="check-circle" class="w-4 h-4 text-green-600 shrink-0"></i>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mx-4 mt-3 flex items-center gap-2 bg-red-50 border border-red-200 text-red-800 px-4 py-2.5 rounded-lg text-sm">
        <i data-lucide="alert-triangle" class="w-4 h-4 text-red-600 shrink-0"></i>
        {{ session('error') }}
    </div>
    @endif

    {{-- Page content --}}
    <div class="flex-1 overflow-auto scrollbar-thin">
        @yield('content')
    </div>
</div>

{{-- ─── Scripts ──────────────────────────────────────────────────────────── --}}
<script>
lucide.createIcons();

// ── Chart.js global defaults (colorblind-safe palette + Notion typography) ──
if (typeof Chart !== 'undefined') {
    Chart.defaults.color = '#31302e';
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.plugins.legend.labels.font.size = 12;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.pointStyle = 'circle';
    Chart.defaults.plugins.legend.labels.padding = 16;
    Chart.defaults.elements.bar.borderRadius = 4;
    Chart.defaults.scale.grid = { color: 'rgba(0,0,0,0.06)' };
    window.CAGE_COLORS = {
        'CAGE-A': '#1B8A3E', 'CAGE-B': '#2563EB', 'CAGE-C': '#EA580C', 'CAGE-D': '#7C3AED'
    };
}

// ── Sidebar expand/collapse with localStorage persistence ──────────────────
(function() {
    var sidebar = document.getElementById('sidebar');
    var toggle  = document.getElementById('sidebarToggle');
    var STORAGE_KEY = 'sidebar_expanded';

    function applyState(expanded) {
        if (expanded) {
            sidebar.classList.remove('collapsed');
        } else {
            sidebar.classList.add('collapsed');
        }
    }

    var stored = localStorage.getItem(STORAGE_KEY);
    applyState(stored === null ? true : stored === 'true');

    toggle.addEventListener('click', function() {
        var isCollapsed = sidebar.classList.contains('collapsed');
        var newState    = !isCollapsed;
        localStorage.setItem(STORAGE_KEY, String(newState));
        applyState(newState);
    });
})();
</script>

@stack('scripts')
</body>
</html>
