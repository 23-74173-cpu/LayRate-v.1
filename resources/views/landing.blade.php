<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="LayRate — offline poultry farm management. Egg production, environment, feed, flock health and ML forecasts in one real-time system.">
    <title>LayRate — Smart Poultry Farm Management</title>

    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <meta name="theme-color" content="#1a2342">
    <meta property="og:title" content="LayRate — Smart Poultry Farm Management">
    <meta property="og:description" content="Offline poultry farm management: egg production, environment, feed, flock health and ML forecasts.">
    <meta property="og:image" content="/images/layrate-logo.png">

    <link rel="stylesheet" href="/css/inter.css">
    <link href="/css/tailwind.css" rel="stylesheet">
    <script src="/js/lucide.min.js" defer></script>

    <style>
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background-color: #F0F0F0; overscroll-behavior: none; overflow-x: clip; }
        :focus-visible { outline: 2px solid var(--color-navy); outline-offset: 2px; border-radius: 4px; }
        #features, #how-it-works, #tech { scroll-margin-top: 4.5rem; }

        /* ── Hero entrance (load-triggered, never scroll-gated) ── */
        @keyframes heroEnter {
            from { opacity: 0; transform: translateY(22px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .hero-enter { animation: heroEnter 0.85s cubic-bezier(.16,1,.3,1) both; }
        .hero-enter-d1 { animation-delay: 0.08s; }
        .hero-enter-d2 { animation-delay: 0.18s; }
        .hero-enter-d3 { animation-delay: 0.3s; }

        /* ── Scroll reveal: SAFE BY DEFAULT.
              Base state = fully visible. JS opts in (adds .js-reveal to <html>)
              only when IntersectionObserver exists and motion is allowed.
              One-shot reveal → .is-visible. A 2.5s timer + catch-all force
              everything visible so cards can never stick faded/clipped. ── */
        .reveal { opacity: 1; transform: none; }
        html.js-reveal .reveal {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity 0.7s ease, transform 0.7s cubic-bezier(.16,1,.3,1);
            will-change: opacity, transform;
        }
        html.js-reveal .reveal.is-visible {
            opacity: 1;
            transform: none;
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .hero-enter { animation: none !important; }
            html.js-reveal .reveal { opacity: 1 !important; transform: none !important; transition: none !important; }
        }

        /* ── Hero depth layers ── */
        .hero-glow {
            background: radial-gradient(60% 60% at 30% 20%, rgba(98,174,240,0.35) 0%, rgba(98,174,240,0) 70%),
                        radial-gradient(50% 50% at 85% 15%, rgba(214,182,246,0.25) 0%, rgba(214,182,246,0) 70%),
                        radial-gradient(45% 45% at 70% 90%, rgba(0, 45, 94,0.22) 0%, rgba(0, 45, 94,0) 70%);
            will-change: transform;
        }
        .hero-spotlight {
            background: radial-gradient(420px circle at var(--mx, 50%) var(--my, 20%), rgba(255,255,255,0.10), transparent 70%);
        }
        /* Egg-dot grid: agricultural-tech texture, pure CSS, near-zero cost */
        .hero-egg-grid {
            background-image: radial-gradient(rgba(255,255,255,0.14) 1.2px, transparent 1.3px);
            background-size: 26px 26px;
            mask-image: radial-gradient(70% 70% at 50% 30%, black 30%, transparent 100%);
            -webkit-mask-image: radial-gradient(70% 70% at 50% 30%, black 30%, transparent 100%);
        }
        @keyframes driftSlow {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-14px) rotate(4deg); }
        }
        .float-shape { animation: driftSlow 9s ease-in-out infinite; }
        .float-shape-2 { animation-delay: -4.5s; }

        /* ── Nav ── */
        header { transition: box-shadow 0.3s ease, background-color 0.3s ease; }
        #site-header.is-scrolled {
            background-color: #ffffff;
            box-shadow: 0 8px 28px -12px rgba(20,30,60,0.22);
        }
        .nav-link { position: relative; }
        .nav-link::after {
            content: ''; position: absolute; left: 0; right: 0; bottom: -4px; height: 2px;
            background: var(--color-navy); transform: scaleX(0); transform-origin: left;
            transition: transform 0.25s ease;
        }
        .nav-link:hover::after, .nav-link.active::after { transform: scaleX(1); }
        .nav-link.active { color: #1f1f1f; }

        /* ── Scroll progress bar (bottom edge of header) ── */
        #scroll-progress { position: absolute; left: 0; bottom: -1px; height: 2px; width: 0%; background: linear-gradient(90deg, var(--color-navy), #62aef0); }

        /* ── Cards ── */
        .lift-card { transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease, border-color 0.25s ease; }
        .lift-card:hover { transform: translateY(-4px); border-color: rgba(0, 45, 94,0.35); box-shadow: 0 16px 36px -12px rgba(20,30,60,0.22); }
        .lift-card:focus-within { border-color: rgba(0, 45, 94,0.5); }

        /* ── How-it-works ── */
        .step-numeral {
            font-size: 60px; font-weight: 800; line-height: 1;
            color: rgba(31,31,31,0.08);
            letter-spacing: -2px;
        }
        .step-icon { transition: background-color 0.4s ease, color 0.4s ease, transform 0.3s ease; }
        .step-card.is-active .step-icon { background: var(--color-secondary); color: #ffffff; }
        .how-dot { transition: background-color 0.4s ease, box-shadow 0.4s ease; }
        .how-dot.is-active { background: var(--color-secondary); box-shadow: 0 0 0 4px rgba(33,49,131,0.15); }

        /* ── Data-flow pulse: a soft dot travelling down the connector ── */
        .flow-rail { position: absolute; left: 27px; top: 14px; bottom: 14px; width: 2px; background: #e6e6e6; border-radius: 9999px; overflow: visible; }
        @keyframes flowPulse {
            0% { top: 0; opacity: 0; }
            12% { opacity: 1; }
            88% { opacity: 1; }
            100% { top: calc(100% - 10px); opacity: 0; }
        }
        .flow-pulse {
            position: absolute; left: 50%; transform: translateX(-50%);
            width: 10px; height: 10px; border-radius: 9999px;
            background: var(--color-navy); box-shadow: 0 0 0 4px rgba(0, 45, 94,0.18);
            animation: flowPulse 2.8s cubic-bezier(.45,0,.35,1) infinite;
        }

        /* ── Buttons: clear hover / focus / active ── */
        .btn-primary { transition: background-color 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease, filter 0.2s ease; }
        .btn-primary:hover { filter: brightness(0.9); box-shadow: 0 12px 28px -10px rgba(0,45,94,0.5); }
        .btn-primary:active { transform: scale(0.96); }
        .btn-primary:focus-visible { outline: 2px solid #ffffff; outline-offset: 2px; box-shadow: 0 0 0 4px rgba(0, 45, 94,0.5); }
        .btn-ghost { transition: background-color 0.2s ease, transform 0.15s ease, border-color 0.2s ease; }
        .btn-ghost:hover { background-color: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.5); }
        .btn-ghost:active { transform: scale(0.96); }
        .btn-ghost:focus-visible { outline: 2px solid #ffffff; outline-offset: 2px; }

        /* ── Full-page circle-wipe transition to /login ── */
        #page-wipe {
            position: fixed; inset: 0; z-index: 9999; pointer-events: none;
            background-color: var(--color-sidebar-bg);
            clip-path: circle(0% at 50% 50%);
        }
        #page-wipe.animate { transition: clip-path 0.65s cubic-bezier(.76,0,.24,1); }
        @media (prefers-reduced-motion: reduce) {
            #page-wipe { display: none; }
            .float-shape { animation: none !important; }
            .flow-pulse { animation: none !important; display: none; }
            .animate-ping { animation: none !important; }
        }
    </style>
    <noscript><style>.reveal { opacity: 1 !important; transform: none !important; }</style></noscript>
</head>
<body class="text-ink antialiased">

<div id="page-wipe"></div>
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[10000] focus:bg-white focus:px-3 focus:py-2 focus:rounded-lg focus:text-sm">Skip to content</a>

{{-- ── NAV ─────────────────────────────────────────────────────────────── --}}
<header id="site-header" class="sticky top-0 z-50 bg-white border-b border-hairline">
    <div id="scroll-progress" aria-hidden="true"></div>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
        <a href="{{ route('landing') }}" class="flex items-center gap-2.5 shrink-0 rounded-lg" aria-label="LayRate home">
            <x-logo size="h-10" :eager="true" />
            <span class="text-title text-ink leading-none">LayRate</span>
        </a>

        <nav class="hidden md:flex items-center gap-8" aria-label="Primary">
            <a href="#features" data-nav-link="features" class="nav-link text-body-sm text-ink-muted hover:text-ink transition-colors">Features</a>
            <a href="#how-it-works" data-nav-link="how-it-works" class="nav-link text-body-sm text-ink-muted hover:text-ink transition-colors">How it works</a>
            <a href="#tech" data-nav-link="tech" class="nav-link text-body-sm text-ink-muted hover:text-ink transition-colors">Technology</a>
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ route('login') }}" data-page-transition
               class="btn-primary hidden sm:inline-flex items-center gap-1.5 bg-navy text-on-primary text-button px-5 py-2.5 rounded-full shadow-soft">
                Sign In
                <i data-lucide="arrow-right" class="w-4 h-4" aria-hidden="true"></i>
            </a>
            <button id="menu-button" type="button" class="md:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg border border-hairline bg-surface text-ink" aria-expanded="false" aria-controls="mobile-menu" aria-label="Open menu">
                <i data-lucide="menu" class="w-5 h-5" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <div id="mobile-menu" class="md:hidden hidden border-t border-hairline bg-white">
        <nav class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex flex-col gap-1" aria-label="Mobile">
            <a href="#features" class="rounded-lg px-3 py-2.5 text-body-sm text-ink hover:bg-canvas-soft">Features</a>
            <a href="#how-it-works" class="rounded-lg px-3 py-2.5 text-body-sm text-ink hover:bg-canvas-soft">How it works</a>
            <a href="#tech" class="rounded-lg px-3 py-2.5 text-body-sm text-ink hover:bg-canvas-soft">Technology</a>
            <a href="{{ route('login') }}" data-page-transition class="btn-primary mt-1 inline-flex items-center justify-center gap-1.5 bg-navy text-on-primary text-button px-5 py-2.5 rounded-full">
                Sign In
                <i data-lucide="arrow-right" class="w-4 h-4" aria-hidden="true"></i>
            </a>
        </nav>
    </div>
</header>

<main id="main">
{{-- ── HERO ────────────────────────────────────────────────────────────── --}}
<section class="relative overflow-hidden" style="background-color: var(--color-sidebar-bg); background-image: linear-gradient(180deg, rgba(255,255,255,0.045) 0%, rgba(255,255,255,0) 40%, rgba(0,0,0,0.10) 100%);" aria-labelledby="hero-heading">
    <div id="hero-glow" class="absolute inset-0 hero-glow" aria-hidden="true"></div>
    <div class="absolute inset-0 hero-egg-grid opacity-70" aria-hidden="true"></div>
    <div class="absolute inset-0 hero-spotlight" aria-hidden="true"></div>
    {{-- Floating agricultural-tech shapes, low opacity, pure CSS/SVG --}}
    <div class="pointer-events-none absolute -left-16 top-24 w-64 h-64 rounded-full bg-white/[0.05] blur-2xl float-shape" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -right-20 bottom-10 w-80 h-80 rounded-full bg-sticker-sky/10 blur-3xl float-shape float-shape-2" aria-hidden="true"></div>
    <svg class="pointer-events-none absolute right-[8%] top-16 w-24 h-28 text-white/[0.08] float-shape hidden sm:block" viewBox="0 0 100 130" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><ellipse cx="50" cy="65" rx="38" ry="55"/></svg>
    <svg class="pointer-events-none absolute left-[6%] bottom-24 w-14 h-16 text-white/[0.07] float-shape float-shape-2 hidden sm:block" viewBox="0 0 100 130" fill="none" stroke="currentColor" stroke-width="4" aria-hidden="true"><ellipse cx="50" cy="65" rx="38" ry="55"/></svg>

    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 pt-16 pb-16 lg:pt-24 lg:pb-24">
        <div class="max-w-2xl mx-auto text-center">
            <span class="hero-enter inline-flex items-center gap-1.5 text-eyebrow text-white/80 bg-white/10 border border-white/15 rounded-full px-3 py-1.5 mb-6">
                <i data-lucide="sparkles" class="w-3.5 h-3.5" aria-hidden="true"></i>
                SMART POULTRY OPERATIONS
            </span>
            <h1 id="hero-heading" class="hero-enter hero-enter-d1 text-4xl sm:text-heading-1 lg:text-display-2 text-white mb-5 text-balance">
                Every egg counted.<br>Every hen accounted for.
            </h1>
            <p class="hero-enter hero-enter-d2 text-body-md text-white/75 mx-auto mb-8 max-w-xl text-pretty">
                LayRate unifies egg production, environmental monitoring, feed, and flock
                health into one real-time system — powered by IoT sensors and
                machine-learning forecasts, built for the modern layer farm.
            </p>
            <div class="hero-enter hero-enter-d3 flex flex-wrap items-center justify-center gap-4">
                <a href="{{ route('login') }}" data-page-transition
                   class="btn-primary inline-flex items-center gap-2 bg-navy text-on-primary text-button px-6 py-3 rounded-full shadow-elevated">
                    Sign In to Dashboard
                    <i data-lucide="arrow-right" class="w-4 h-4" aria-hidden="true"></i>
                </a>
                <a href="#features"
                   class="btn-ghost inline-flex items-center gap-2 text-button text-white px-6 py-3 rounded-full border border-white/25">
                    Explore Features
                </a>
            </div>
            <p class="hero-enter hero-enter-d3 mt-5 text-caption text-white/50">Offline-ready · Built for farm owners and operators</p>
        </div>

        {{-- Product preview: mock dashboard cluster in the app's KPI card style.
             Clearly sample data, HTML/CSS only. --}}
        <div class="hero-enter hero-enter-d3 relative max-w-4xl mx-auto mt-12 lg:mt-16" role="img" aria-label="Preview of the LayRate dashboard showing sample key metrics: HDEP, eggs today, temperature and humidity. Sample data.">
            <div class="absolute -inset-3 bg-white/10 rounded-3xl blur-2xl" aria-hidden="true"></div>
            <div class="relative bg-surface rounded-2xl border border-white/20 shadow-elevated overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-4 sm:px-5 py-3 border-b border-hairline bg-canvas-soft/60">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="relative flex h-2.5 w-2.5 shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-ok-text opacity-40"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-ok-text"></span>
                        </span>
                        <span class="text-body-sm font-semibold text-ink truncate">Coop Overview</span>
                    </div>
                    <span class="inline-flex items-center gap-1 text-caption text-ink-muted bg-surface border border-hairline rounded-full px-2.5 py-1 shrink-0">
                        <i data-lucide="flask-conical" class="w-3.5 h-3.5" aria-hidden="true"></i>
                        Sample data
                    </span>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 p-4 sm:p-5 bg-surface">
                    <div class="rounded-2xl border border-hairline bg-white p-3.5 flex items-center gap-3">
                        <span class="kpi-icon-tile shrink-0" aria-hidden="true"><i data-lucide="percent"></i></span>
                        <span class="min-w-0"><span class="kpi-label">HDEP</span><span class="block text-xl font-bold text-ink leading-tight">87.4%</span></span>
                    </div>
                    <div class="rounded-2xl border border-hairline bg-white p-3.5 flex items-center gap-3">
                        <span class="kpi-icon-tile shrink-0" aria-hidden="true"><i data-lucide="egg"></i></span>
                        <span class="min-w-0"><span class="kpi-label">Eggs today</span><span class="block text-xl font-bold text-ink leading-tight">1,284</span></span>
                    </div>
                    <div class="rounded-2xl border border-hairline bg-white p-3.5 flex items-center gap-3">
                        <span class="kpi-icon-tile shrink-0" aria-hidden="true"><i data-lucide="thermometer"></i></span>
                        <span class="min-w-0"><span class="kpi-label">Temperature</span><span class="block text-xl font-bold text-ink leading-tight">24.6°C</span></span>
                    </div>
                    <div class="rounded-2xl border border-hairline bg-white p-3.5 flex items-center gap-3">
                        <span class="kpi-icon-tile shrink-0" aria-hidden="true"><i data-lucide="droplets"></i></span>
                        <span class="min-w-0"><span class="kpi-label">Humidity</span><span class="block text-xl font-bold text-ink leading-tight">62%</span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── CAPABILITY STRIP ────────────────────────────────────────────────── --}}
