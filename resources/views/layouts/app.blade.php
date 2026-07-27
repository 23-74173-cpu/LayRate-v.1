<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="turbo-cache-control" content="no-preview">
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
    <link rel="stylesheet" href="/css/inter.css">

    {{-- Tailwind CSS (compiled) --}}
    <link href="/css/tailwind.css" rel="stylesheet">

    {{-- Prevent white flash while styles load --}}
    <style>
      html { background-color: #F5F6F8; }
      body { background-color: #F5F6F8; }
    </style>

    {{-- Chart.js --}}
    <script src="/js/chart.min.js" defer></script>

    {{-- Lucide Icons --}}
    <script src="/js/lucide.min.js" defer></script>

    {{-- Turbo Drive --}}
    <script type="module" src="/js/turbo.js"></script>

    <style>
        * { -webkit-tap-highlight-color: transparent; }
        html { height: 100%; height: -webkit-fill-available; }
        body { background-color: #F5F6F8; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; height: 100%; height: -webkit-fill-available; overflow: hidden; overscroll-behavior: none; }
        .nav-active { background: rgba(255,255,255,.2); box-shadow: inset 0 0 0 1px rgba(255,255,255,.25); }
        .scrollbar-thin::-webkit-scrollbar { width: 4px; height: 4px; }
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

    {{-- Print: the root flex row and main column are both pinned to a fixed
         viewport height with overflow:hidden on screen (so only the sidebar's
         own nav scrolls) — during print that clips everything to page 1. Only
         these two structural wrappers need overriding; a page's own
         @media print rules (pushed via @stack('head') below) handle content
         specifics like hiding the sidebar or resetting a printable doc's own
         layout. --}}
    <style>
        @media print {
            html, body { height: auto !important; overflow: visible !important; }
            .flex.overflow-hidden { height: auto !important; overflow: visible !important; }
            .flex.flex-col.flex-1.overflow-hidden { height: auto !important; overflow: visible !important; }
            main.page-wrapper { overflow: visible !important; }
        }
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
                <div class="w-11 h-11 rounded-lg bg-white flex items-center justify-center shrink-0 p-1 shadow-sm">
                    <img src="/images/layrate-logo-white.png" alt="LayRate logo" class="w-full h-full object-contain" loading="lazy">
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
                ['icon'=>'home',          'label'=>'Dashboard',          'route'=>'dashboard'],
                ['icon'=>'feather',       'label'=>'Cages',              'route'=>'cages.index'],
                ['icon'=>'bird',          'label'=>'Chickens',           'route'=>'chickens.index'],
                ['icon'=>'egg',           'label'=>'Egg Management',     'route'=>'eggs.logging'],
                ['icon'=>'thermometer',   'label'=>'Environment',        'route'=>'environment'],
                ['icon'=>'cpu',           'label'=>'Hardware',           'route'=>'hardware.index'],
                ['icon'=>'leaf',          'label'=>'Feed & Nutrition',   'route'=>'feed'],
                ['icon'=>'bar-chart-3',   'label'=>'Analytics',          'route'=>'analytics'],
                ['icon'=>'trending-up',   'label'=>'Forecast',           'route'=>'forecast'],
                ['icon'=>'clipboard-list','label'=>'Reports',            'route'=>'reports'],
                ['icon'=>'sticky-note',   'label'=>'Notes',              'route'=>'notes.index'],
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
            <a href="{{ route('profile') }}"
               data-route="profile"
               class="nav-link group flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors"
               title="Profile" aria-label="Profile">
                <i data-lucide="user" class="w-[19px] h-[19px] shrink-0 transition-transform duration-200 group-hover:-translate-y-0.5 group-hover:scale-110"></i>
                <span class="sidebar-label text-sm font-medium whitespace-nowrap overflow-hidden">Profile</span>
            </a>
            <form action="{{ route('logout') }}" method="POST" data-turbo="false"
                  data-confirm="Sign out of LayRate?" data-confirm-action="Sign out" data-confirm-severity="neutral">
                @csrf
                <button type="submit"
                        class="nav-link group w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors"
                        title="Sign out" aria-label="Sign out">
                    <i data-lucide="log-out" class="w-[19px] h-[19px] shrink-0 transition-transform duration-200 group-hover:-translate-y-0.5 group-hover:scale-110"></i>
                    <span class="sidebar-label text-sm font-medium whitespace-nowrap overflow-hidden">Sign out</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- ─── MOBILE BACKDROP ──────────────────────────────────────────────── --}}
    <div id="sidebar-backdrop" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden"></div>

    {{-- ─── MAIN COLUMN ──────────────────────────────────────────────────── --}}
    <div class="flex flex-col flex-1 overflow-hidden">

        {{-- TOP HEADER BAR --}}
        <header id="main-header" data-turbo-permanent class="flex-shrink-0 bg-surface h-12 flex items-center justify-between px-4 border-b border-hairline shadow-soft">
            <div class="flex items-center gap-3">
                {{-- Hamburger: visible on ALL screen sizes --}}
                <button id="sidebar-toggle" class="mr-2 text-ink hover:text-ink-muted transition-colors p-1 rounded" aria-label="Toggle sidebar">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}" class="hover:text-ink transition-colors">Home</a>
                    <span class="mx-1 text-ink-faint">/</span>
                    <span id="breadcrumb-current" class="text-ink font-medium">{{ $title ?? 'Dashboard' }}</span>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('notifications.index') }}" class="relative text-ink hover:text-ink-muted transition-colors" aria-label="Notifications">
                    <i data-lucide="bell" class="w-4 h-4"></i>
                    @if($globalAlertCount > 0)
                    <span class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-alert-text text-white text-xs rounded-full flex items-center justify-center font-bold">{{ $globalAlertCount }}</span>
                    @endif
                </a>
                <div class="relative pl-2 border-l border-hairline">
                    <button id="profileMenuBtn" type="button"
                            class="flex items-center gap-2 rounded-lg px-1.5 py-1 hover:bg-black/5 transition-colors"
                            aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
                        <div class="text-right hidden sm:block">
                            <div class="text-xs text-ink leading-tight">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-ink-muted uppercase tracking-wider">{{ auth()->user()->role }}</div>
                        </div>
                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-ink-muted"></i>
                    </button>
                    <div id="profileMenu" class="hidden absolute right-0 top-full mt-2 w-44 rounded-lg border border-hairline bg-surface shadow-soft py-1 z-50">
                        <a href="{{ route('profile') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-ink hover:bg-black/5 transition-colors">
                            <i data-lucide="user" class="w-4 h-4"></i> Profile
                        </a>
                        <a href="{{ route('profile', ['tab' => 'settings']) }}" class="flex items-center gap-2 px-3 py-2 text-sm text-ink hover:bg-black/5 transition-colors">
                            <i data-lucide="settings" class="w-4 h-4"></i> Settings
                        </a>
                        <div class="my-1 border-t border-hairline"></div>
                        <form action="{{ route('logout') }}" method="POST" data-turbo="false"
                              data-confirm="Sign out of LayRate?" data-confirm-action="Sign out" data-confirm-severity="neutral">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-sm text-alert-text hover:bg-black/5 transition-colors">
                                <i data-lucide="log-out" class="w-4 h-4"></i> Sign out
                            </button>
                        </form>
                    </div>
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
        <main class="page-wrapper flex-1 overflow-y-auto overflow-x-hidden px-3 sm:px-4 lg:px-6 py-4 scrollbar-thin" style="overscroll-behavior: none;">
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
    // Turbo re-parses this inline <script> on every visit (it isn't inside a
    // data-turbo-permanent element), so without this guard every navigation would
    // attach another 'turbo:load'/'contextmenu' listener on top of the previous ones —
    // causing page-load setup (icons, page-transition animation, etc.) to re-run once
    // per accumulated listener on every subsequent visit.
    if (window.__layoutScriptInitialized) return;
    window.__layoutScriptInitialized = true;

    var SIDEBAR_INITIALIZED = false;

    // ── Scroll-position preservation across form-submit navigations ──
    // Many action forms (record mortality, add consumption, add note, ...)
    // redirect()->back() after saving, which — whether Turbo Drive treats it
    // as a full "advance" Visit or a classic reload — rebuilds <main> from
    // scratch and naturally lands at scrollTop 0. Save the scroll position
    // just before any such submit, keyed by path, and restore it once on the
    // next load. Bound once, at the document level, so it survives regardless
    // of how the next page arrives (Turbo visit or classic navigation).
    document.addEventListener('submit', function(e) {
        var mainEl = document.querySelector('.page-wrapper');
        if (!mainEl) return;
        try {
            sessionStorage.setItem('scrollPos:' + location.pathname, String(mainEl.scrollTop));
        } catch (err) { /* sessionStorage unavailable — degrade to no-op */ }
    }, true);

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

        // ── Restore scroll position saved just before the submit that led here ──
        // Re-applied on every turbo:frame-load below too: a lazy-loaded frame
        // (e.g. a "Recent Records" list) can expand the page's scrollHeight
        // well after this first attempt, which would otherwise clamp the
        // restore to whatever little height existed at this exact instant.
        // The saved value itself is only cleared on the next navigation.
        if (mainContent) {
            try {
                var savedScroll = sessionStorage.getItem('scrollPos:' + location.pathname);
                if (savedScroll !== null) {
                    mainContent.scrollTop = parseInt(savedScroll, 10) || 0;
                }
            } catch (err) { /* sessionStorage unavailable — degrade to no-op */ }
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

            // ── Header profile dropdown ──
            var profileBtn = document.getElementById('profileMenuBtn');
            var profileMenu = document.getElementById('profileMenu');
            if (profileBtn && profileMenu) {
                profileBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var isOpen = !profileMenu.classList.contains('hidden');
                    profileMenu.classList.toggle('hidden', isOpen);
                    profileBtn.setAttribute('aria-expanded', String(!isOpen));
                });
                document.body.addEventListener('click', function(e) {
                    if (!e.target.closest('#profileMenu') && !e.target.closest('#profileMenuBtn')) {
                        profileMenu.classList.add('hidden');
                        profileBtn.setAttribute('aria-expanded', 'false');
                    }
                });
                // Close immediately on selecting an item — otherwise the menu
                // stays visibly open (header persists across Turbo visits)
                // when the user lands back on this page later.
                profileMenu.querySelectorAll('a, button').forEach(function(el) {
                    el.addEventListener('click', function() {
                        profileMenu.classList.add('hidden');
                        profileBtn.setAttribute('aria-expanded', 'false');
                    });
                });
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        profileMenu.classList.add('hidden');
                        profileBtn.setAttribute('aria-expanded', 'false');
                    }
                });
            }
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

        // ── Breadcrumb (client-side, since the header is data-turbo-permanent) ──
        var SECTION_LABELS = {
            'dashboard': 'Dashboard',   'cages': 'Cages',            'chickens': 'Chickens',
            'eggs': 'Egg Management',   'egg-production-history': 'Egg History',
            'environment': 'Environment','hardware': 'Hardware',
            'feed': 'Feed & Nutrition', 'analytics': 'Analytics',    'forecast': 'Forecast',
            'reports': 'Reports',       'notes': 'Notes',            'mortality': 'Mortality',
            'notifications': 'Notifications', 'profile': 'Profile',
        };
        var crumb = document.getElementById('breadcrumb-current');
        if (crumb) {
            crumb.textContent = SECTION_LABELS[currentPath] || document.title.split('—')[0].trim();
        }
    });

    // ── Re-initialize Lucide icons when turbo-frame content loads ──
    document.addEventListener('turbo:frame-load', function() {
        lucide.createIcons();

        // Re-apply any pending scroll restore — a lazy frame finishing load
        // can grow the page after the initial turbo:load attempt clamped short.
        var mainEl = document.querySelector('.page-wrapper');
        if (mainEl) {
            try {
                var savedScroll = sessionStorage.getItem('scrollPos:' + location.pathname);
                if (savedScroll !== null) {
                    mainEl.scrollTop = parseInt(savedScroll, 10) || 0;
                }
            } catch (err) { /* sessionStorage unavailable — degrade to no-op */ }
        }
    });

    // Note: deliberately NOT clearing the saved scroll value on turbo:before-visit —
    // that event also fires for the very redirect-following visit this value is
    // meant to be consumed by (redirect()->back() lands on the same path), which
    // would wipe it before turbo:load ever gets to read it. Any stale leftover
    // value is harmless: it's just overwritten by the next real submit on that path.

    // ── Prevent right-click context menu (bind once) ──
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });
})();

