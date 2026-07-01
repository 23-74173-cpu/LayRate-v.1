<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#1a2342">
    <link rel="manifest" href="/manifest.json">
    <title>{{ $title ?? 'LayRate' }} — LayRate Farm Monitor</title>

    {{-- Favicons --}}
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

    {{-- Inter Font (self-hosted) --}}
    <link rel="stylesheet" href="{{ asset('css/inter.css') }}">

    {{-- Tailwind CSS (compiled) --}}
    <link href="{{ asset('css/tailwind.css') }}" rel="stylesheet">

    {{-- Prevent white flash while styles load --}}
    <style>
      html { background-color: #F5F6F8; }
      body { background-color: #F5F6F8; }
    </style>

    {{-- Chart.js --}}
    <script src="{{ asset('js/chart.min.js') }}" defer></script>

    {{-- Lucide Icons --}}
    <script src="{{ asset('js/lucide.min.js') }}" defer></script>

    {{-- Turbo Drive --}}
    <script type="module" src="{{ asset('js/turbo.js') }}"></script>

    <style>
        * { -webkit-tap-highlight-color: transparent; }
        html { height: 100%; height: -webkit-fill-available; }
        body { background-color: #F5F6F8; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; height: 100%; height: -webkit-fill-available; overflow: hidden; }
        .nav-active { background: rgba(255,255,255,.2); box-shadow: inset 0 0 0 1px rgba(255,255,255,.25); }
        .scrollbar-thin::-webkit-scrollbar { width: 4px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #D9D9D9; border-radius: 9999px; }
        [x-cloak] { display: none !important; }

        :focus-visible {
            outline: 2px solid #0075de;
            outline-offset: 2px;
            border-radius: 4px;
        }

        /* ── Desktop collapsed state: hide labels, center icons ── */
        @media (min-width: 1024px) {
            #sidebar.w-16 .sidebar-label,
            #sidebar.w-16 .logo-text,
            .sidebar-collapsed #sidebar .sidebar-label,
            .sidebar-collapsed #sidebar .logo-text { display: none !important; }
            #sidebar.w-16 .nav-link,
            .sidebar-collapsed #sidebar .nav-link {
                justify-content: center !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            #sidebar.w-16 .logo-wrap,
            .sidebar-collapsed #sidebar .logo-wrap { justify-content: center !important; }

            /* Apply collapsed width synchronously before paint (Turbo-safe replacement for document.write) */
            html.sidebar-collapsed #sidebar { width: 4rem !important; }
        }

        /* ─ Allow text selection in form inputs ── */
        input, textarea, select {
            user-select: text !important;
            -webkit-user-select: text !important;
        }

        /* ─ Turbo page transition ── */
        @keyframes turboFade {
            from { opacity: 0; transform: translateY(3px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .turbo-loaded { animation: turboFade 120ms ease-out; }
    </style>

    @stack('head')
</head>
<body class="h-screen overflow-hidden bg-[#F5F6F8] select-none">

{{-- ── Turbo navigation loading bar ─────────────────────────────────────── --}}
<style>
#turbo-loading-bar {
    position: fixed; top: 0; left: 0; height: 3px;
    background: linear-gradient(90deg, #0075de, #62aef0);
    z-index: 9999; pointer-events: none;
    width: 0%; opacity: 0;
    transition: width 0.4s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.3s;
}
#turbo-loading-bar.active { opacity: 1; }
</style>
<div id="turbo-loading-bar"></div>

{{-- ── Turbo loading bar control ─────────────────────────────────────────── --}}
<script>
(function() {
    var bar = document.getElementById('turbo-loading-bar');
    if (!bar) return;
    var timer;

    document.addEventListener('turbo:before-visit', function() {
        clearTimeout(timer);
        bar.style.width = '0%';
        bar.classList.add('active');
        requestAnimationFrame(function() {
            bar.style.transition = 'width 3s cubic-bezier(0.22, 1, 0.36, 1)';
            bar.style.width = '85%';
        });
    });

    document.addEventListener('turbo:load', function() {
        clearTimeout(timer);
        bar.style.transition = 'width 0.3s ease-out';
        bar.style.width = '100%';
        timer = setTimeout(function() {
            bar.classList.remove('active');
            bar.style.transition = 'none';
            bar.style.width = '0%';
        }, 400);
    });
})();
</script>

{{-- ── Inline script: restore sidebar collapsed state BEFORE paint ──────── --}}
<script>
(function() {
    if (window.innerWidth >= 1024) {
        var stored = localStorage.getItem('sidebar_state');
        if (stored === 'collapsed') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    }
})();
</script>

{{-- ─── ROOT FLEX ROW ───────────────────────────────────────────────────── --}}
<div class="flex overflow-hidden" style="height: 100vh; height: -webkit-fill-available; height: 100dvh;">

    {{-- ─── SIDEBAR ─────────────────────────────────────────────────────── --}}
    <aside id="sidebar" data-turbo-permanent
           class="flex flex-col bg-sidebar-bg text-white
                  transition-all duration-300 ease-in-out
                  flex-shrink-0
                  fixed lg:relative lg:translate-x-0
                  -translate-x-full z-40 w-64"
           style="width: 16rem; height: 100vh; height: -webkit-fill-available; height: 100dvh;">

        {{-- TOP: Brand + Arrow (mobile close / desktop unused) --}}
        <div class="flex items-center justify-between px-4 pt-3 pb-1 shrink-0">
            <div class="logo-wrap flex items-center gap-2.5 overflow-hidden">
                <div class="w-9 h-9 rounded-lg bg-[#1F4B7D] flex items-center justify-center shrink-0 border border-white/25">
                    <i data-lucide="feather" class="w-5 h-5 text-white"></i>
                </div>
                <div class="logo-text overflow-hidden whitespace-nowrap">
                    <div class="text-white text-sm font-semibold">LayRate</div>
                    <div class="text-white/75 text-xs">Farm Monitor</div>
                </div>
            </div>
            {{-- Arrow button: mobile drawer close only (hidden on desktop) --}}
            <button id="sidebar-arrow" class="lg:hidden text-white/50 hover:text-white transition-colors p-1 rounded shrink-0" aria-label="Close menu">
                <i data-lucide="chevron-left" class="w-5 h-5"></i>
            </button>
        </div>

        {{-- MIDDLE: Main nav (scrollable) --}}
        <nav class="flex-1 overflow-y-auto py-4 px-3 scrollbar-thin">
            @php
            $nav = [
                ['icon'=>'home',          'label'=>'Dashboard',       'route'=>'dashboard'],
                ['icon'=>'feather',       'label'=>'Cages',           'route'=>'cages.index'],
                ['icon'=>'bird',          'label'=>'Chickens',        'route'=>'chickens.index'],
                ['icon'=>'egg',           'label'=>'Egg Management',  'route'=>'eggs.logging'],
                ['icon'=>'thermometer',   'label'=>'Environment',     'route'=>'environment'],
                ['icon'=>'cpu',           'label'=>'Hardware',        'route'=>'hardware.index'],
                ['icon'=>'leaf',          'label'=>'Feed & Nutrition','route'=>'feed'],
                ['icon'=>'bar-chart-3',   'label'=>'Analytics',       'route'=>'analytics'],
                ['icon'=>'trending-up',   'label'=>'Forecast',        'route'=>'forecast'],
                ['icon'=>'clipboard-list','label'=>'Reports',         'route'=>'reports'],
            ];
            @endphp

            @foreach($nav as $item)
            <a href="{{ route($item['route']) }}"
               data-route="{{ $item['route'] === 'dashboard' ? 'dashboard' : explode('.', $item['route'])[0] }}"
               class="nav-link group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-white/85 hover:text-white hover:bg-white/10"
               title="{{ $item['label'] }}"
               aria-label="{{ $item['label'] }}">
                <i data-lucide="{{ $item['icon'] }}" class="w-[19px] h-[19px] shrink-0 transition-transform duration-200 group-hover:-translate-y-0.5 group-hover:scale-110"></i>
                <span class="sidebar-label text-sm font-medium whitespace-nowrap overflow-hidden">{{ $item['label'] }}</span>
            </a>
            @endforeach
        </nav>

        {{-- BOTTOM: Pinned footer nav --}}
        <div class="border-t border-white/10 py-4 px-3 shrink-0">
            <a href="{{ route('notifications.index') }}"
               data-route="notifications"
               class="nav-link group flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors"
               title="Notifications" aria-label="Notifications">
                <i data-lucide="bell" class="w-[19px] h-[19px] shrink-0 transition-transform duration-200 group-hover:-translate-y-0.5 group-hover:scale-110"></i>
                <span class="sidebar-label text-sm font-medium whitespace-nowrap overflow-hidden">Notifications</span>
                @if($globalAlertCount > 0)
                <span class="ml-auto bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[1.25rem] text-center">{{ $globalAlertCount }}</span>
                @endif
            </a>
            <a href="{{ route('account') }}"
               data-route="settings"
               class="nav-link group flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors"
               title="Settings" aria-label="Settings">
                <i data-lucide="settings" class="w-[19px] h-[19px] shrink-0 transition-transform duration-200 group-hover:-translate-y-0.5 group-hover:scale-110"></i>
                <span class="sidebar-label text-sm font-medium whitespace-nowrap overflow-hidden">Settings</span>
            </a>
            <a href="#"
               data-route="profile"
               class="nav-link group flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors"
               title="Profile" aria-label="Profile">
                <i data-lucide="user" class="w-[19px] h-[19px] shrink-0 transition-transform duration-200 group-hover:-translate-y-0.5 group-hover:scale-110"></i>
                <span class="sidebar-label text-sm font-medium whitespace-nowrap overflow-hidden">Profile</span>
            </a>
        </div>
    </aside>

    {{-- ─── MOBILE BACKDROP ──────────────────────────────────────────────── --}}
    <div id="sidebar-backdrop" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden"></div>

    {{-- ─── MAIN COLUMN ──────────────────────────────────────────────────── --}}
    <div class="flex flex-col flex-1 overflow-hidden">

        {{-- TOP HEADER BAR --}}
        <header id="main-header" data-turbo-permanent class="flex-shrink-0 bg-[#1A2E1A] h-12 flex items-center justify-between px-4">
            <div class="flex items-center gap-3">
                {{-- Hamburger: visible on ALL screen sizes --}}
                <button id="sidebar-toggle" class="mr-2 text-white/70 hover:text-white transition-colors p-1 rounded" aria-label="Toggle sidebar">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <nav class="text-xs text-white/60">
                    <a href="{{ route('dashboard') }}" class="hover:text-white/90 transition-colors">Home</a>
                    <span class="mx-1">/</span>
                    <span class="text-white">{{ $title ?? 'Dashboard' }}</span>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                <span class="hidden sm:flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-emerald-900/60 text-emerald-300 border border-emerald-600/40">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Offline · Local Network
                </span>
                <a href="{{ route('notifications.index') }}" class="relative text-white/70 hover:text-white transition-colors" aria-label="Notifications">
                    <i data-lucide="bell" class="w-4 h-4"></i>
                    @if($globalAlertCount > 0)
                    <span class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-red-500 text-white text-[8px] rounded-full flex items-center justify-center font-bold">{{ $globalAlertCount }}</span>
                    @endif
                </a>
                <div class="flex items-center gap-2 pl-2 border-l border-white/20">
                    <div class="text-right hidden sm:block">
                        <div class="text-xs text-white/90 leading-tight">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-white/50 uppercase tracking-wider">{{ auth()->user()->role }}</div>
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

        {{-- SCROLLABLE PAGE CONTENT --}}
        <main class="page-wrapper flex-1 overflow-y-auto px-3 sm:px-4 lg:px-6 py-4 scrollbar-thin">
            @yield('content')
        </main>
    </div>
</div>

{{-- Session-aware alert modal --}}
@if($showAlertsModal)
    <x-alerts-modal :alerts="$globalNewAlerts" />
@endif

{{-- ─── Scripts ─────────────────────────────────────────────────────────── --}}
<script>
// ── Fullscreen for captive portal / mobile browsers ──
// (Disabled for now)
/*
(function() {
    function goFullscreen() {
        var el = document.documentElement;
        if (el.requestFullscreen) {
            el.requestFullscreen().catch(function() {});
        } else if (el.webkitRequestFullscreen) {
            el.webkitRequestFullscreen();
        } else if (el.msRequestFullscreen) {
            el.msRequestFullscreen();
        }
        // Hide address bar on mobile
        window.scrollTo(0, 1);
    }

    // Try on first user interaction (browsers require gesture for fullscreen)
    var tried = false;
    function tryOnce() {
        if (!tried) {
            tried = true;
            goFullscreen();
        }
    }
    document.addEventListener('touchstart', tryOnce, { once: true });
    document.addEventListener('click', tryOnce, { once: true });

    // Also try on load (works in some captive portal browsers)
    if (document.readyState === 'complete') {
        goFullscreen();
    } else {
        window.addEventListener('load', goFullscreen);
    }
})();
*/

(function() {
    var SIDEBAR_INITIALIZED = false;

    document.addEventListener('turbo:load', function() {
        var sidebar  = document.getElementById('sidebar');
        var toggleBtn = document.getElementById('sidebar-toggle');
        var arrowBtn = document.getElementById('sidebar-arrow');
        var backdrop = document.getElementById('sidebar-backdrop');
        var navLinks = sidebar.querySelectorAll('.nav-link');
        var STORAGE_KEY = 'sidebar_state';

        // ── Page transition animation ──
        var mainContent = document.querySelector('.page-wrapper');
        if (mainContent) {
            mainContent.classList.remove('turbo-loaded');
            void mainContent.offsetWidth;
            mainContent.classList.add('turbo-loaded');
        }

        // ── Desktop collapse toggle ──
        function toggleDesktop() {
            var sb = document.getElementById('sidebar');
            if (!sb) return;
            var isCollapsed = sb.classList.contains('w-16');
            if (isCollapsed) {
                sb.classList.remove('w-16');
                sb.classList.add('w-64');
                sb.style.setProperty('width', '16rem', 'important');
                document.documentElement.classList.remove('sidebar-collapsed');
                localStorage.setItem(STORAGE_KEY, 'expanded');
            } else {
                sb.classList.remove('w-64');
                sb.classList.add('w-16');
                sb.style.setProperty('width', '4rem', 'important');
                document.documentElement.classList.add('sidebar-collapsed');
                localStorage.setItem(STORAGE_KEY, 'collapsed');
            }
        }

        // Apply initial state from localStorage (desktop only — mobile drawer always starts full-width)
        function applySidebarState() {
            var sb = document.getElementById('sidebar');
            if (!sb) return;
            if (window.innerWidth >= 1024) {
                var stored = localStorage.getItem(STORAGE_KEY);
                if (stored === 'collapsed') {
                    sb.classList.remove('w-64');
                    sb.classList.add('w-16');
                    sb.style.setProperty('width', '4rem', 'important');
                    document.documentElement.classList.add('sidebar-collapsed');
                } else {
                    sb.classList.remove('w-16');
                    sb.classList.add('w-64');
                    sb.style.setProperty('width', '16rem', 'important');
                    document.documentElement.classList.remove('sidebar-collapsed');
                }
            } else {
                // Mobile: ensure drawer starts full-width and off-screen
                sb.classList.remove('w-16');
                sb.classList.add('w-64');
                sb.style.removeProperty('width');
                document.documentElement.classList.remove('sidebar-collapsed');
            }
        }
        applySidebarState();

        // ── Only bind sidebar events once (elements are data-turbo-permanent) ──
        if (!SIDEBAR_INITIALIZED) {
            SIDEBAR_INITIALIZED = true;

            toggleBtn.addEventListener('click', function() {
                if (window.innerWidth >= 1024) {
                    toggleDesktop();
                } else {
                    openMobile();
                }
            });

            // ── Mobile drawer open ──
            function openMobile() {
                var sb = document.getElementById('sidebar');
                var bd = document.getElementById('sidebar-backdrop');
                if (sb) {
                    sb.classList.remove('-translate-x-full');
                    sb.classList.add('translate-x-0');
                }
                if (bd) bd.classList.remove('hidden');
            }

            // ── Mobile drawer close ──
            function closeMobile() {
                var sb = document.getElementById('sidebar');
                var bd = document.getElementById('sidebar-backdrop');
                if (sb) {
                    sb.classList.remove('translate-x-0');
                    sb.classList.add('-translate-x-full');
                }
                if (bd) bd.classList.add('hidden');
            }

            // Backdrop is replaced by Turbo, so use delegation on a permanent ancestor (body)
            document.body.addEventListener('click', function(e) {
                if (e.target.closest('#sidebar-backdrop')) closeMobile();
            });
            if (arrowBtn) arrowBtn.addEventListener('click', closeMobile);

            // ── Nav link clicks close mobile drawer ──
            navLinks.forEach(function(link) {
                link.addEventListener('click', closeMobile);
            });
        }

        // ── Initialize icons ──
        lucide.createIcons();

        // ── Active nav highlight (client-side, since sidebar is data-turbo-permanent) ──
        var currentPath = window.location.pathname.split('/')[1] || 'dashboard';
        document.querySelectorAll('.nav-link').forEach(function(link) {
            var route = link.dataset.route || '';
            if (currentPath === route || (currentPath === '' && route === 'dashboard')) {
                link.classList.add('nav-active');
                link.classList.remove('text-white/85', 'hover:text-white', 'hover:bg-white/10');
                link.classList.add('text-white');
            } else {
                link.classList.remove('nav-active', 'text-white');
                link.classList.add('text-white/85', 'hover:text-white', 'hover:bg-white/10');
            }
        });
    });

    // ── Prevent right-click context menu (bind once) ──
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });
})();

if (typeof Chart !== 'undefined') {
    Chart.defaults.color = '#31302e';
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.set('plugins.legend.labels.font.size', 12);
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.pointStyle = 'circle';
    Chart.defaults.plugins.legend.labels.padding = 16;
    Chart.defaults.elements.bar.borderRadius = 4;
    Chart.defaults.scale.grid = { color: 'rgba(0,0,0,0.06)' };
    window.CAGE_COLORS = {
        'CAGE-A': '#1B8A3E', 'CAGE-B': '#2563EB', 'CAGE-C': '#EA580C', 'CAGE-D': '#7C3AED'
    };
}

// ── Reusable loading-button helper for form submissions ──
function loadingButton(btn, label) {
    if (!btn || btn.disabled) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full mr-1 align-middle"></span>'
        + (label || 'Saving\u2026');
}
</script>

@stack('scripts')
</body>
</html>