<section class="border-b border-hairline bg-surface" aria-label="Capabilities">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 grid grid-cols-2 lg:grid-cols-4 gap-y-8 gap-x-6 text-center">
        <div class="reveal px-2 lg:border-l lg:border-hairline lg:first:border-l-0 lg:first:pl-2">
            <div class="mx-auto mb-2 w-9 h-9 rounded-lg bg-navy/10 text-navy flex items-center justify-center"><i data-lucide="activity" class="w-5 h-5" aria-hidden="true"></i></div>
            <div class="text-heading-3 text-navy">Real-Time</div>
            <div class="text-caption text-ink-muted mt-1">Sensor synchronization</div>
        </div>
        <div class="reveal px-2 lg:border-l lg:border-hairline" style="transition-delay: 60ms;">
            <div class="mx-auto mb-2 w-9 h-9 rounded-lg bg-navy/10 text-navy flex items-center justify-center"><i data-lucide="shield-check" class="w-5 h-5" aria-hidden="true"></i></div>
            <div class="text-heading-3 text-navy">Role-Based</div>
            <div class="text-caption text-ink-muted mt-1">Admin &amp; operator access</div>
        </div>
        <div class="reveal px-2 lg:border-l lg:border-hairline" style="transition-delay: 120ms;">
            <div class="mx-auto mb-2 w-9 h-9 rounded-lg bg-navy/10 text-navy flex items-center justify-center"><i data-lucide="brain-circuit" class="w-5 h-5" aria-hidden="true"></i></div>
            <div class="text-heading-3 text-navy">ML-Driven</div>
            <div class="text-caption text-ink-muted mt-1">Production forecasts</div>
        </div>
        <div class="reveal px-2 lg:border-l lg:border-hairline" style="transition-delay: 180ms;">
            <div class="mx-auto mb-2 w-9 h-9 rounded-lg bg-navy/10 text-navy flex items-center justify-center"><i data-lucide="layers" class="w-5 h-5" aria-hidden="true"></i></div>
            <div class="text-heading-3 text-navy">Full Lifecycle</div>
            <div class="text-caption text-ink-muted mt-1">Hen &amp; flock tracking</div>
        </div>
    </div>
