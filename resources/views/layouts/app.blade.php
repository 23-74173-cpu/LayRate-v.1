<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="turbo-cache-control" content="no-preview">
    {{-- No hover prefetch: on the Pi (and php artisan serve) every hovered
         sidebar link was a full page request queued ahead of the real click. --}}
    <meta name="turbo-prefetch" content="false">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#1a2342">
    <link rel="manifest" href="/manifest.json">
    <title>{{ $title ?? 'LayRate' }} — LayRate Farm Monitor</title>

    {{-- Favicons (generated from /images/layrate-logo.png) --}}
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <meta property="og:image" content="/images/layrate-logo.png">
    <meta property="og:title" content="LayRate Farm Monitor">

    {{-- Inter Font (self-hosted) --}}
    <link rel="stylesheet" href="/css/inter.css">

    {{-- Tailwind CSS (compiled) --}}
    <link href="/css/tailwind.css?v={{ @filemtime(public_path('css/tailwind.css')) }}" rel="stylesheet">

    {{-- Prevent white flash while styles load --}}
    <style>
      html { background-color: #F0F0F0; }
      body { background-color: #F0F0F0; }
    </style>

    {{-- Turbo Drive --}}
    <script type="module" src="/js/turbo.js"></script>

    {{-- Back/Forward after sign-out goes to login, never a cached farm page. --}}
    <script src="/js/auth-guard.js?v={{ @filemtime(public_path('js/auth-guard.js')) }}" data-mode="app"
            data-status-url="{{ route('auth.status') }}" data-login-url="{{ route('login') }}"
            data-home-url="{{ route('dashboard') }}" data-logout-url="{{ route('logout') }}"></script>

    {{-- Shared chart metric palette (CSS-var backed, hardcoded fallbacks).
         Synchronous in head so inline chart configs can use it on first paint. --}}
    <script src="/js/chart-colors.js?v={{ @filemtime(public_path('js/chart-colors.js')) }}"></script>

    {{-- Date fields always show and accept mm/dd/yyyy, whatever the browser's
         language (see the comment at the top of the file). Loaded once; it
         watches the page for date inputs added by Turbo visits and frames. --}}
    <script src="/js/date-mdy.js?v={{ @filemtime(public_path('js/date-mdy.js')) }}" defer></script>

    {{-- Branded client-side validation (replaces native bubbles site-wide).
         Delegated + Turbo-aware; opt out per form with data-native-validation. --}}
    <script src="/js/form-validation.js?v={{ @filemtime(public_path('js/form-validation.js')) }}" defer></script>

    {{-- Branded tooltips replacing native title="" bubbles (auto-upgrade,
         delegated + Turbo-aware). --}}
    <script src="/js/tooltip.js?v={{ @filemtime(public_path('js/tooltip.js')) }}" defer></script>

    {{-- One consistent filter system for every record table (x-filter-bar):
         auto-apply, debounced search, phone bottom sheet, chips. Delegated +
         Turbo-aware; inert on pages without a filter bar. --}}
    <script src="/js/filter-ui.js?v={{ @filemtime(public_path('js/filter-ui.js')) }}" defer></script>

    {{-- KPI card explanation popovers for every info button (delegated,
         Turbo-aware, binds once). --}}
    <script src="/js/kpi-info.js?v={{ @filemtime(public_path('js/kpi-info.js')) }}" defer></script>
    {{-- Full-screen chart viewer with pinch-zoom (zoom libraries load on first use). --}}
    <script src="/js/chart-fullscreen.js?v={{ @filemtime(public_path('js/chart-fullscreen.js')) }}" defer></script>

    {{-- Slow-page loading screen: appears only when a page takes >600ms to
         render. Fast loads clear the timer and never flash it. Covers both
         Turbo Drive visits and initial hard loads. Guarded against Turbo
         re-evaluation so listeners aren't attached twice. --}}
    <style>
        @keyframes app-spin { to { transform: rotate(360deg); } }
        #app-loading-overlay {
            position: fixed; inset: 0; z-index: 10000;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 18px;
            background: rgba(240, 240, 240, 0.92); backdrop-filter: blur(2px);
            opacity: 0; pointer-events: none; transition: opacity 0.25s ease;
        }
        #app-loading-overlay.visible { opacity: 1; pointer-events: auto; }
        #app-loading-overlay .al-spinner {
            width: 40px; height: 40px; border-radius: 50%;
            border: 3px solid rgba(0, 45, 94, 0.2); border-top-color: var(--color-navy);
            animation: app-spin 0.8s linear infinite;
        }
        #app-loading-overlay .al-text { font-size: 13px; font-weight: 500; color: #6B7280; letter-spacing: 0.02em; }
        @media (prefers-reduced-motion: reduce) {
            #app-loading-overlay .al-spinner { animation: none; }
            #app-loading-overlay { transition: none; }
        }
    </style>
    <script>
    (function () {
        if (window.__appLoadingInit) return;
        window.__appLoadingInit = true;

        // Element lives in <body>, but on a slow initial hard load the body
        // may not have arrived yet — so query at call time and inject the
        // overlay early if needed.
        function ensureOverlay() {
            var el = document.getElementById('app-loading-overlay');
            if (el) return el;
            el = document.createElement('div');
            el.id = 'app-loading-overlay';
            el.setAttribute('role', 'status');
            el.setAttribute('aria-live', 'polite');
            el.innerHTML = '<div class="al-spinner"></div><div class="al-text">Loading…</div>';
            (document.body || document.documentElement).appendChild(el);
            return el;
        }
        function show() { ensureOverlay().classList.add('visible'); }
        function hide() { var el = document.getElementById('app-loading-overlay'); if (el) el.classList.remove('visible'); }

        var timer = null;
        function arm() {
            // Loading animation disabled: Turbo visits are fast enough now that the
            // spinner is never worth showing fulfilling. `timer` is left null so the
            // hide/clearTimeout listeners below simply no-op.
            clearTimeout(timer);
            timer = null;
        }

        // Initial hard load: arm while the document is still being transferred.
        if (document.readyState === 'loading') arm();

        window.addEventListener('load', function () { clearTimeout(timer); hide(); });
        // Hide as soon as the initial document is parsed (window.load can lag
        // behind waiting on images, which would flash the overlay over a
        // rendered page).
        document.addEventListener('DOMContentLoaded', function () { clearTimeout(timer); hide(); });

        // Turbo Drive visits (drive only — lazy frames keep their skeletons).
        document.addEventListener('turbo:before-visit', arm);
        document.addEventListener('turbo:load', function () { clearTimeout(timer); hide(); });
        document.addEventListener('turbo:render', hide);
        document.addEventListener('turbo:visit-end', function () { clearTimeout(timer); hide(); });
    })();
    </script>

    <style>
        * { -webkit-tap-highlight-color: transparent; }
        html { height: 100%; height: -webkit-fill-available; }
        body { background-color: #F0F0F0; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; height: 100%; height: -webkit-fill-available; overflow: hidden; overscroll-behavior: none; }
        /* ── Sidebar shell: flat deep navy shared with login, landing hero
               and dashboard banner (tokens: --color-sidebar-bg #1a2342,
               --color-secondary #213183). At most a whisper of vertical
               light so the surface reads flat, plus a hairline divider
               against the content area. ── */
        #sidebar {
            background-color: var(--color-sidebar-bg);
            background-image: linear-gradient(180deg, rgba(255,255,255,0.045) 0%, rgba(255,255,255,0) 28%, rgba(0,0,0,0) 72%, rgba(0,0,0,0.10) 100%);
            border-right: 1px solid rgba(255,255,255,0.09);
        }
        /* ── Active nav item: soft translucent fill + 3px brand-sky rail ── */
        .nav-active {
            position: relative;
            background: rgba(255,255,255,.10) !important;
            border-radius: 8px;
            color: #ffffff !important;
            font-weight: 600;
        }
        .nav-active::before {
            content: "";
            position: absolute;
            left: 0;
            top: 6px;
            bottom: 6px;
            width: 3px;
            border-radius: 9999px;
            background: #62aef0;
        }
        .nav-active i, .nav-active .sidebar-label { color: #ffffff !important; }
        /* ── Sign out reads as an exit action on hover ── */
        .signout-link:hover {
            background-color: rgba(251,113,133,.14) !important;
            color: #fecdd3 !important;
        }
        .signout-link:hover i { color: #fecdd3 !important; }
        .scrollbar-thin::-webkit-scrollbar { width: 4px; height: 4px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #D9D9D9; border-radius: 9999px; }
        [x-cloak] { display: none !important; }

        :focus-visible {
            outline: 2px solid var(--color-navy);
            outline-offset: 2px;
            border-radius: 4px;
        }

        /* ── Desktop collapsed state: hide labels, center icons ── */
        @media (min-width: 1024px) {
            #sidebar.w-16 .sidebar-label,
            #sidebar.w-16 .logo-text,
            #sidebar.w-16 .sidebar-section,
            .sidebar-collapsed #sidebar .sidebar-label,
            .sidebar-collapsed #sidebar .logo-text,
            .sidebar-collapsed #sidebar .sidebar-section { display: none !important; }
            #sidebar.w-16 .nav-link,
            .sidebar-collapsed #sidebar .nav-link {
                justify-content: center !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            #sidebar.w-16 .logo-wrap,
            .sidebar-collapsed #sidebar .logo-wrap { justify-content: center !important; }

            /* Collapsed: center the brand logo without cropping and drop the
               side gutters so the 44px logo fits 64px of sidebar exactly. */
            #sidebar.w-16 .brand-block,
            .sidebar-collapsed #sidebar .brand-block {
                padding-left: 0 !important;
                padding-right: 0 !important;
                border-bottom-color: transparent !important;
            }
            #sidebar.w-16 .brand-block > .flex,
            .sidebar-collapsed #sidebar .brand-block > .flex { justify-content: center !important; }

            #sidebar.w-16 nav,
            .sidebar-collapsed #sidebar nav { overflow-x: hidden !important; }

            /* Collapsed rail: the portrait egg alone at ~34px tall. */
            #sidebar.w-16 .brand-block img,
            .sidebar-collapsed #sidebar .brand-block img { height: 34px !important; }

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

    {{-- No-JS fallback: entrance animations must never leave content hidden.
         (With JS they end at full opacity via fill modes; this covers the
         case where CSS animations are interrupted or scripting is off.) --}}
    <noscript><style>
        .page-wrapper, .page-wrapper > .space-y-5 > *, .dash-rise, .dash-pop,
        .turbo-loaded, .login-enter { opacity: 1 !important; transform: none !important; animation: none !important; }
    </style></noscript>

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
<body class="h-screen overflow-hidden bg-[#F0F0F0] select-none">

{{-- ── Turbo navigation loading bar ─────────────────────────────────────── --}}
<style>
#turbo-loading-bar {
    position: fixed; top: 0; left: 0; height: 3px;
    background: linear-gradient(90deg, var(--color-navy), #62aef0);
    z-index: 9999; pointer-events: none;
    width: 0%; opacity: 0;
    transition: width 0.4s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.3s;
}
#turbo-loading-bar.active { opacity: 1; }
</style>
<div id="turbo-loading-bar"></div>

{{-- ── Slow-page loading screen (shown only after 600ms, see head script) ── --}}
<div id="app-loading-overlay" role="status" aria-live="polite" aria-busy="false">
    <div class="al-spinner"></div>
    <div class="al-text">Loading…</div>
</div>

{{-- ── Turbo loading bar control ─────────────────────────────────────────── --}}
<script>
(function() {
    // Bound once: Turbo re-runs this script on every visit, and the bar
    // element is replaced each time, so it is looked up inside the handlers.
    if (window.__turboBarBound) return;
    window.__turboBarBound = true;
    var timer;

    document.addEventListener('turbo:before-visit', function() {
        var bar = document.getElementById('turbo-loading-bar');
        if (!bar) return;
        clearTimeout(timer);
        bar.style.width = '0%';
        bar.classList.add('active');
        requestAnimationFrame(function() {
            bar.style.transition = 'width 3s cubic-bezier(0.22, 1, 0.36, 1)';
            bar.style.width = '85%';
        });
    });

    document.addEventListener('turbo:load', function() {
        var bar = document.getElementById('turbo-loading-bar');
        if (!bar) return;
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
           class="flex flex-col bg-linear-to-br from-secondary to-sidebar-bg text-white
                  transition-all duration-300 ease-in-out
                  flex-shrink-0
                  fixed lg:relative lg:translate-x-0
                  -translate-x-full z-40 w-64"
           style="width: 16rem; height: 100vh; height: -webkit-fill-available; height: 100dvh;">

        {{-- TOP: Brand + Arrow (mobile close / desktop unused) --}}
        <div class="brand-block px-4 pt-3 pb-3 shrink-0 border-b border-white/10">
            <div class="flex items-center justify-between">
                <div class="logo-wrap flex items-center gap-2.5 overflow-hidden">
                    {{-- THE logo sits directly on the navy: white egg with dark
                         outline needs no tile or effects to stand out. --}}
                    <x-logo size="h-11" class="shrink-0" />
                    <div class="logo-text overflow-hidden whitespace-nowrap">
                        <div class="text-white text-sm font-bold leading-tight">LayRate</div>
                        <div class="text-white/60 text-xs leading-tight">Farm Monitor</div>
                    </div>
                </div>
                {{-- Arrow button: mobile drawer close only (hidden on desktop) --}}
                <button id="sidebar-arrow" class="lg:hidden text-white/50 hover:text-white transition-colors p-1 rounded shrink-0" aria-label="Close menu">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </button>
            </div>
        </div>

        {{-- MIDDLE: Main nav (scrollable) --}}
        <nav class="flex-1 overflow-y-auto py-4 px-3 scrollbar-thin">
            @php
            $sections = [
                'Overview' => [
                    ['icon'=>'home',          'label'=>'Dashboard',          'route'=>'dashboard'],
                ],
                'Production' => [
                    ['icon'=>'feather',       'label'=>'Cages',              'route'=>'cages.index'],
                    ['icon'=>'bird',          'label'=>'Hens',               'route'=>'chickens.index'],
                    ['icon'=>'egg',           'label'=>'Egg Management',     'route'=>'eggs.logging'],
                    ['icon'=>'leaf',          'label'=>'Feed & Nutrition',   'route'=>'feed'],
                ],
                'Monitoring' => [
                    ['icon'=>'thermometer',   'label'=>'Environment',        'route'=>'environment'],
                    ['icon'=>'cpu',           'label'=>'Hardware',           'route'=>'hardware.index'],
                ],
                'Business' => [
                    ['icon'=>'wallet',        'label'=>'Finance',            'route'=>'finance.index'],
                ],
                'Insights' => [
                    ['icon'=>'trending-up',   'label'=>'Forecast',           'route'=>'forecast', 'adminOnly' => true],
                    ['icon'=>'clipboard-list','label'=>'Reports',            'route'=>'reports'],
                    ['icon'=>'sticky-note',   'label'=>'Notes',              'route'=>'notes.index'],
                ],
            ];
            @endphp

            @foreach($sections as $sectionName => $items)
            @php
                $visible = array_values(array_filter($items, fn($item) => empty($item['adminOnly']) || auth()->user()?->isAdmin()));
            @endphp
            @if(empty($visible))
                @continue
            @endif
            <div class="mt-3 first:mt-0">
                <div class="sidebar-section px-3 pb-1 text-[10px] uppercase tracking-[0.14em] text-white/80 font-semibold whitespace-nowrap">{{ $sectionName }}</div>
                @foreach($visible as $item)
                <a href="{{ route($item['route']) }}"
                   data-route="{{ $item['route'] === 'dashboard' ? 'dashboard' : explode('.', $item['route'])[0] }}"
                   class="nav-link group flex items-center gap-3 px-3 py-2 rounded-lg transition-colors text-white/85 hover:text-white hover:bg-white/10"
                   title="{{ $item['label'] }}"
                   aria-label="{{ $item['label'] }}">
                    <span class="flex items-center gap-3 min-w-0 transition-transform duration-200 ease-out group-hover:-translate-y-0.5 group-hover:translate-x-1">
                        <i data-lucide="{{ $item['icon'] }}" class="w-[19px] h-[19px] shrink-0"></i>
                        <span class="sidebar-label text-xs font-medium whitespace-nowrap overflow-hidden min-w-0">{{ $item['label'] }}</span>
                    </span>
                </a>
                @endforeach
            </div>
            @endforeach
        </nav>

        {{-- BOTTOM: Pinned footer nav --}}
        <div class="border-t border-white/10 py-4 px-3 shrink-0">
            <a href="{{ route('profile') }}"
               data-route="profile"
               class="nav-link group flex items-center gap-3 px-3 py-2 rounded-lg transition-colors text-white/85 hover:text-white hover:bg-white/10"
               title="Profile" aria-label="Profile">
                <span class="flex items-center gap-3 min-w-0 transition-transform duration-200 ease-out group-hover:-translate-y-0.5 group-hover:translate-x-1">
                    <i data-lucide="user" class="w-[19px] h-[19px] shrink-0"></i>
                    <span class="sidebar-label text-xs font-medium whitespace-nowrap overflow-hidden min-w-0">Profile</span>
                </span>
            </a>
            <form action="{{ route('logout') }}" method="POST" data-turbo="false"
                  data-confirm="Sign out of LayRate?" data-confirm-action="Sign out" data-confirm-severity="neutral">
                @csrf
                <button type="submit"
                        class="signout-link nav-link group w-full flex items-center gap-3 px-3 py-2 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors"
                        title="Sign out" aria-label="Sign out">
                    <span class="flex items-center gap-3 min-w-0 transition-transform duration-200 ease-out group-hover:-translate-y-0.5 group-hover:translate-x-1">
                        <i data-lucide="log-out" class="w-[19px] h-[19px] shrink-0"></i>
                        <span class="sidebar-label text-xs font-medium whitespace-nowrap overflow-hidden min-w-0">Sign out</span>
                    </span>
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
                @hasSection('header-clock')
                <span class="text-xs text-ink-muted whitespace-nowrap hidden sm:inline">@yield('header-clock')</span>
                @endif
                <a href="{{ route('notifications.index') }}" class="relative rounded-full p-1.5 text-ink hover:bg-black/5 transition-colors" aria-label="Notifications">
                    <i data-lucide="bell" class="w-5 h-5 block"></i>
                    @if($globalAlertCount > 0)
                    <span class="absolute -top-0.5 -right-0.5 min-w-3.5 h-3.5 px-0.5 bg-alert-text text-white text-[10px] leading-3.5 rounded-full flex items-center justify-center font-bold">{{ $globalAlertCount }}</span>
                    @endif
                </a>
                <div class="relative pl-2 border-l border-hairline">
                    <button id="profileMenuBtn" type="button"
                            class="flex items-center gap-2 rounded-lg px-1.5 py-1 hover:bg-black/5 transition-colors"
                            aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
                        @php
                            $initials = collect(preg_split('/\s+/', trim(auth()->user()->name ?? '')))->filter()->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('');
                        @endphp
                        <span class="w-7 h-7 rounded-full bg-secondary text-white text-[11px] font-semibold flex items-center justify-center shrink-0" aria-hidden="true">{{ $initials !== '' ? $initials : '•' }}</span>
                        <div class="text-right hidden sm:block">
                            <div class="text-xs font-medium text-ink leading-tight">{{ auth()->user()->name }}</div>
                            <div class="text-[10px] text-ink-muted uppercase tracking-wider leading-tight">{{ auth()->user()->role }}</div>
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
        <div class="mx-4 mt-3 flex items-center gap-2 bg-success-bg border border-success-border text-success px-4 py-2.5 rounded-lg text-sm" role="status">
            <i data-lucide="check-circle" class="w-4 h-4 text-success shrink-0"></i>
            {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="mx-4 mt-3 flex items-center gap-2 bg-danger-bg border border-danger-border text-danger px-4 py-2.5 rounded-lg text-sm" role="alert">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-danger shrink-0"></i>
            {{ session('error') }}
        </div>
        @endif
        @if(session('forecast_warning'))
        <div class="mx-4 mt-3 flex items-center gap-2 bg-warning-bg border border-warning-border text-warning px-4 py-2.5 rounded-lg text-sm" role="status">
            <i data-lucide="alert-circle" class="w-4 h-4 text-warning shrink-0"></i>
            {{ session('forecast_warning') }}
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

    // Scroll-restore window: a saved scroll position (set on form submit) may
    // only be re-applied by lazy turbo-frame loads while the page is still
    // settling. Once the window expires, the saved value is cleared so a stale
    // one can never yank the page back to the top on a later, unrelated frame load.
    var restoreUntil = 0;

    // ── Shared Floating Action Button (delegated, bound once) ──
    // Drives the "+" menu inside the global quick-actions dock (and any
    // legacy .fab markup). The menu uses `hidden` (fully out of flow) rather
    // than opacity-only hiding, so a closed menu never reserves space.
    // Handlers are delegated to document (a permanent ancestor) instead of
    // being bound per element, so they survive Turbo visits.
    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('.fab-toggle');
        if (toggle) {
            var fab = toggle.closest('.fab');
            var menu = fab ? fab.querySelector('.fab-menu') : null;
            var icon = fab ? fab.querySelector('.fab-icon') : null;
            var isOpen = menu && !menu.classList.contains('hidden');
            if (isOpen) {
                menu.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Open menu');
                if (icon) icon.style.transform = 'rotate(0deg)';
            } else {
                menu.classList.remove('hidden');
                toggle.setAttribute('aria-expanded', 'true');
                toggle.setAttribute('aria-label', 'Close menu');
                if (icon) icon.style.transform = 'rotate(45deg)';
            }
            return;
        }
        // Outside click closes any open FAB menu
        document.querySelectorAll('.fab .fab-menu:not(.hidden)').forEach(function(menu) {
            var fab = menu.closest('.fab');
            var fabToggle = fab ? fab.querySelector('.fab-toggle') : null;
            var icon = fab ? fab.querySelector('.fab-icon') : null;
            menu.classList.add('hidden');
            if (fabToggle) {
                fabToggle.setAttribute('aria-expanded', 'false');
                fabToggle.setAttribute('aria-label', 'Open menu');
            }
            if (icon) icon.style.transform = 'rotate(0deg)';
        });
    });

    // Some pages (e.g. Egg History) have no inline script of their own to call
    // lucide.createIcons() after a Turbo visit, so ensure the FAB's plus icon is
    // always rendered. Scoped to the FAB to avoid disturbing per-page handling.
    document.addEventListener('turbo:load', function() {
        var fabIcons = document.querySelectorAll('.fab [data-lucide]');
        if (fabIcons.length && window.lucide) {
            window.lucide.createIcons({ els: fabIcons });
        }
    });

    // ── Pi connection-loss notice ──
    // Turbo frames, streams and fetch calls hit the Pi over mDNS
    // (layratepi.local), which flaps while the device hops networks
    // (hotspot ↔ router) or the browser re-resolves .local to a different
    // address family. Surface one banner instead of console spam, then
    // reload stalled lazy frames once the network settles.
    var piOfflineUntil = 0;
    function showPiOfflineBanner() {
        if (Date.now() < piOfflineUntil) return;
        piOfflineUntil = Date.now() + 30000;
        var banner = document.getElementById('pi-offline-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'pi-offline-banner';
            banner.setAttribute('role', 'alert');
            banner.style.cssText = 'position:fixed;left:50%;transform:translateX(-50%);bottom:16px;z-index:9999;max-width:min(92vw,28rem);background:#7f1d1d;color:#fff;font-size:13px;line-height:1.45;padding:10px 14px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.25);';
            banner.textContent = 'Connection to the Pi was lost — stay on the LayRatePI network and this page will retry automatically.';
            document.body.appendChild(banner);
        }
        banner.style.display = 'block';
        setTimeout(function() { banner.style.display = 'none'; }, 30000);
    }
    document.addEventListener('turbo:fetch-request-error', function(e) {
        showPiOfflineBanner();
        var url = e.detail && (e.detail.url || (e.detail.request && e.detail.request.url));
        setTimeout(function() {
            document.querySelectorAll('turbo-frame[src]').forEach(function(f) {
                var src = f.getAttribute('src');
                if (!url || src === url || f.src === url) {
                    try { f.reload(); } catch (err) { /* frame detached */ }
                }
            });
        }, 5000);
    });
    window.addEventListener('offline', showPiOfflineBanner);

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
        if (!sidebar) return;
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
                    // Keep letting lazy frames nudge the restore while the page
                    // is still growing, then clear it so it can't re-apply later.
                    window.__scrollRestored = false;
                    restoreUntil = Date.now() + 3000;
                    setTimeout(function () {
                        restoreUntil = 0;
                        try { sessionStorage.removeItem('scrollPos:' + location.pathname); } catch (e) {}
                    }, 3000);
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

            // ── Keep the full/collapsed state consistent across breakpoint
            // crossings (resize laptop→tablet→laptop) without a full reload. ──
            window.addEventListener('resize', function() {
                applySidebarState();
            }, { passive: true });

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
                link.setAttribute('aria-current', 'page');
            } else {
                link.classList.remove('nav-active', 'text-white');
                link.classList.add('text-white/85', 'hover:text-white', 'hover:bg-white/10');
                link.removeAttribute('aria-current');
            }
        });

        // ── Breadcrumb (client-side, since the header is data-turbo-permanent) ──
        var SECTION_LABELS = {
            'dashboard': 'Dashboard',   'cages': 'Cages',            'chickens': 'Hens',
            'eggs': 'Egg Management',   'egg-production-history': 'Egg History',
            'environment': 'Environment','hardware': 'Hardware',
            'feed': 'Feed & Nutrition', 'analytics': 'Analytics',    'forecast': 'Forecast',
            'reports': 'Reports',       'notes': 'Notes',            'mortality': 'Mortality',
            'finance': 'Finance',
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

        // Re-apply scroll restore only ONCE after turbo:load — lazy frames
        // finishing load can grow the page after the initial turbo:load attempt
        // clamped short. A flag ensures we don't keep yanking scroll back on
        // every subsequent frame load within the restore window.
        if (restoreUntil && Date.now() < restoreUntil && !window.__scrollRestored) {
            window.__scrollRestored = true;
            try {
                var mainEl = document.querySelector('.page-wrapper');
                var savedScroll = sessionStorage.getItem('scrollPos:' + location.pathname);
                if (mainEl && savedScroll !== null) {
                    mainEl.scrollTop = parseInt(savedScroll, 10) || 0;
                }
            } catch (err) { /* sessionStorage unavailable — degrade to no-op */ }
        }
    });

    // Note: deliberately NOT clearing the saved scroll value on turbo:before-visit —
    // that event also fires for the very redirect-following visit this value is
    // meant to be consumed by (redirect()->back() lands on the same path), which
    // would wipe it before turbo:load ever gets to read it. Instead the value is
    // consumed ~3s after turbo:load (see restoreUntil above), after which it is
    // removed so it can never be re-applied by unrelated lazy frame loads.

    // ── Prevent right-click context menu (bind once) ──
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });
})();

