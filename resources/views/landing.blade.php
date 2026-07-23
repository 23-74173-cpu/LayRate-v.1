<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LayRate — Smart Poultry Farm Management</title>

    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

    <link rel="stylesheet" href="/css/inter.css">
    <link href="/css/tailwind.css" rel="stylesheet">
    <script src="/js/lucide.min.js" defer></script>

    <style>
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background-color: #f6f5f4; }
        :focus-visible { outline: 2px solid #0075de; outline-offset: 2px; border-radius: 4px; }
        #features, #how-it-works, #tech { scroll-margin-top: 4.5rem; }

        /* ── Hero entrance (load-triggered, never scroll-gated) ── */
        @keyframes heroEnter {
            from { opacity: 0; transform: translateY(22px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .hero-enter { animation: heroEnter 0.85s cubic-bezier(.16,1,.3,1) both; }

        /* ── Scroll reveal: default = fully visible, always. The scroll
             controller below applies inline opacity/transform continuously
             tied to scroll position ONLY once it has actually initialized —
             so "JS never ran" and "reduced motion" both degrade to this same
             safe baseline with zero timing races. ── */
        .reveal { opacity: 1; transform: none; }

        @media (prefers-reduced-motion: reduce) {
            .hero-enter, .float-slow { animation: none !important; }
        }

        @keyframes floatSlow {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .float-slow { animation: floatSlow 6s ease-in-out infinite; }

        .hero-glow {
            background: radial-gradient(60% 60% at 30% 20%, rgba(98,174,240,0.35) 0%, rgba(98,174,240,0) 70%),
                        radial-gradient(50% 50% at 85% 15%, rgba(214,182,246,0.25) 0%, rgba(214,182,246,0) 70%);
            will-change: transform;
        }
        .hero-spotlight {
            background: radial-gradient(420px circle at var(--mx, 50%) var(--my, 20%), rgba(255,255,255,0.10), transparent 70%);
        }
        #hero-mockup { transition: transform 0.3s ease-out; }

        /* ── Nav ── */
        header { transition: box-shadow 0.3s ease, background-color 0.3s ease; }
        .nav-link { position: relative; }
        .nav-link::after {
            content: ''; position: absolute; left: 0; right: 0; bottom: -4px; height: 2px;
            background: #0075de; transform: scaleX(0); transform-origin: left;
            transition: transform 0.25s ease;
        }
        .nav-link:hover::after, .nav-link.active::after { transform: scaleX(1); }
        .nav-link.active { color: #1f1f1f; }

        /* ── Scroll progress bar (bottom edge of header) ── */
        #scroll-progress { position: absolute; left: 0; bottom: -1px; height: 2px; width: 0%; background: linear-gradient(90deg, #0075de, #62aef0); }

        /* ── Cards ── */
        .lift-card { transition: transform 0.35s cubic-bezier(.16,1,.3,1), box-shadow 0.35s ease, border-color 0.35s ease; }
        .lift-card:hover { transform: translateY(-6px); border-color: rgba(0,117,222,0.3); }

        /* ── Bars grow in from 0 ── */
        .grow-bar { height: 0%; transition: height 1.1s cubic-bezier(.16,1,.3,1); }

        .tabular { font-variant-numeric: tabular-nums; }

        /* ── How-it-works scroll-driven connecting line ── */
        .step-icon { transition: background-color 0.4s ease, color 0.4s ease, transform 0.3s ease; }
        .step-card.is-active .step-icon { background: #213183; color: #ffffff; }
        .step-dot { transition: background-color 0.4s ease, box-shadow 0.4s ease; }
        .step-card.is-active .step-dot { background: #213183; box-shadow: 0 0 0 4px rgba(33,49,131,0.15); }

        /* ── Full-page circle-wipe transition to /login ── */
        #page-wipe {
            position: fixed; inset: 0; z-index: 9999; pointer-events: none;
            background: linear-gradient(135deg, #213183, #1a2342);
            clip-path: circle(0% at 50% 50%);
        }
        #page-wipe.animate { transition: clip-path 0.65s cubic-bezier(.76,0,.24,1); }
        @media (prefers-reduced-motion: reduce) {
            #page-wipe { display: none; }
        }
    </style>
</head>
<body class="text-ink antialiased">

<div id="page-wipe"></div>

{{-- ── NAV ─────────────────────────────────────────────────────────────── --}}
<header id="site-header" class="sticky top-0 z-50 bg-surface/90 backdrop-blur border-b border-hairline relative">
    <div id="scroll-progress"></div>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <a href="{{ route('landing') }}" class="flex items-center gap-2.5 shrink-0">
            <div class="w-9 h-9 rounded-lg bg-white border border-hairline flex items-center justify-center overflow-hidden shadow-soft">
                <img src="/images/layrate-logo-mark.png" alt="LayRate logo" class="w-8 h-8 object-contain">
            </div>
            <span class="text-title text-ink">LayRate</span>
        </a>

        <nav class="hidden md:flex items-center gap-8">
            <a href="#features" data-nav-link="features" class="nav-link text-body-sm text-ink-muted hover:text-ink transition-colors">Features</a>
            <a href="#how-it-works" data-nav-link="how-it-works" class="nav-link text-body-sm text-ink-muted hover:text-ink transition-colors">How it works</a>
            <a href="#tech" data-nav-link="tech" class="nav-link text-body-sm text-ink-muted hover:text-ink transition-colors">Technology</a>
        </nav>

        <div class="flex items-center gap-3">
            <a href="{{ route('login') }}" data-page-transition
               class="inline-flex items-center gap-1.5 bg-primary text-on-primary text-button px-5 py-2.5 rounded-full hover:bg-primary-active active:scale-95 transition-all shadow-soft">
                Sign In
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>
    </div>
</header>

{{-- ── HERO ────────────────────────────────────────────────────────────── --}}
<section class="relative overflow-hidden bg-linear-to-br from-secondary to-sidebar-bg">
    <div id="hero-glow" class="absolute inset-0 hero-glow"></div>
    <div class="absolute inset-0 hero-spotlight"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 pt-16 pb-24 lg:pt-24 lg:pb-32">
        <div class="grid lg:grid-cols-2 gap-14 items-center">

            {{-- Copy --}}
            <div class="hero-enter">
                <span class="inline-flex items-center gap-1.5 text-eyebrow text-white/80 bg-white/10 border border-white/15 rounded-full px-3 py-1.5 mb-6">
                    <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                    SMART POULTRY OPERATIONS
                </span>
                <h1 class="text-4xl sm:text-heading-1 lg:text-display-2 text-white mb-5">
                    Every egg counted.<br>Every hen accounted for.
                </h1>
                <p class="text-body-md text-white/75 max-w-md mb-8">
                    LayRate unifies egg production, environmental monitoring, feed, and flock
                    health into one real-time system — powered by IoT sensors and
                    machine-learning forecasts, built for the modern layer farm.
                </p>
                <div class="flex flex-wrap items-center gap-4">
                    <a href="{{ route('login') }}" data-page-transition
                       class="inline-flex items-center gap-2 bg-primary text-on-primary text-button px-6 py-3 rounded-full hover:bg-primary-active active:scale-95 transition-all shadow-elevated">
                        Sign In to Dashboard
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                    <a href="#features"
                       class="inline-flex items-center gap-2 text-button text-white px-6 py-3 rounded-full border border-white/25 hover:bg-white/10 active:scale-95 transition-all">
                        Explore Features
                    </a>
                </div>
            </div>

            {{-- Decorative live-dashboard mockup. Three nested layers on purpose:
                 hero-enter (one-shot load animation) > float-slow (continuous
                 bob) > #hero-mockup (JS-driven velocity tilt). CSS animations
                 outrank inline styles on the same element/property, so each
                 motion needs its own layer or they'd fight over `transform`. --}}
            <div class="hero-enter" style="animation-delay: 150ms;">
                <div class="float-slow">
                    <div id="hero-mockup">
                        <div class="bg-surface rounded-xl shadow-elevated border border-hairline p-5 max-w-md mx-auto lg:mx-0 lg:ml-auto">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-alert-text/70"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-watch-text/70"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-ok-text/70"></span>
                                </div>
                                <span class="text-eyebrow text-ink-faint flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-ok-text animate-pulse"></span>
                                    LIVE
                                </span>
                            </div>

                            <div class="grid grid-cols-3 gap-3 mb-4">
                                <div class="bg-canvas-soft rounded-lg p-3">
                                    <div class="text-eyebrow text-ink-muted mb-1">EGGS TODAY</div>
                                    <div class="text-heading-3 text-ink tabular" data-countup="182">0</div>
                                </div>
                                <div class="bg-canvas-soft rounded-lg p-3">
                                    <div class="text-eyebrow text-ink-muted mb-1">AVG HDEP</div>
                                    <div class="text-heading-3 text-ink tabular" data-countup="84.2" data-decimals="1" data-suffix="%">0%</div>
                                </div>
                                <div class="bg-canvas-soft rounded-lg p-3">
                                    <div class="text-eyebrow text-ink-muted mb-1">CAGES</div>
                                    <div class="text-heading-3 text-ink tabular" data-countup="4">0</div>
                                </div>
                            </div>

                            <div class="border border-hairline rounded-lg p-3 mb-4">
                                <div class="text-eyebrow text-ink-muted mb-2.5">PRODUCTION BY CAGE</div>
                                <div class="flex items-end gap-2.5 h-16">
                                    <div class="grow-bar flex-1 rounded-t bg-cage-a" data-h="92"></div>
                                    <div class="grow-bar flex-1 rounded-t bg-cage-b" data-h="74"></div>
                                    <div class="grow-bar flex-1 rounded-t bg-cage-c" data-h="58"></div>
                                    <div class="grow-bar flex-1 rounded-t bg-cage-d" data-h="30"></div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 text-ok-text bg-ok-bg border border-ok-border rounded-lg px-3 py-2 text-caption">
                                <i data-lucide="thermometer" class="w-3.5 h-3.5"></i>
                                Cage A · 28.9°C · 68% humidity — within range
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── CAPABILITY STRIP ────────────────────────────────────────────────── --}}
<section class="border-b border-hairline bg-surface">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 grid grid-cols-2 lg:grid-cols-4 gap-6 text-center">
        <div>
            <div class="text-heading-3 text-primary">Real-Time</div>
            <div class="text-caption text-ink-muted mt-1">Sensor synchronization</div>
        </div>
        <div>
            <div class="text-heading-3 text-primary">Role-Based</div>
            <div class="text-caption text-ink-muted mt-1">Admin &amp; operator access</div>
        </div>
        <div>
            <div class="text-heading-3 text-primary">ML-Driven</div>
            <div class="text-caption text-ink-muted mt-1">Production forecasts</div>
        </div>
        <div>
            <div class="text-heading-3 text-primary">Full Lifecycle</div>
            <div class="text-caption text-ink-muted mt-1">Hen &amp; flock tracking</div>
        </div>
    </div>
</section>

{{-- ── FEATURES ────────────────────────────────────────────────────────── --}}
<section id="features" class="max-w-6xl mx-auto px-4 sm:px-6 py-20 lg:py-28">
    <div class="max-w-2xl mb-14 reveal">
        <span class="text-eyebrow text-primary">FEATURES</span>
        <h2 class="text-heading-2 text-ink mt-2 mb-3">Everything your farm needs, in one place.</h2>
        <p class="text-body-md text-ink-muted">
            From the cage floor to the forecast, LayRate connects hardware, operators,
            and data into a single, coherent system.
        </p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @php
        $features = [
            ['icon' => 'egg', 'title' => 'Production Tracking', 'desc' => 'Automatic and manual egg logging per cage and slot, with hen-day egg percentage (HDEP) calculated instantly.'],
            ['icon' => 'cpu', 'title' => 'IoT Hardware Integration', 'desc' => 'IR break-beam sensors auto-count eggs and DHT22 sensors stream live temperature and humidity — no manual entry required.'],
            ['icon' => 'thermometer', 'title' => 'Environmental Monitoring', 'desc' => 'Live temperature and humidity tracking per cage, with configurable thresholds and instant alerts when conditions drift.'],
            ['icon' => 'leaf', 'title' => 'Feed & Nutrition', 'desc' => 'Track feed batches, crude protein content, and consumption — with automatic feed conversion ratio (FCR) calculations.'],
            ['icon' => 'bird', 'title' => 'Flock & Health Records', 'desc' => 'Full hen lifecycle from placement to removal — health events, weight checks, mortality logs, and culling records.'],
            ['icon' => 'trending-up', 'title' => 'ML-Powered Forecasting', 'desc' => 'Predict future egg production using historical trends, environmental data, and machine-learning models.'],
        ];
        @endphp

        @foreach($features as $i => $f)
        <div class="reveal lift-card group bg-surface border border-hairline rounded-xl p-6 shadow-soft" style="transition-delay: {{ $i * 40 }}ms;">
            <div class="w-11 h-11 rounded-lg bg-primary/10 text-primary flex items-center justify-center mb-4 transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-6">
                <i data-lucide="{{ $f['icon'] }}" class="w-5 h-5"></i>
            </div>
            <h3 class="text-title text-ink mb-2">{{ $f['title'] }}</h3>
            <p class="text-body-sm text-ink-muted">{{ $f['desc'] }}</p>
        </div>
        @endforeach
    </div>
</section>

{{-- ── HOW IT WORKS ────────────────────────────────────────────────────── --}}
<section id="how-it-works" class="bg-canvas-soft border-y border-hairline">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-20 lg:py-28">
        <div class="max-w-2xl mb-14 reveal">
            <span class="text-eyebrow text-primary">HOW IT WORKS</span>
            <h2 class="text-heading-2 text-ink mt-2 mb-3">From the cage to the dashboard, automatically.</h2>
        </div>

        {{-- Scroll-driven connecting line: fills left-to-right as this section
             passes through the viewport; each step lights up once the line
             reaches it. Desktop only — a horizontal "progress" reads oddly
             once steps stack into a single column on mobile. --}}
        <div class="hidden md:block relative h-1 bg-hairline rounded-full mb-10 overflow-hidden">
            <div id="how-fill" class="absolute inset-y-0 left-0 bg-secondary rounded-full" style="width: 0%"></div>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
            @php
            $steps = [
                ['n' => '01', 'icon' => 'radio', 'title' => 'Sensors Capture', 'desc' => 'IR break-beam and DHT22 hardware continuously read egg counts, temperature, and humidity straight from the cage.'],
                ['n' => '02', 'icon' => 'refresh-cw', 'title' => 'LayRate Logs & Analyzes', 'desc' => 'Readings sync automatically into per-cage, per-slot records — cross-checked against manual entries and flagged for review.'],
                ['n' => '03', 'icon' => 'target', 'title' => 'Your Team Acts', 'desc' => 'Dashboards, alerts, and forecasts turn raw sensor data into decisions — before a problem becomes a loss.'],
            ];
            @endphp

            @foreach($steps as $i => $s)
            <div class="step-card reveal lift-card group relative bg-surface border border-hairline rounded-xl p-6 shadow-soft" style="transition-delay: {{ $i * 60 }}ms;">
                <div class="hidden md:flex step-dot absolute -top-[2.85rem] left-1/2 -translate-x-1/2 w-3 h-3 rounded-full bg-hairline items-center justify-center"></div>
                <span class="absolute top-3 right-4 select-none transition-transform duration-300 group-hover:scale-110" style="font-size: 44px; font-weight: 700; -webkit-text-stroke: 1px #e6e6e6; color: transparent;">{{ $s['n'] }}</span>
                <div class="step-icon w-11 h-11 rounded-lg bg-secondary/10 text-secondary flex items-center justify-center mb-4 relative transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-6">
                    <i data-lucide="{{ $s['icon'] }}" class="w-5 h-5"></i>
                </div>
                <h3 class="text-title text-ink mb-2 relative">{{ $s['title'] }}</h3>
                <p class="text-body-sm text-ink-muted relative">{{ $s['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── TECH STACK ──────────────────────────────────────────────────────── --}}
<section id="tech" class="max-w-6xl mx-auto px-4 sm:px-6 py-20 lg:py-28">
    <div class="grid lg:grid-cols-2 gap-14 items-center">
        <div class="reveal">
            <span class="text-eyebrow text-primary">TECHNOLOGY</span>
            <h2 class="text-heading-2 text-ink mt-2 mb-4">Built on real hardware, backed by real models.</h2>
            <p class="text-body-md text-ink-muted mb-8">
                LayRate isn't just a form — it's wired directly into the barn. Sensor
                readings flow into the same database that trains the forecasting model,
                so every prediction is grounded in your farm's own history.
            </p>

            <ul class="space-y-4">
                <li class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-ok-bg text-ok-text flex items-center justify-center shrink-0 mt-0.5">
                        <i data-lucide="scan-line" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <div class="text-body-md text-ink font-medium">IR break-beam egg counters</div>
                        <div class="text-body-sm text-ink-muted">Auto-detect and log eggs the moment they're laid, per slot.</div>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-watch-bg text-watch-text flex items-center justify-center shrink-0 mt-0.5">
                        <i data-lucide="thermometer" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <div class="text-body-md text-ink font-medium">DHT22 environmental sensors</div>
                        <div class="text-body-sm text-ink-muted">Stream live temperature and humidity for every cage, around the clock.</div>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5">
                        <i data-lucide="brain-circuit" class="w-4 h-4"></i>
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
                <div class="absolute -inset-4 bg-linear-to-br from-sticker-sky/20 to-sticker-purple/20 rounded-2xl blur-2xl"></div>
                <div class="relative bg-surface border border-hairline rounded-xl shadow-soft p-6">
                    <div class="text-eyebrow text-ink-muted mb-4">DATA FLOW</div>
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3 bg-canvas-soft rounded-lg px-3 py-2.5">
                            <i data-lucide="cpu" class="w-4 h-4 text-cage-a"></i>
                            <span class="text-body-sm text-ink">Hardware sensors</span>
                        </div>
                        <div class="flex justify-center"><i data-lucide="arrow-down" class="w-4 h-4 text-ink-faint"></i></div>
                        <div class="flex items-center gap-3 bg-canvas-soft rounded-lg px-3 py-2.5">
                            <i data-lucide="database" class="w-4 h-4 text-cage-b"></i>
                            <span class="text-body-sm text-ink">LayRate database</span>
                        </div>
                        <div class="flex justify-center"><i data-lucide="arrow-down" class="w-4 h-4 text-ink-faint"></i></div>
                        <div class="flex items-center gap-3 bg-canvas-soft rounded-lg px-3 py-2.5">
                            <i data-lucide="trending-up" class="w-4 h-4 text-cage-c"></i>
                            <span class="text-body-sm text-ink">Forecast model</span>
                        </div>
                        <div class="flex justify-center"><i data-lucide="arrow-down" class="w-4 h-4 text-ink-faint"></i></div>
                        <div class="flex items-center gap-3 bg-primary/10 rounded-lg px-3 py-2.5">
                            <i data-lucide="layout-dashboard" class="w-4 h-4 text-primary"></i>
                            <span class="text-body-sm text-ink font-medium">Your dashboard</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── CTA BAND ─────────────────────────────────────────────────────────── --}}
<section class="relative overflow-hidden bg-linear-to-br from-secondary to-sidebar-bg">
    <div class="absolute inset-0 hero-glow"></div>
    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 py-20 text-center reveal">
        <h2 class="text-heading-1 text-white mb-4">Ready to see your flock in real time?</h2>
        <p class="text-body-md text-white/75 mb-8 max-w-lg mx-auto">
            Sign in to view live production, environment, and forecast data for your farm.
        </p>
        <a href="{{ route('login') }}" data-page-transition
           class="inline-flex items-center gap-2 bg-primary text-on-primary text-button px-7 py-3.5 rounded-full hover:bg-primary-active active:scale-95 transition-all shadow-elevated">
            Sign In to LayRate
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </a>
    </div>
</section>

{{-- ── FOOTER ───────────────────────────────────────────────────────────── --}}
<footer class="bg-surface border-t border-hairline">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-white border border-hairline flex items-center justify-center overflow-hidden">
                <img src="/images/layrate-logo-mark.png" alt="LayRate logo" class="w-7 h-7 object-contain">
            </div>
            <div>
                <div class="text-body-sm text-ink font-medium leading-tight">LayRate</div>
                <div class="text-caption text-ink-muted leading-tight">Smart Poultry Farm Management</div>
            </div>
        </div>
        <p class="text-caption text-ink-faint">&copy; {{ date('Y') }} LayRate. All rights reserved.</p>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide) lucide.createIcons();

    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Everything below is wrapped so a genuine bug degrades to "content
    // visible, no fancy motion" rather than "content stuck invisible."
    try {
        var revealEls   = [].slice.call(document.querySelectorAll('.reveal'));
        var header       = document.getElementById('site-header');
        var progressBar  = document.getElementById('scroll-progress');
        var navLinks     = [].slice.call(document.querySelectorAll('[data-nav-link]'));
        var sections     = navLinks.map(function (l) { return document.getElementById(l.dataset.navLink); }).filter(Boolean);
        var heroGlow     = document.getElementById('hero-glow');
        var heroMockup   = document.getElementById('hero-mockup');
        var howSection   = document.getElementById('how-it-works');
        var howFill      = document.getElementById('how-fill');
        var howSteps     = [].slice.call(document.querySelectorAll('.step-card'));

        function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }

        if (reducedMotion) {
            // Static, fully-settled end state — no continuous motion at all.
            revealEls.forEach(function (el) { el.style.opacity = 1; el.style.transform = 'none'; });
            if (howFill) howFill.style.width = '100%';
            howSteps.forEach(function (s) { s.classList.add('is-active'); });
        } else {
            var lastScrollY = window.scrollY;
            var velocity = 0;
            var loopRunning = false;

            function computeFrame() {
                var vh = window.innerHeight;
                var scrollY = window.scrollY;

                // Scroll progress bar (whole-document).
                if (progressBar) {
                    var docH = document.documentElement.scrollHeight - vh;
                    progressBar.style.width = (docH > 0 ? clamp(scrollY / docH, 0, 1) * 100 : 0) + '%';
                }

                if (header) header.classList.toggle('shadow-soft', scrollY > 8);

                // Continuous, bidirectional scroll-linked reveal: driven by
                // each element's live position, not a one-shot trigger.
                revealEls.forEach(function (el) {
                    var rect = el.getBoundingClientRect();
                    var start = vh * 0.92, end = vh * 0.55;
                    var progress = clamp((start - rect.top) / (start - end), 0, 1);
                    el.style.opacity = progress;
                    el.style.transform = 'translateY(' + ((1 - progress) * 28) + 'px)';
                });

                // Scroll-spy nav highlight.
                sections.forEach(function (sec) {
                    var rect = sec.getBoundingClientRect();
                    var active = rect.top <= vh * 0.5 && rect.bottom >= vh * 0.3;
                    var link = navLinks.filter(function (l) { return l.dataset.navLink === sec.id; })[0];
                    if (link) link.classList.toggle('active', active);
                });

                // Hero ambient parallax.
                if (heroGlow) heroGlow.style.transform = 'translateY(' + (scrollY * 0.25) + 'px)';

                // Scroll-velocity tilt on the hero mockup card (decays to 0 when still).
                var delta = scrollY - lastScrollY;
                lastScrollY = scrollY;
                velocity += (delta - velocity) * 0.15;
                if (heroMockup) heroMockup.style.transform = 'rotate(' + clamp(velocity * 0.5, -4, 4) + 'deg)';
                velocity *= 0.9;

                // How-it-works: connecting line fills as the section scrolls
                // through, each step lights up once the line reaches it.
                if (howSection && howFill) {
                    var hRect = howSection.getBoundingClientRect();
                    var hProgress = clamp((vh - hRect.top) / (hRect.height + vh * 0.3), 0, 1);
                    howFill.style.width = (hProgress * 100) + '%';
                    howSteps.forEach(function (s, i) {
                        s.classList.toggle('is-active', hProgress >= (i + 0.5) / howSteps.length);
                    });
                }

                var settled = Math.abs(velocity) < 0.05;
                if (!settled) {
                    requestAnimationFrame(computeFrame);
                } else {
                    loopRunning = false;
                }
            }

            function kickLoop() {
                if (!loopRunning) {
                    loopRunning = true;
                    requestAnimationFrame(computeFrame);
                }
            }

            document.addEventListener('scroll', kickLoop, { passive: true });
            computeFrame(); // establish correct initial state without waiting for the first scroll
        }

        // ── Count-up numbers in the hero mockup (load-triggered, once) ──
        function easeOutExpo(t) { return t === 1 ? 1 : 1 - Math.pow(2, -10 * t); }
        document.querySelectorAll('[data-countup]').forEach(function (el) {
            var target = parseFloat(el.dataset.countup);
            var decimals = parseInt(el.dataset.decimals || '0', 10);
            var suffix = el.dataset.suffix || '';
            if (reducedMotion) { el.textContent = target.toFixed(decimals) + suffix; return; }
            var duration = 1400, start = null;
            function tick(ts) {
                if (start === null) start = ts;
                var progress = Math.min((ts - start) / duration, 1);
                el.textContent = (target * easeOutExpo(progress)).toFixed(decimals) + suffix;
                if (progress < 1) requestAnimationFrame(tick);
            }
            setTimeout(function () { requestAnimationFrame(tick); }, 350);
        });

        // ── Bar chart grow-in (load-triggered, once) ──
        document.querySelectorAll('.grow-bar').forEach(function (bar) {
            var target = bar.dataset.h + '%';
            if (reducedMotion) { bar.style.height = target; return; }
            setTimeout(function () { bar.style.height = target; }, 400);
        });

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
        document.querySelectorAll('.reveal').forEach(function (el) { el.style.opacity = 1; el.style.transform = 'none'; });
        if (window.console) console.error('Landing page animation controller failed, showing static fallback:', err);
    }
});
</script>
</body>
</html>