</section>

{{-- ── FEATURES ────────────────────────────────────────────────────────── --}}
<section id="features" class="max-w-6xl mx-auto px-4 sm:px-6 py-20 lg:py-28" aria-labelledby="features-heading">
    <div class="max-w-2xl mb-14 reveal">
        <span class="inline-flex items-center gap-2 text-eyebrow text-navy"><span class="w-1.5 h-1.5 rounded-full bg-sticker-orange" aria-hidden="true"></span>FEATURES</span>
        <h2 id="features-heading" class="text-heading-2 text-ink mt-2 mb-3">Everything your farm needs, in one place.</h2>
        <p class="text-body-md text-ink-muted">
            From the cage floor to the forecast, LayRate connects hardware, operators,
            and data into a single, coherent system.
        </p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @php
        $features = [
            ['icon' => 'egg', 'tile' => 'bg-[#dcebfa] text-navy border-[#cfe3f8]', 'title' => 'Production Tracking', 'desc' => 'Automatic and manual egg logging per cage and slot, with hen-day egg percentage (HDEP) calculated instantly.'],
            ['icon' => 'cpu', 'tile' => 'bg-[#dcebfa] text-navy border-[#cfe3f8]', 'title' => 'IoT Hardware Integration', 'desc' => 'IR break-beam sensors auto-count eggs and DHT22 sensors stream live temperature and humidity — no manual entry required.'],
            ['icon' => 'thermometer', 'tile' => 'bg-[#dcebfa] text-navy border-[#cfe3f8]', 'title' => 'Environmental Monitoring', 'desc' => 'Live temperature and humidity tracking per cage, with configurable thresholds and instant alerts when conditions drift.'],
            ['icon' => 'leaf', 'tile' => 'bg-[#dcebfa] text-navy border-[#cfe3f8]', 'title' => 'Feed & Nutrition', 'desc' => 'Track feed batches, crude protein content, and consumption — with automatic feed conversion ratio (FCR) calculations.'],
            ['icon' => 'bird', 'tile' => 'bg-[#dcebfa] text-navy border-[#cfe3f8]', 'title' => 'Flock & Health Records', 'desc' => 'Full hen lifecycle from placement to removal — health events, weight checks, mortality logs, and culling records.'],
            ['icon' => 'trending-up', 'tile' => 'bg-[#dcebfa] text-navy border-[#cfe3f8]', 'title' => 'ML-Powered Forecasting', 'desc' => 'Predict future egg production using historical trends, environmental data, and machine-learning models.'],
        ];
        @endphp

        @foreach($features as $i => $f)
        <div class="reveal lift-card group bg-surface border border-hairline rounded-2xl p-6 shadow-soft" style="transition-delay: {{ $i * 60 }}ms;">
            <div class="w-12 h-12 rounded-xl border {{ $f['tile'] }} flex items-center justify-center mb-5 transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-6">
                <i data-lucide="{{ $f['icon'] }}" class="w-6 h-6" aria-hidden="true"></i>
            </div>
            <h3 class="text-title text-ink mb-2">{{ $f['title'] }}</h3>
            <p class="text-body-sm text-ink-muted max-w-[38ch]">{{ $f['desc'] }}</p>
        </div>
        @endforeach
    </div>