// ── Shared modal keyboard nav (Esc = close, Enter = submit) — bind once ──
// Opt-in via `data-modal` (+ `data-modal-destructive` for Esc-only, and
// optional `data-close="closeFnName"`). Nested overlays: when the shared
// confirm modal (confirm-modal) is open on top of a data-modal, it OWNS the
// keypress — this listener returns immediately so Esc and Enter never
// double-fire into the parent modal underneath on the same keypress.
(function () {
    if (window.__modalKeyNavBound) return;
    window.__modalKeyNavBound = true;

    function visible(el) {
        if (!el) return false;
        return getComputedStyle(el).display !== 'none' && el.getAttribute('aria-hidden') !== 'true';
    }
    function confirmOpen() {
        var el = document.getElementById('confirm-modal');
        return !!(el && getComputedStyle(el).display !== 'none');
    }
    function topmostOpen() {
        var all = document.querySelectorAll('[data-modal]');
        var top = null;
        for (var i = 0; i < all.length; i++) if (visible(all[i])) top = all[i];
        return top;
    }
    function closeEl(el) {
        var fn = el.getAttribute('data-close');
        if (fn && typeof window[fn] === 'function') { window[fn](); return; }
        el.style.display = 'none';
    }

    document.addEventListener('keydown', function (e) {
        if (confirmOpen()) return;          // nested confirm owns Esc/Enter

        var m = topmostOpen();
        if (!m) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            closeEl(m);
            return;
        }
        if (e.key === 'Enter') {
            if (m.hasAttribute('data-modal-destructive')) return;  // Esc-only
            var ae = document.activeElement;
            if (ae && (ae.tagName === 'TEXTAREA' || ae.tagName === 'BUTTON' || ae.tagName === 'A')) return;
            var target = m.querySelector('[data-modal-enter]') || m.querySelector('button[type="submit"]');
            if (target) { e.preventDefault(); target.click(); }
        }
    }, true);
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
    // Single source of truth for axis-tick size — pages used to each hardcode their
    // own (10, 11, or unset/12), which is exactly the "every graph looks different"
    // drift this centralizes. Individual charts should stop repeating this value.
    Chart.defaults.font.size = 10;
    // NOTE: never use Chart.defaults.set('plugins.legend.labels.font.size', 11) here.
    // Chart.js 4.4.0's set() with a dotted path corrupts the legend font size into a
    // bare object ({}), and every chart with a visible legend then dies during layout
    // fitting with "Cannot convert object to primitive value" (spacing/box size math).
    // Merge any existing props first, then assign the numeric size directly.
    Chart.defaults.plugins.legend.labels.font = Object.assign({}, Chart.defaults.plugins.legend.labels.font || {});
    Chart.defaults.plugins.legend.labels.font.size = 11;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.pointStyle = 'circle';
    Chart.defaults.plugins.legend.labels.boxWidth = 10;
    Chart.defaults.plugins.legend.labels.padding = 12;
    // Legend labels toggle datasets on click — pointer on hover, default off.
    // (Canvas-drawn, so CSS cannot target them; defaults are the one place.)
    Chart.defaults.plugins.legend.onHover = function(e) {
        if (e && e.native && e.native.target) e.native.target.style.cursor = 'pointer';
    };
    Chart.defaults.plugins.legend.onLeave = function(e) {
        if (e && e.native && e.native.target) e.native.target.style.cursor = 'default';
    };
    Chart.defaults.elements.bar.borderRadius = 4;
    // Entry animation for every chart: no horizontal sweep anywhere (x never
    // animates — that sweep is what made charts look like they slid in from
    // the left); y rises from the axis baseline over ~600ms with easeOutQuart
    // (the library's native initial y-from). Bars keep growing from base;
    // pie/doughnut keep their own rotate/scale (separate options, untouched).
    // Merged (not replaced) so colors/numbers/legend defaults above survive.
    // Respects prefers-reduced-motion (re-evaluated on every defaults pass).
    var __reduceChartMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    Chart.defaults.animations = Object.assign({}, Chart.defaults.animations, {
        x: { duration: 0 },
        y: { duration: __reduceChartMotion ? 0 : 600, easing: 'easeOutQuart' },
    });
    // Container resizes (sidebar, dock, fonts, scrollbars) must not replay
    // the entry animation — debounce the resize handling instead.
    Chart.defaults.resizeDelay = 120;
    // Subtle vertical hover crosshair for line/area charts. Registered once
    // globally; individual charts opt in via options.plugins.layrateCrosshair.
    if (typeof Chart.register === 'function' && !Chart.registry.plugins.get('layrateCrosshair')) {
        Chart.register({
            id: 'layrateCrosshair',
            afterDraw: function (chart, args, opts) {
                if (!opts || !opts.enabled) return;
                var active = chart.tooltip && chart.tooltip.getActiveElements ? chart.tooltip.getActiveElements() : [];
                if (!active.length || !active[0].element) return;
                var x = active[0].element.x;
                var area = chart.chartArea;
                var ctx = chart.ctx;
                ctx.save();
                ctx.strokeStyle = (opts && opts.color) || 'rgba(0,45,94,0.18)';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(x, area.top);
                ctx.lineTo(x, area.bottom);
                ctx.stroke();
                ctx.restore();
            }
        });
    }
    if (full !== false) {
        Chart.defaults.scale.grid = { color: 'rgba(0,0,0,0.06)' };
        Chart.defaults.layout = { padding: { top: 10, bottom: 10, left: 10, right: 10 } };
    }
};
// Applied right after chart.min.js loads (end of <body>), not here: this point
// runs before Chart exists on a full page load, so these defaults used to be
// skipped there, and on Turbo visits only some charts picked them up from the
// previous page's copy of Chart.js (one page could mix both looks). Chart.js
// now loads once and stays loaded, so applying it once covers every chart.

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
    // Set when a stuck-paint recovery MUST have a guaranteed-fresh Chart.js module;
    // prepareForRender() then performs the heavy script reload instead of blessing the
    // already-loaded one. Consumed (reset to false) once the reload runs.
    _needsLibraryReload: false,

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

    // Prepares the chart layer before rendering — call this on every tab/cage/period
    // switch, not just when a chart is detected broken. Destroys live instances and
    // bumps the generation counter synchronously (so racing renders no-op by generation),
    // returning a Promise; render inside its .then(). Re-downloads a clean Chart.js
    // module only when a recovery flagged _needsLibraryReload (see _verifyBarPainted);
    // otherwise it reuses the already-loaded module, keeping normal renders fast.
    prepareForRender() {
        this._generation++;
        Object.keys(this._instances).forEach(id => this.destroy(id));
        // Fast path: Chart.js is already downloaded and executing. Unless a stuck-paint
        // recovery flagged the module dirty, re-downloading/re-executing it here makes
        // every page load (and every analytics tab/cage/period switch) pay ~300ms plus a
        // ~205KB round-trip for a module that was already rendering fine. The recovery
        // paths in _verifyBarPainted set _needsLibraryReload when a chart genuinely fails
        // to paint — that is the only case that guarantees the heavy reload, and it's
        // what the self-heal actually relies on. The synchronous generation bump and
        // instance teardown still happen here, so the race protection that used
        // prepareForRender's ordering is preserved.
        if (!this._needsLibraryReload) {
            return Promise.resolve();
        }
        this._needsLibraryReload = false;
        return this._reloadChartJsLibrary();
    },

    create(id, config, _retryCount) {
        _retryCount = _retryCount || 0;
        this.destroy(id);
        const canvas = document.getElementById(id);
        if (!canvas) return null;
        // Fallback: Chart.js's own global registry may still hold a live instance for this
        // canvas even after LayRateChart.destroy() above was a no-op (because the primary
        // destroy's inst.destroy() threw and was silently caught, leaving _instances[id]
        // deleted but Chart.instances intact). Check directly via Chart.getChart() so we
        // don't rely on LayRateChart's bookkeeping.
        const orphaned = typeof Chart !== 'undefined' && Chart.getChart(canvas);
        if (orphaned) {
            try { orphaned.destroy(); } catch (e) { /* already gone */ }
        }
        this._configs[id] = config;
        // Turbo cache restores (back/forward preview, <html data-turbo-preview>)
        // must not replay the entry animation: render this one with animation
        // off, without touching the stored config (fresh visits keep animating).
        var renderConfig = config;
        if (document.documentElement.hasAttribute('data-turbo-preview')) {
            renderConfig = Object.assign({}, config, {
                options: Object.assign({}, config.options, { animation: false })
            });
        }
        try {
            const instance = new Chart(canvas, renderConfig);
            this._instances[id] = instance;
            // Keep an open full-screen copy of this chart in step (chart-fullscreen.js).
            if (window.LayRateChartFullscreen) window.LayRateChartFullscreen.sync(id);
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

    // Update-in-place for toggles/filters on the same canvas: when a live
    // chart of the same type already exists, swap labels + datasets (+ the
    // complete options, so legend toggles still apply) and morph via
    // chart.update() instead of replaying the entry animation. Callers must
    // only use this when the chart type is unchanged (true for every current
    // call site: period/compare/forecast toggles and same-canvas refetches).
    // Anything else (missing canvas, type change, no live instance) falls
    // back to create(), which keeps the entry animation on first creation.
    setData(id, config) {
        const canvas = document.getElementById(id);
        const live = (this._instances[id] && this._instances[id].canvas === canvas)
            ? this._instances[id]
            : (typeof Chart !== 'undefined' && canvas ? Chart.getChart(canvas) : null);
        if (live && canvas && config && config.data && live.config && live.config.type === config.type) {
            try {
                live.config.data.labels = config.data.labels;
                live.config.data.datasets = config.data.datasets;
                if (config.options) live.config.options = config.options;
                live.update();
                this._instances[id] = live;
                this._configs[id] = config;
                if (window.LayRateChartFullscreen) window.LayRateChartFullscreen.sync(id);
                return live;
            } catch (e) {
                console.error('[LayRateChart] setData failed for "' + id + '", recreating:', e);
            }
        }
        return this.create(id, config);
    },

    _verifyBarPainted(id, config, canvas, instance, retryCount) {
        if (this._instances[id] !== instance) return; // superseded by a newer render already
        // Guard: a chart inside a display:none section / zero-area canvas can never
        // measure a bar geometry, so "null base everywhere" there is expected — not the
        // stuck-paint corruption this self-heal exists for. Bailing prevents an
        // eager-loaded hidden-section chart from repeatedly tearing down every chart and
        // re-downloading Chart.js. Such charts paint themselves once their section is
        // shown, because Chart.js re-measures on the container resize.
        if (canvas.offsetParent === null || canvas.clientWidth === 0 || canvas.clientHeight === 0) return;
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
            this._needsLibraryReload = true;
            this._reloadChartJsLibrary()
                .then(() => {
                    if (gen !== this._generation) return; // navigated away mid-recovery
                    // This reload already produced the guaranteed-fresh module; let the
                    // recovery hook's own prepareForRender() take the fast path instead of
                    // loading chart.min.js a second time back-to-back.
                    this._needsLibraryReload = false;
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
            try { inst.destroy(); } catch (e) {
                console.warn('[LayRateChart] Chart.js native destroy() threw for "' + id + '":', e);
            }
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

// \u2500\u2500 "Use a saved note" pickers (components/saved-note-picker.blade.php) \u2500\u2500
// apply(): puts the picked note into the section's notes box (added on a new
// line when the box already has text). remember(): after an AJAX save that
// stays on the page, adds the note just typed to every picker of that
// category, so it can be reused without a reload.
window.LayRateNotes = {
    _label: function (text) {
        var flat = String(text).replace(/\s+/g, ' ').trim();
        return flat.length > 70 ? flat.slice(0, 69) + '\u2026' : flat;
    },
    apply: function (select) {
        var text = select.value;
        select.value = '';
        if (!text) return;
        var selector = select.getAttribute('data-note-target');
        var target = (select.form && select.form.querySelector(selector)) || document.querySelector(selector);
        if (!target) return;
        var current = target.value.trim();
        var lines = current.split(/\r?\n/).map(function (l) { return l.trim(); });
        if (lines.indexOf(text.trim()) === -1) {
            var sep = target.tagName === 'TEXTAREA' ? '\n' : '; ';
            target.value = current ? current + sep + text : text;
            target.dispatchEvent(new Event('input', { bubbles: true }));
        }
        target.focus();
    },
    remember: function (category, text) {
        text = String(text || '').trim();
        if (!text || text.length > 2000) return;
        var self = this;
        document.querySelectorAll('select[data-note-picker]').forEach(function (select) {
            if (select.getAttribute('data-note-category') !== category) return;
            for (var i = 0; i < select.options.length; i++) {
                if (select.options[i].value.toLowerCase() === text.toLowerCase()) return;
            }
            select.insertBefore(new Option(self._label(text), text), select.options[1] || null);
            select.disabled = false;
            select.options[0].textContent = select.getAttribute('data-placeholder') || 'Use a saved note\u2026';
        });
    },
    // Call after a form with a picker was saved by AJAX (before clearing it).
    rememberFromForm: function (form) {
        if (!form || !form.querySelectorAll) return;
        var self = this;
        form.querySelectorAll('select[data-note-picker]').forEach(function (select) {
            var target = form.querySelector(select.getAttribute('data-note-target'));
            if (target) self.remember(select.getAttribute('data-note-category'), target.value);
        });
    },
};
</script>

<x-confirm-modal />
<x-notification-toast />
<x-loading-modal />
<x-transaction-logger />

{{-- Global quick-actions dock (checklist on every authenticated page) --}}
<x-quick-actions-dock :dataCompleteness="$dockCompleteness ?? []" />

{{-- Shared dock behavior (delegated, init-guarded, Turbo-safe) --}}
<script src="/js/quick-dock.js?v={{ @filemtime(public_path('js/quick-dock.js')) }}" defer data-turbo-eval="false"></script>

{{-- Libraries needed by inline scripts in @stack('scripts').
     data-turbo-eval="false": they run once per full page load and stay loaded.
     Without it Turbo re-downloaded and re-ran all ~615 KB on every visit,
     right while the new page was painting. Only this layout loads Turbo, so
     every Turbo visit starts from a page that already ran them. --}}
<script src="/js/lucide.min.js" data-turbo-eval="false"></script>
<script src="/js/chart.min.js" data-turbo-eval="false"></script>
<script data-turbo-eval="false">
// App chart defaults (font sizes, legend, crosshair plugin, animation), once
// per full page load. See window.__applyChartDefaults above. full=false, the
// same as the recovery path: replacing scale.grid/layout on a Chart module
// that has only just loaded leaves bar charts unable to paint (confirmed
// again here: the Dashboard bar charts went blank), so grid and padding stay
// at Chart.js's own values, as they always were after a full page load.
if (typeof window.__applyChartDefaults === 'function') window.__applyChartDefaults(false);

// Icons: render only what is new. Lucide keeps data-lucide on the <svg> it
// creates, so every full createIcons() call replaced every icon on the page
// again (the Dashboard rebuilt ~830 icons to show ~70; Hens ~1,600 per visit).
// Each drawn icon is stamped with data-lucide-drawn="<name>". Icons whose
// stamp matches their current data-lucide are set aside during the call; an
// icon whose data-lucide was changed (chevron/eye toggles) still re-renders.
(function () {
    var L = window.lucide;
    if (!L || L.__layrateOnlyNew || typeof L.createIcons !== 'function') return;
    var original = L.createIcons;
    L.createIcons = function (options) {
        var opts = options || {};
        if (opts.nameAttr && opts.nameAttr !== 'data-lucide') return original.call(this, options);
        var root = opts.root || document;
        if (!root.querySelectorAll) return original.call(this, options);
        var all = root.querySelectorAll('[data-lucide]');
        var drawn = [];
        var pending = 0;
        for (var i = 0; i < all.length; i++) {
            var el = all[i];
            var name = el.getAttribute('data-lucide');
            if (el.getAttribute('data-lucide-drawn') === name) drawn.push([el, name]);
            else pending++;
        }
        if (!pending && !root.querySelector('[icon-name]')) return;
        for (var j = 0; j < drawn.length; j++) drawn[j][0].removeAttribute('data-lucide');
        try {
            return original.call(this, options);
        } finally {
            for (var k = 0; k < drawn.length; k++) drawn[k][0].setAttribute('data-lucide', drawn[k][1]);
            var svgs = root.querySelectorAll('svg[data-lucide]');
            for (var s = 0; s < svgs.length; s++) {
                var n = svgs[s].getAttribute('data-lucide');
                if (svgs[s].getAttribute('data-lucide-drawn') !== n) svgs[s].setAttribute('data-lucide-drawn', n);
            }
        }
    };
    L.__layrateOnlyNew = true;
})();
</script>

@stack('scripts')
</body>
</html>
