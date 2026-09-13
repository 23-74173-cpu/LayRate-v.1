<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — LayRate</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link href="/css/tailwind.css" rel="stylesheet">
    <script src="/js/lucide.min.js"></script>
    <style>
        body { background: linear-gradient(160deg, #213183 0%, #1a2342 55%, #4a5485 100%); background-attachment: fixed; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; overscroll-behavior: none; }
        :focus-visible { outline: 2px solid #0075de; outline-offset: 2px; border-radius: 4px; }

        .egg-decor { color: rgba(255, 255, 255, 0.10); }

        /* Glassmorphism card + inputs */
        .glass-card {
            position: relative;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.06);
            -webkit-backdrop-filter: blur(28px) saturate(160%);
            backdrop-filter: blur(28px) saturate(160%);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.18);
        }
        .glass-eggs { overflow: hidden; pointer-events: none; }
        .glass-eggs i, .glass-eggs svg { color: #ffffff; filter: blur(4px); }
        .glass-input {
            background: rgba(255, 255, 255, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.32);
            color: #fff;
        }
        .glass-input::placeholder { color: rgba(255, 255, 255, 0.55); }
        .glass-input:hover { border-color: rgba(255, 255, 255, 0.45); }
        .glass-input:focus {
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.65);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.14);
        }
        .glass-label { color: rgba(255, 255, 255, 0.85); }

        .signin-title {
            font-family: Georgia, 'Times New Roman', 'Palatino Linotype', serif;
            font-style: italic;
            font-weight: 600;
            letter-spacing: 0.01em;
        }
        .signin-title-accent {
            width: 3rem; height: 3px; margin: 0.6rem auto 0;
            border-radius: 9999px;
            background: linear-gradient(90deg, rgba(255,255,255,0.9), rgba(255,255,255,0.25));
        }

        /* Reverse of the landing page's circle-wipe: this page loads already
           covered, then wipes away to reveal the form — continuing the same
           transition that started on the landing page. Pure CSS start state,
           so it works even if JS never runs (just stays covered momentarily
           then the fallback below force-removes it). */
        #page-wipe {
            position: fixed; inset: 0; z-index: 9999; pointer-events: none;
            background: linear-gradient(160deg, #213183 0%, #1a2342 55%, #4a5485 100%);
            clip-path: circle(150% at 50% 50%);
            transition: clip-path 0.65s cubic-bezier(.76,0,.24,1);
        }
        .login-enter { opacity: 0; transform: translateY(16px); }
        .login-enter.in { opacity: 1; transform: translateY(0); transition: opacity 0.5s ease-out 0.3s, transform 0.5s ease-out 0.3s; }
        @media (prefers-reduced-motion: reduce) {
            #page-wipe { display: none; }
            .login-enter { opacity: 1; transform: none; }
        }
    </style>
    <link rel="stylesheet" href="/css/inter.css">
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div id="page-wipe"></div>
    <div id="egg-decor" class="fixed inset-0 overflow-hidden pointer-events-none egg-decor" style="z-index:0;"></div>

    <div class="w-full max-w-sm login-enter relative" style="z-index:10;">

        {{-- Login Error Banner --}}
        @if($errors->any())
        <div class="mb-4 rounded-lg px-4 py-3 flex items-start gap-3" style="background-color: #fdf2f2; border: 1px solid #f3cdd0; border-left: 3px solid #e03e3e;">
            <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 shrink-0" style="color: #c44d4d;"></i>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.05em]" style="color: #c44d4d;">Sign in failed</p>
                <p class="text-sm mt-0.5" style="color: #31302e;">{{ $errors->first() }}</p>
            </div>
        </div>
        @endif

        {{-- Card --}}
        <div class="glass-card p-7">
            {{-- Blurred decorative eggs behind the card content --}}
            <div class="glass-eggs" style="position:absolute; inset:0; z-index:-1;">
                <i data-lucide="egg" class="w-16 h-16" style="position:absolute; top:3%; left:5%; transform:rotate(-12deg); opacity:.55;"></i>
                <i data-lucide="egg" class="w-24 h-24" style="position:absolute; bottom:6%; right:-3%; transform:rotate(18deg); opacity:.4;"></i>
                <i data-lucide="egg" class="w-10 h-10" style="position:absolute; top:16%; right:12%; transform:rotate(24deg); opacity:.5;"></i>
                <i data-lucide="egg" class="w-14 h-14" style="position:absolute; top:38%; left:-4%; transform:rotate(-20deg); opacity:.35;"></i>
                <i data-lucide="egg" class="w-20 h-20" style="position:absolute; bottom:26%; left:30%; transform:rotate(10deg); opacity:.3;"></i>
            </div>
            {{-- Logo — circular-cropped mark, sits directly on the glass. --}}
            <img src="/images/logo1.png"
                 alt="LayRate — Egg Counting &amp; Forecasting System"
                 class="w-40 mx-auto -mt-3 -mb-5 rounded-full" loading="lazy">

            <h1 class="signin-title text-2xl text-white mb-1 text-center">Sign in</h1>
            <div class="signin-title-accent"></div>
            <p class="text-xs text-white/70 mb-6 mt-3 text-center">Enter your credentials to access the dashboard.</p>

            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="glass-label block text-xs tracking-wider mb-1.5">EMAIL</label>
                    <input type="email" name="email" required autofocus
                           value="{{ old('email') }}"
                           placeholder="operator@layrate.local"
                           style="{{ $errors->has('email') ? 'border-color:#ef4444;' : '' }}"
                           class="glass-input w-full rounded-lg px-3 py-2.5 text-sm focus:outline-none"
                           autocapitalize="none" spellcheck="false">
                    @error('email')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="glass-label block text-xs tracking-wider mb-1.5">PASSWORD</label>
                    <input type="password" name="password" required
                           placeholder="••••••••"
                           class="glass-input w-full rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="remember" id="remember"
                           class="w-3.5 h-3.5 rounded border-white/40 bg-white/10 text-[#102A4C]" style="accent-color:#fff;">
                    <label for="remember" class="text-xs text-white/80">Remember me</label>
                </div>

                <x-button type="submit" class="w-full py-2.5 mt-2">
                    Sign In
                </x-button>

                <div class="text-center mt-4 pt-4 border-t border-white/15">
                    <a href="{{ route('landing') }}"
                       class="text-xs text-white/70 hover:text-white transition-colors">
                        What is LayRate?
                    </a>
                </div>
            </form>
        </div>

        <p class="text-center text-xs text-white mt-5">
            LayRate · Offline Poultry Farm Management System
        </p>
    </div>

    <script>
        lucide.createIcons();
        (function () {
            // Decorative eggs scattered over the blue gradient background —
            // random position, size, rotation, and translucency on every load.
            var decor = document.getElementById('egg-decor');
            if (!decor) return;
            var opacities = ['0.30', '0.40', '0.50', '0.60', '0.70'];
            var count = 40;
            for (var i = 0; i < count; i++) {
                var egg = document.createElement('div');
                egg.style.position = 'absolute';
                egg.style.left = (Math.random() * 100).toFixed(2) + '%';
                egg.style.top = (Math.random() * 100).toFixed(2) + '%';
                egg.style.width = (16 + Math.random() * 48).toFixed(1) + 'px';
                egg.style.height = (16 + Math.random() * 48).toFixed(1) + 'px';
                egg.style.opacity = opacities[i % opacities.length];
                egg.style.transform = 'rotate(' + (Math.random() * 360).toFixed(0) + 'deg)';
                egg.innerHTML = '<i data-lucide="egg" class="w-full h-full"></i>';
                decor.appendChild(egg);
            }
            if (window.lucide) lucide.createIcons();
        })();
        document.addEventListener('DOMContentLoaded', function () {
            var wipe = document.getElementById('page-wipe');
            var card = document.querySelector('.login-enter');
            requestAnimationFrame(function () {
                if (wipe) wipe.style.clipPath = 'circle(0% at 50% 50%)';
                if (card) card.classList.add('in');
            });
            // Safety net: if the transition never fires for any reason, don't
            // leave the page permanently covered.
            setTimeout(function () {
                if (wipe) wipe.style.display = 'none';
                if (card) card.classList.add('in');
            }, 1500);
        });
    </script>
</body>
</html>
