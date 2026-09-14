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
        body { min-height: 100vh; background: linear-gradient(160deg, #213183 0%, #1a2342 55%, #4a5485 100%); background-size: cover; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; overscroll-behavior: none; }
        :focus-visible { outline: 2px solid #0075de; outline-offset: 2px; border-radius: 4px; }

        .egg-decor { color: rgba(255, 255, 255, 0.10); }

        /* Basic white card + inputs (glassmorphism removed) */
        .glass-card {
            position: relative;
            background: #ffffff;
            border: 1px solid #D9D9D9;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(10, 22, 46, 0.18);
        }
        .glass-input {
            background: #ffffff;
            border: 1px solid #D9D9D9;
            color: #333333;
        }
        .glass-input::placeholder { color: #9CA3AF; }
        .glass-input:hover { border-color: #B0B0B0; }
        .glass-input:focus {
            background: #ffffff;
            border-color: #102A4C;
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 42, 76, 0.14);
        }
        .glass-label { color: #6B7280; }

        .signin-title {
            font-family: Georgia, 'Times New Roman', 'Palatino Linotype', serif;
            font-style: italic;
            font-weight: 600;
            letter-spacing: 0.01em;
        }
        .signin-title-accent {
            width: 3rem; height: 3px; margin: 0.6rem auto 0;
            border-radius: 9999px;
            background: linear-gradient(90deg, #102A4C, rgba(16, 42, 76, 0.25));
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
            {{-- Logo — circular-cropped mark, sits directly on the glass. --}}
            <img src="/images/logo_nobg.png"
                 alt="LayRate — Egg Counting &amp; Forecasting System"
                 class="w-40 mx-auto -mt-3" loading="lazy">

            <h1 class="signin-title text-2xl mb-1 text-center" style="color: #102A4C;">Sign in</h1>
            <div class="signin-title-accent"></div>
            <p class="text-xs mb-6 mt-3 text-center" style="color: #6B7280;">Enter your credentials to access the dashboard.</p>

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
                    <label for="remember" class="text-xs" style="color: #374151;">Remember me</label>
                </div>

                <x-button type="submit" class="w-full py-2.5 mt-2" style="background-color:#27578A;">Sign In</x-button>

                <div class="text-center mt-4 pt-4 border-t border-white/15">
                    <a href="{{ route('landing') }}"
                       class="text-xs transition-colors" style="color: #6B7280;">
                        What is LayRate?
                    </a>
                </div>
            </form>
        </div>

        <p class="text-center text-xs mt-5" style="color: #6B7280;">
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

        // Exit transition on successful sign-in: intercept the POST, follow the
        // server redirect with fetch, play the reverse circle-wipe, then go.
        // On failure we land back on /login, so reload it to show the banner.
        (function () {
            var form = document.querySelector('form');
            if (!form) return;
            var reduceMotion = window.matchMedia &&
                window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            form.addEventListener('submit', function (e) {
                if (form.dataset.submitting) { e.preventDefault(); return; }
                form.dataset.submitting = '1';
                var btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Signing in…';
                }
                e.preventDefault();

                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    redirect: 'follow',
                    credentials: 'same-origin',
                }).then(function (res) {
                    if (res.status === 419) {
                        // Session/token expired (e.g. a login page rendered before
                        // a logout rotated the CSRF token). Reload fresh instead of
                        // showing the "Session Expired" page.
                        window.location.reload();
                        return;
                    }
                    if (!res.ok) throw new Error('request failed');
                    var url = res.url || window.location.href;
                    var hasRedirected = res.redirected;

                    // Server bounced us back to the login page → auth failed.
                    if (url.indexOf('/login') !== -1) {
                        window.location.href = url;
                        return;
                    }

                    var go = function () { window.location.href = url; };

                    if (!hasRedirected || reduceMotion) { go(); return; }

                    // Leave with the same circle-wipe used when entering the login
                    // page — just in reverse: the wipe covers the screen, then we
                    // navigate into the dashboard beneath it.
                    var wipe = document.getElementById('page-wipe');
                    if (!wipe) { go(); return; }
                    wipe.style.display = '';
                    wipe.style.clipPath = 'circle(0% at 50% 50%)';
                    requestAnimationFrame(function () {
                        requestAnimationFrame(function () {
                            wipe.style.clipPath = 'circle(150% at 50% 50%)';
                            setTimeout(go, 700);
                        });
                    });
                }).catch(function () {
                    // Fetch failed (network/dup-click guard) → fall back to the
                    // native POST so the browser behaves as usual.
                    if (btn) { btn.disabled = false; btn.textContent = 'Sign In'; }
                    delete form.dataset.submitting;
                    form.submit();
                });
            });
        })();
    </script>
</body>
</html>