</section>

{{-- ── HOW IT WORKS ────────────────────────────────────────────────────── --}}
<section id="how-it-works" class="bg-canvas-soft border-y border-hairline" aria-labelledby="how-heading">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-20 lg:py-28">
        <div class="max-w-2xl mb-14 reveal">
            <span class="inline-flex items-center gap-2 text-eyebrow text-navy"><span class="w-1.5 h-1.5 rounded-full bg-sticker-orange" aria-hidden="true"></span>HOW IT WORKS</span>
            <h2 id="how-heading" class="text-heading-2 text-ink mt-2 mb-3">From the cage to the dashboard, automatically.</h2>
        </div>

        {{-- Desktop connector: same 3-col grid + gaps as the cards below, so each
             marker sits exactly over its card's center by construction. The fill
             bar runs behind the markers; steps light up as it reaches them. --}}
        <div class="hidden md:block relative mb-8" aria-hidden="true">
            <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 h-1 bg-hairline rounded-full overflow-hidden">
                <div id="how-fill" class="absolute inset-y-0 left-0 bg-secondary rounded-full" style="width: 0%"></div>
            </div>
            <div class="relative grid grid-cols-3 md:gap-6 lg:gap-8">
                <div class="flex justify-center"><span class="how-dot block w-3 h-3 rounded-full bg-hairline ring-4 ring-[#F0F0F0]"></span></div>
                <div class="flex justify-center"><span class="how-dot block w-3 h-3 rounded-full bg-hairline ring-4 ring-[#F0F0F0]"></span></div>
                <div class="flex justify-center"><span class="how-dot block w-3 h-3 rounded-full bg-hairline ring-4 ring-[#F0F0F0]"></span></div>
            </div>
        </div>

        <div class="grid md:grid-cols-3 gap-8 md:gap-6 lg:gap-8">
            @php
            $steps = [
                ['n' => '01', 'icon' => 'radio', 'title' => 'Sensors Capture', 'desc' => 'IR break-beam and DHT22 hardware continuously read egg counts, temperature, and humidity straight from the cage.'],
                ['n' => '02', 'icon' => 'refresh-cw', 'title' => 'LayRate Logs & Analyzes', 'desc' => 'Readings sync automatically into per-cage, per-slot records — cross-checked against manual entries and flagged for review.'],
                ['n' => '03', 'icon' => 'target', 'title' => 'Your Team Acts', 'desc' => 'Dashboards, alerts, and forecasts turn raw sensor data into decisions — before a problem becomes a loss.'],
            ];
            @endphp

            @foreach($steps as $i => $s)
            <div class="step-card reveal lift-card group relative bg-surface border border-hairline rounded-2xl p-6 pt-7 shadow-soft" style="transition-delay: {{ $i * 80 }}ms;">
                <span class="step-numeral absolute top-3 right-4 select-none transition-transform duration-300 group-hover:scale-110" aria-hidden="true">{{ $s['n'] }}</span>
                <div class="step-icon w-12 h-12 rounded-xl bg-secondary/10 text-secondary flex items-center justify-center mb-5 relative transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-6">
                    <i data-lucide="{{ $s['icon'] }}" class="w-6 h-6" aria-hidden="true"></i>
                </div>
                <h3 class="text-title text-ink mb-2 relative">{{ $s['title'] }}</h3>
                <p class="text-body-sm text-ink-muted relative max-w-[36ch]">{{ $s['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── TECH STACK ──────────────────────────────────────────────────────── --}}
<section id="tech" class="max-w-6xl mx-auto px-4 sm:px-6 py-20 lg:py-28" aria-labelledby="tech-heading">
    <div class="grid lg:grid-cols-2 gap-14 items-center">
        <div class="reveal">
            <span class="inline-flex items-center gap-2 text-eyebrow text-navy"><span class="w-1.5 h-1.5 rounded-full bg-sticker-orange" aria-hidden="true"></span>TECHNOLOGY</span>
            <h2 id="tech-heading" class="text-heading-2 text-ink mt-2 mb-4">Built on real hardware, backed by real models.</h2>
            <p class="text-body-md text-ink-muted mb-8 max-w-[52ch]">
                LayRate isn't just a form — it's wired directly into the barn. Sensor
                readings flow into the same database that trains the forecasting model,
                so every prediction is grounded in your farm's own history.
            </p>

            <ul class="space-y-5">
                <li class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-ok-bg text-ok-text border border-ok-border flex items-center justify-center shrink-0 mt-0.5">
                        <i data-lucide="scan-line" class="w-5 h-5" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="text-body-md text-ink font-medium">IR break-beam egg counters</div>
                        <div class="text-body-sm text-ink-muted">Auto-detect and log eggs the moment they're laid, per slot.</div>
                    </div>
                </li>
                <li class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-watch-bg text-watch-text border border-watch-border flex items-center justify-center shrink-0 mt-0.5">
                        <i data-lucide="thermometer" class="w-5 h-5" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="text-body-md text-ink font-medium">DHT22 environmental sensors</div>
                        <div class="text-body-sm text-ink-muted">Stream live temperature and humidity for every cage, around the clock.</div>
                    </div>
                </li>
                <li class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-navy/10 text-navy border border-[#cfe3f8] flex items-center justify-center shrink-0 mt-0.5">
                        <i data-lucide="brain-circuit" class="w-5 h-5" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="text-body-md text-ink font-medium">Machine-learning forecasts</div>
                        <div class="text-body-sm text-ink-muted">Statistical and gradient-boosted models trained on your farm's own production history.</div>
                    </div>
                </li>
            </ul>
        </div>

        <div class="reveal flex justify-center" style="transition-delay: 100ms;">
            <div class="relative w-full max-w-sm">
                <div class="absolute -inset-4 bg-linear-to-br from-sticker-sky/20 to-sticker-purple/20 rounded-2xl blur-2xl" aria-hidden="true"></div>
                <div class="relative bg-surface border border-hairline rounded-2xl shadow-soft p-6">
                    <div class="text-eyebrow text-ink-muted mb-4">DATA FLOW</div>
                    <ol class="relative flex flex-col gap-1.5">
                        <div class="flow-rail" aria-hidden="true"><span class="flow-pulse"></span></div>
                        <li class="relative flex items-center gap-3 bg-canvas-soft rounded-xl pl-12 pr-3 py-2.5">
                            <i data-lucide="cpu" class="w-4 h-4 text-cage-a shrink-0" aria-hidden="true"></i>
                            <span class="text-body-sm text-ink">Hardware sensors</span>
                        </li>
                        <li class="flex justify-start pl-11" aria-hidden="true"><i data-lucide="arrow-down" class="w-4 h-4 text-ink-faint"></i></li>
                        <li class="relative flex items-center gap-3 bg-canvas-soft rounded-xl pl-12 pr-3 py-2.5">
                            <i data-lucide="database" class="w-4 h-4 text-cage-b shrink-0" aria-hidden="true"></i>
                            <span class="text-body-sm text-ink">LayRate database</span>
                        </li>
                        <li class="flex justify-start pl-11" aria-hidden="true"><i data-lucide="arrow-down" class="w-4 h-4 text-ink-faint"></i></li>
                        <li class="relative flex items-center gap-3 bg-canvas-soft rounded-xl pl-12 pr-3 py-2.5">
                            <i data-lucide="trending-up" class="w-4 h-4 text-cage-c shrink-0" aria-hidden="true"></i>
                            <span class="text-body-sm text-ink">Forecast model</span>
                        </li>
                        <li class="flex justify-start pl-11" aria-hidden="true"><i data-lucide="arrow-down" class="w-4 h-4 text-ink-faint"></i></li>
                        <li class="relative flex items-center gap-3 bg-navy/10 border border-navy/25 rounded-xl pl-12 pr-3 py-3 ring-1 ring-navy/20">
                            <i data-lucide="layout-dashboard" class="w-4 h-4 text-navy shrink-0" aria-hidden="true"></i>
                            <span class="text-body-sm text-ink font-semibold">Your dashboard</span>
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-navy ml-auto shrink-0" aria-hidden="true"></i>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── CTA BAND ─────────────────────────────────────────────────────────── --}}
<section class="relative overflow-hidden" style="background-color: var(--color-sidebar-bg); background-image: linear-gradient(180deg, rgba(255,255,255,0.045) 0%, rgba(255,255,255,0) 40%, rgba(0,0,0,0.10) 100%);" aria-labelledby="cta-heading">
    <div class="absolute inset-0 hero-glow" aria-hidden="true"></div>
    <div class="absolute inset-0 hero-egg-grid opacity-50" aria-hidden="true"></div>
    <div class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[36rem] h-[16rem] bg-navy/25 blur-[100px] rounded-full" aria-hidden="true"></div>
    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 py-20 lg:py-24 text-center reveal">
        <span class="inline-flex items-center gap-1.5 text-eyebrow text-white/70 bg-white/10 border border-white/15 rounded-full px-3 py-1.5 mb-5">
            <i data-lucide="egg" class="w-3.5 h-3.5" aria-hidden="true"></i>
            FOR LAYER FARMS
        </span>
        <h2 id="cta-heading" class="text-heading-1 text-white mb-4 text-balance">Ready to see your flock in real time?</h2>
        <p class="text-body-md text-white/75 mb-8 max-w-lg mx-auto">
            Sign in to view live production, environment, and forecast data for your farm.
        </p>
        <a href="{{ route('login') }}" data-page-transition
            class="btn-primary inline-flex items-center gap-2 bg-white text-secondary text-button font-semibold px-7 py-3.5 rounded-full shadow-elevated hover:bg-canvas-soft">
            Sign In to LayRate
            <i data-lucide="arrow-right" class="w-4 h-4" aria-hidden="true"></i>
        </a>
    </div>
</section>
</main>

{{-- ── FOOTER ───────────────────────────────────────────────────────────── --}}
<footer class="bg-surface border-t border-hairline">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 flex flex-col gap-6">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-2.5">
                <x-logo size="h-10" />
                <div>
                    <div class="text-body-sm text-ink font-medium leading-tight">LayRate</div>
                    <div class="text-caption text-ink-muted leading-tight">Offline poultry farm management, built for the barn.</div>
                </div>
            </div>
            <nav class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2" aria-label="Footer">
                <a href="#features" class="text-body-sm text-ink-muted hover:text-ink transition-colors">Features</a>
                <a href="#how-it-works" class="text-body-sm text-ink-muted hover:text-ink transition-colors">How it works</a>
                <a href="#tech" class="text-body-sm text-ink-muted hover:text-ink transition-colors">Technology</a>
                <a href="{{ route('login') }}" class="text-body-sm text-ink-muted hover:text-ink transition-colors">Sign In</a>
            </nav>
        </div>
        <div class="flex flex-col sm:flex-row items-center justify-between gap-2 pt-6 border-t border-hairline">
            <p class="text-caption text-ink-faint">&copy; {{ date('Y') }} LayRate. All rights reserved.</p>
            <p class="text-caption text-ink-faint">Every egg counted. Every hen accounted for.</p>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide) lucide.createIcons();

    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ── Mobile menu ──
    var menuButton = document.getElementById('menu-button');
    var mobileMenu = document.getElementById('mobile-menu');
    if (menuButton && mobileMenu) {
        menuButton.addEventListener('click', function () {
            var open = mobileMenu.classList.toggle('hidden');
            var isOpen = !open;
            menuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            menuButton.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
        });
        mobileMenu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                mobileMenu.classList.add('hidden');
                menuButton.setAttribute('aria-expanded', 'false');
                menuButton.setAttribute('aria-label', 'Open menu');
            });
        });
    }

    // Everything below degrades to "content visible, no fancy motion".
    try {
        var header      = document.getElementById('site-header');
        var progressBar = document.getElementById('scroll-progress');
        var navLinks    = [].slice.call(document.querySelectorAll('[data-nav-link]'));
        var sections    = navLinks.map(function (l) { return document.getElementById(l.dataset.navLink); }).filter(Boolean);
        var heroGlow    = document.getElementById('hero-glow');
        var howSection  = document.getElementById('how-it-works');
        var howFill     = document.getElementById('how-fill');
        var howSteps    = [].slice.call(document.querySelectorAll('.step-card'));
        var howDots     = [].slice.call(document.querySelectorAll('.how-dot'));
        var revealEls   = [].slice.call(document.querySelectorAll('.reveal'));

        function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }

        function forceVisible() {
            revealEls.forEach(function (el) { el.classList.add('is-visible'); });
            if (howFill) howFill.style.width = '100%';
            howSteps.forEach(function (s) { s.classList.add('is-active'); });
            howDots.forEach(function (d) { d.classList.add('is-active'); });
        }

        if (reducedMotion) {
            forceVisible();
        } else if ('IntersectionObserver' in window) {
            // Opt in to hidden-until-revealed ONLY now that we know the
            // observer exists to reveal them again.
            document.documentElement.classList.add('js-reveal');

            var revealObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        revealObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
            revealEls.forEach(function (el) { revealObserver.observe(el); });

            // Scroll-spy nav highlight.
            var spyObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    var link = navLinks.filter(function (l) { return l.dataset.navLink === entry.target.id; })[0];
                    if (link) link.classList.toggle('active', entry.isIntersecting);
                });
            }, { rootMargin: '-40% 0px -55% 0px', threshold: 0 });
            sections.forEach(function (sec) { spyObserver.observe(sec); });

            // Safety net: never leave content hidden (e.g. observer stalls).
            setTimeout(forceVisible, 2500);
        } else {
            // No IntersectionObserver → stay in the visible baseline.
            forceVisible();
        }

        // ── Lightweight scroll effects (progress, header blur, hero parallax,
        //     how-it-works fill). rAF-throttled, transform/width only. ──
        var ticking = false;
        function onScroll() {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(function () {
                ticking = false;
                var vh = window.innerHeight;
                var scrollY = window.scrollY || window.pageYOffset;

                if (progressBar) {
                    var docH = document.documentElement.scrollHeight - vh;
                    progressBar.style.width = (docH > 0 ? clamp(scrollY / docH, 0, 1) * 100 : 0) + '%';
                }
                if (header) header.classList.toggle('is-scrolled', scrollY > 8);

                if (heroGlow && !reducedMotion) heroGlow.style.transform = 'translateY(' + (scrollY * 0.18) + 'px)';

                if (howSection && howFill) {
                    var hRect = howSection.getBoundingClientRect();
                    var hProgress = clamp((vh - hRect.top) / (hRect.height + vh * 0.3), 0, 1);
                    howFill.style.width = (hProgress * 100) + '%';
                    howSteps.forEach(function (s, i) {
                        s.classList.toggle('is-active', hProgress >= (i + 0.5) / howSteps.length);
                    });
                    howDots.forEach(function (d, i) {
                        d.classList.toggle('is-active', hProgress >= (i + 0.5) / howDots.length);
                    });
                }
            });
        }
        document.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        // ── Cursor-follow spotlight in hero (skip on touch devices) ──
        var isCoarsePointer = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
        if (!reducedMotion && !isCoarsePointer) {
            var spotlightLayer = document.querySelector('.hero-spotlight');
            var heroSection = spotlightLayer ? spotlightLayer.closest('section') : null;
            if (heroSection) {
                heroSection.addEventListener('mousemove', function (e) {
                    var rect = heroSection.getBoundingClientRect();
                    heroSection.style.setProperty('--mx', ((e.clientX - rect.left) / rect.width * 100) + '%');
                    heroSection.style.setProperty('--my', ((e.clientY - rect.top) / rect.height * 100) + '%');
                }, { passive: true });
            }
        }

        // ── Circle-wipe page transition into Sign In ──
        var wipe = document.getElementById('page-wipe');
        if (wipe && !reducedMotion) {
            document.querySelectorAll('[data-page-transition]').forEach(function (link) {
                link.addEventListener('click', function (e) {
                    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
                    e.preventDefault();
                    var href = link.getAttribute('href');
                    var x = e.clientX, y = e.clientY;
                    var radius = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y));
                    wipe.classList.add('animate');
                    wipe.style.clipPath = 'circle(0% at ' + x + 'px ' + y + 'px)';
                    requestAnimationFrame(function () {
                        wipe.style.clipPath = 'circle(' + radius + 'px at ' + x + 'px ' + y + 'px)';
                    });
                    setTimeout(function () { window.location.href = href; }, 650);
                });
            });
        }
    } catch (err) {
        document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('is-visible'); });
        if (window.console) console.error('Landing page controller failed, showing static fallback:', err);
    }
});
</script>
</body>
</html>