window.CAGE_COLORS = @json(\App\Models\Cage::getColorMap());

// Factored out so it can be re-applied after a Chart.js library reload
// (see LayRateChart._reloadChartJsLibrary below) — a fresh Chart module starts
// with library defaults, not this app's.
//
// `full` (default true) applies everything, including scale.grid/layout. LayRateChart's
// self-heal calls this with `full: false` after reloading the library: confirmed live
// that reassigning scale.grid/layout in that specific situation (a Chart module that's
// only just finished loading) is what leaves bar charts unable to paint — merging into
// the existing objects instead of replacing them avoids that, but Chart.js's own
// internal scale-defaults routing silently doesn't persist a merge for scale.grid
// specifically, so the safest fix is to just skip both in that one path. The visual
// difference (library default grid shade/padding instead of this app's) only shows up
// in the rare case a chart needed this recovery at all, which is an acceptable
// trade-off against the chart staying blank.
window.__applyChartDefaults = function(full) {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.color = '#31302e';
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.set('plugins.legend.labels.font.size', 12);
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.pointStyle = 'circle';
    Chart.defaults.plugins.legend.labels.padding = 16;
    Chart.defaults.elements.bar.borderRadius = 4;
    if (full !== false) {
        Chart.defaults.scale.grid = { color: 'rgba(0,0,0,0.06)' };
        Chart.defaults.layout = { padding: { top: 10, bottom: 10, left: 10, right: 10 } };
    }
};
window.__applyChartDefaults();

// ── Shared chart lifecycle manager ──
// Every chart on every page routes through this helper so that:
//   • instances are properly destroyed before recreation (no "goes blank" bug)
//   • all charts are torn down on turbo:before-cache (no stale canvas errors)
//   • consistent defaults (padding, legend, grid) apply globally
window.LayRateChart = {
    _instances: {},
    _configs: {},
    _lifecycleBound: false,
    _recoveryHook: null,
    _recovering: false,
    _generation: 0,

    // A page can register how to "re-render everything I currently show" (e.g.
    // Analytics re-runs its normal AJAX fetch for the active cage/period). The
    // self-heal path below calls this instead of trying to rebuild charts generically
    // from cached configs — confirmed live that rebuilding from a config directly
    // (even a config identical in every way, deep-cloned) does *not* reliably clear
    // the stuck-paint state, but re-entering the page's own real fetch/render flow
    // does, every time tested. Re-registered on every call, so it always points at
    // the current page's live closures, not stale ones from a previous render.
    registerRecoveryHook(fn) {
        this._recoveryHook = fn;
    },

    // Unconditionally rebuilds from a clean Chart.js module before rendering — call
    // this on every tab/cage/period switch, not just when a chart is detected broken.
    // Returns a Promise; render inside its .then(). Heavier than the detect-then-heal
    // approach this replaced, but guaranteed correct every time instead of depending on
    // a check that can itself race.
    prepareForRender() {
        this._generation++;
        Object.keys(this._instances).forEach(id => this.destroy(id));
        return this._reloadChartJsLibrary();
    },

    create(id, config, _retryCount) {
        _retryCount = _retryCount || 0;
        this.destroy(id);
        this._configs[id] = config;
        const canvas = document.getElementById(id);
        if (!canvas) return null;
        try {
            const instance = new Chart(canvas, config);
            this._instances[id] = instance;
            // Defensive self-heal: intermittently (observed live, root cause not fully
            // pinned down despite extensive investigation — ruled out stale scale/data,
            // canvas reuse, animation timing, ResizeObserver loops, and font-loading races
            // as the trigger) a bar chart's dataset elements never get a valid pixel
            // geometry (base stays null) even though data/scale are correct, so nothing
            // paints. Confirmed live: the corruption lives inside Chart.js's own shared
            // module state, not the DOM — neither a fresh canvas+chart instance nor a full
            // turbo-frame reload clears it, but forcing the Chart.js <script> itself to
            // re-execute (a fresh module, no page navigation) reliably does. Only bar
            // charts have been observed affected; harmless no-op otherwise since the check
            // is bar-geometry-specific.
            // Suppressed while a recovery is already in flight (see _verifyBarPainted):
            // the recovery hook itself creates fresh bar charts as part of re-rendering,
            // and letting those schedule their own independent verification/retry chains
            // caused overlapping recoveries to race each other — confirmed live, this is
            // what was reintroducing the earlier stale-cage/period bug via multiple
            // concurrent recovery-triggered tab "clicks".
            if (config.type === 'bar' && _retryCount < 3 && !this._recovering) {
                setTimeout(() => this._verifyBarPainted(id, config, canvas, instance, _retryCount), 1100);
            }
            return instance;
        } catch (e) {
            console.error('[LayRateChart] Failed to create chart "' + id + '":', e);
            return null;
        }
    },

    _verifyBarPainted(id, config, canvas, instance, retryCount) {
        if (this._instances[id] !== instance) return; // superseded by a newer render already
        const meta = instance.getDatasetMeta(0);
        const stuck = meta.data.length > 0 && meta.data.every(el => el.base == null || !isFinite(el.base));
        if (!stuck) return;

        if (retryCount === 0) {
            console.warn('[LayRateChart] "' + id + '" failed to paint, rebuilding once');
            const fresh = document.createElement('canvas');
            fresh.id = id;
            fresh.className = canvas.className;
            fresh.style.cssText = canvas.style.cssText;
            canvas.replaceWith(fresh);
            this.create(id, config, 1);
        } else if (retryCount === 1) {
            console.warn('[LayRateChart] "' + id + '" still stuck — reloading the Chart.js library (graph-only recovery, no page navigation) and re-rendering');
            // Confirmed live: reloading the library while *other* charts built on the old
            // module are still alive doesn't clear it, and neither does rebuilding from a
            // cached config directly (even an exact deep-cloned copy) — only re-entering
            // the page's own real fetch/render flow does. So every currently-live chart is
            // torn down, the library is reloaded, and the page's registered hook re-runs
            // its normal render path (which is what a manual tab re-click already does).
            this._recovering = true;
            const gen = this._generation; // if this changes, the page navigated away — abort
            Object.keys(this._instances).forEach(otherId => this.destroy(otherId));
            this._reloadChartJsLibrary()
                .then(() => {
                    if (gen !== this._generation) return; // navigated away mid-recovery
                    if (typeof this._recoveryHook === 'function') {
                        this._recoveryHook();
                    } else {
                        // No page hook registered — best effort, may not clear it (see above).
                        this.create(id, config, 2);
                    }
                    // Give the recovery's own fetch/render (triggered above) time to finish
                    // before allowing verification to run again.
                    setTimeout(() => {
                        if (gen === this._generation) this._recovering = false;
                    }, 2000);
                })
                .catch(() => {
                    if (gen !== this._generation) return; // navigated away mid-recovery
                    this._recovering = false;
                    window.location.reload();
                });
        } else {
            console.error('[LayRateChart] "' + id + '" still failed to paint after a Chart.js library reload — falling back to a full page reload');
            window.location.reload();
        }
    },

    // Forces Chart.js to fully re-initialize (fresh Animator, fresh registries) by
    // re-executing its <script> tag, without touching the rest of the page. Existing
    // chart instances built against the old module keep working (their own destroy()/
    // update() stay bound to their own instance), but new ones after this point use the
    // fresh module — which is what actually clears the stuck-paint state.
    _reloadChartJsLibrary() {
        return new Promise((resolve, reject) => {
            const old = document.querySelector('script[src*="chart.min.js"]');
            if (!old) { reject(new Error('chart.min.js script tag not found')); return; }
            const fresh = document.createElement('script');
            fresh.src = old.src.split('?')[0] + '?r=' + Date.now();
            fresh.onload = () => {
                if (typeof window.__applyChartDefaults === 'function') window.__applyChartDefaults(false);
                // The fresh module isn't actually ready to build a working chart the
                // instant its script finishes executing — confirmed live: rebuilding
                // immediately on load still failed every time, but the exact same rebuild
                // succeeded reliably once a short delay was inserted first. Likely some
                // async part of Chart.js's own init (its animator's first scheduling tick)
                // that onload doesn't wait for.
                setTimeout(resolve, 300);
            };
            fresh.onerror = () => reject(new Error('Failed to reload chart.min.js'));
            old.remove();
            document.head.appendChild(fresh);
        });
    },

    update(id, config) {
        const inst = this._instances[id];
        if (inst) {
            try {
                if (config.data) inst.data = config.data;
                if (config.options) {
                    if (config.options.scales) inst.options.scales = config.options.scales;
                    if (config.options.plugins) inst.options.plugins = config.options.plugins;
                }
                inst.update('none');
                return inst;
            } catch (e) {
                console.warn('[LayRateChart] Update failed for "' + id + '", falling back to recreate:', e);
            }
        }
        return this.create(id, config);
    },

    destroy(id) {
        const inst = this._instances[id];
        if (inst) {
            try { inst.destroy(); } catch (e) {}
            delete this._instances[id];
        }
    },

    destroyAll() {
        // Invalidates any in-flight self-heal recovery (see _verifyBarPainted) — without
        // this, navigating away mid-recovery left its pending .then() callback to fire
        // later against whatever the *next* page happened to render, clicking tabs and
        // creating charts that had nothing to do with the page the user was now on.
        // Confirmed live: repeated fast navigation reliably corrupted state without this.
        this._generation++;
        this._recovering = false;
        Object.keys(this._instances).forEach(id => this.destroy(id));
    },

    _bindLifecycle() {
        if (this._lifecycleBound) return;
        this._lifecycleBound = true;
        document.addEventListener('turbo:before-cache', () => this.destroyAll());
    }
};
LayRateChart._bindLifecycle();

// ── Reusable loading-button helper for form submissions ──
function loadingButton(btn, label) {
    if (!btn || btn.disabled) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full mr-1 align-middle"></span>'
        + (label || 'Saving\u2026');
}
</script>

<x-confirm-modal />
<x-notification-toast />
<x-loading-modal />
<x-transaction-logger />

@stack('scripts')
</body>
</html>
