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
    <meta name="theme-color" content="#1a2342">
    <meta property="og:title" content="Sign In — LayRate">
    <meta property="og:image" content="/images/layrate-logo.png">
    <link href="/css/tailwind.css" rel="stylesheet">
    <script src="/js/lucide.min.js"></script>
    {{-- Shared branded validation (same module as the app shell) --}}
    <script src="/js/form-validation.js" defer></script>
    <style>
        body { min-height: 100vh; background-color: var(--color-sidebar-bg); background-image: radial-gradient(60% 40% at 50% 0%, rgba(98,174,240,0.14), rgba(98,174,240,0) 70%); background-size: cover; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; overscroll-behavior: none; }
        :focus-visible { outline: 2px solid var(--color-navy); outline-offset: 2px; border-radius: 4px; }

        /* Same backdrop treatment as the info page hero: egg-dot grid plus
           hovering egg outlines drifting slowly. Pure CSS, near-zero cost. */
        .login-dot-grid {
            background-image: radial-gradient(rgba(255,255,255,0.14) 1.2px, transparent 1.3px);
            background-size: 26px 26px;
            mask-image: radial-gradient(80% 80% at 50% 40%, black 20%, transparent 100%);
            -webkit-mask-image: radial-gradient(80% 80% at 50% 40%, black 20%, transparent 100%);
        }
        @keyframes driftSlow {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-14px) rotate(4deg); }
        }
        /* Wider wander for the far eggs so the whole backdrop feels afloat,
           not just hovering in place. */
        @keyframes driftWide {
            0%, 100% { transform: translate(0, 0) rotate(-3deg); }
            33% { transform: translate(18px, -20px) rotate(3deg); }
            66% { transform: translate(-14px, -8px) rotate(-1deg); }
        }
        .float-shape { animation: driftSlow 9s ease-in-out infinite; }
        .float-shape-2 { animation-delay: -4.5s; }
        .float-wide { animation: driftWide 14s ease-in-out infinite; }
        .float-wide-2 { animation-delay: -7s; animation-duration: 17s; }
        .float-wide-3 { animation-delay: -3s; animation-duration: 11s; }
        @media (prefers-reduced-motion: reduce) {
            .float-shape, .float-wide { animation: none !important; }
        }

        /* Card + form controls reuse the app system: .modal-card supplies the
           solid-white 20px shell + elevated shadow; inputs/labels below use
           the same classes as every app form (border token, lg radius,
           primary focus ring). No login-specific duplicates. */

        .signin-title {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            font-weight: 700;
            letter-spacing: -0.25px;
        }
        .signin-title-accent {
            width: 3rem; height: 3px; margin: 0.6rem auto 0;
            border-radius: 9999px;
            background: linear-gradient(90deg, var(--color-navy), rgba(0, 45, 94, 0.25));
        }

        /* Reverse of the landing page's circle-wipe: this page loads already
           covered, then wipes away to reveal the form — continuing the same
           transition that started on the landing page. Pure CSS start state,
           so it works even if JS never runs (just stays covered momentarily
           then the fallback below force-removes it). */
        #page-wipe {
            position: fixed; inset: 0; z-index: 9999; pointer-events: none;
            background-color: var(--color-sidebar-bg);
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
    <div class="fixed inset-0 overflow-hidden pointer-events-none" style="z-index:0;" aria-hidden="true">
        <div class="absolute inset-0 login-dot-grid opacity-70"></div>
        <svg class="absolute right-[8%] top-16 w-24 h-28 text-white/[0.08] float-shape hidden sm:block" viewBox="0 0 100 130" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><ellipse cx="50" cy="65" rx="38" ry="55"/></svg>
        <svg class="absolute left-[6%] bottom-24 w-14 h-16 text-white/[0.07] float-shape float-shape-2 hidden sm:block" viewBox="0 0 100 130" fill="none" stroke="currentColor" stroke-width="4" aria-hidden="true"><ellipse cx="50" cy="65" rx="38" ry="55"/></svg>
        <svg class="absolute left-[12%] top-[12%] w-16 h-20 text-white/[0.07] float-wide hidden md:block" viewBox="0 0 100 130" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><ellipse cx="50" cy="65" rx="38" ry="55"/></svg>
        <svg class="absolute right-[14%] bottom-[16%] w-20 h-24 text-white/[0.08] float-wide float-wide-2 hidden md:block" viewBox="0 0 100 130" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><ellipse cx="50" cy="65" rx="38" ry="55"/></svg>
        <svg class="absolute left-[4%] top-[46%] w-10 h-12 text-white/[0.07] float-wide float-wide-3 hidden lg:block" viewBox="0 0 100 130" fill="none" stroke="currentColor" stroke-width="4" aria-hidden="true"><ellipse cx="50" cy="65" rx="38" ry="55"/></svg>
        <svg class="absolute right-[4%] top-[52%] w-12 h-14 text-white/[0.07] float-wide float-wide-2 hidden lg:block" viewBox="0 0 100 130" fill="none" stroke="currentColor" stroke-width="4" aria-hidden="true"><ellipse cx="50" cy="65" rx="38" ry="55"/></svg>
        <svg class="absolute left-[22%] bottom-[8%] w-16 h-20 text-white/[0.08] float-shape float-shape-2 hidden md:block" viewBox="0 0 100 130" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><ellipse cx="50" cy="65" rx="38" ry="55"/></svg>
    </div>

    <div class="w-full max-w-sm login-enter relative" style="z-index:10;">

        {{-- Login Error Banner (danger token triple, like app flash banners) --}}
        @if($errors->any())
        <div class="mb-4 rounded-lg px-4 py-3 flex items-start gap-3 bg-danger-bg border border-danger-border" style="border-left: 3px solid var(--color-danger);">
            <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 shrink-0 text-danger"></i>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.05em] text-danger">Sign in failed</p>
                <p class="text-sm mt-0.5 text-ink">{{ $errors->first() }}</p>
            </div>
        </div>
        @endif

        {{-- Card --}}
        <div class="modal-card border border-hairline p-7">
            {{-- Logo — the egg alone, centered above Sign in. --}}
            <div class="flex justify-center">
                <x-logo size="h-28" :eager="true" />
            </div>

            <h1 class="signin-title text-2xl mb-1 text-center" style="color: var(--color-navy);">Sign in</h1>
            <div class="signin-title-accent"></div>
            <p class="text-xs mb-6 mt-3 text-center text-ink-muted">Enter your credentials to access the dashboard.</p>

            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-xs tracking-wider mb-1.5 text-ink-muted">EMAIL</label>
                    <input type="email" name="email" id="email" required autofocus autocomplete="email"
                           value="{{ old('email') }}"
                           placeholder="operator@layrate.local"
                           style="{{ $errors->has('email') ? 'border-color:var(--color-danger);' : '' }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white text-ink placeholder:text-ink-faint focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy"
                           autocapitalize="none" spellcheck="false" aria-describedby="email-error">
                    @error('email')
                    <p id="email-error" class="text-xs text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-xs tracking-wider mb-1.5 text-ink-muted">PASSWORD</label>
                    <input type="password" name="password" id="password" required autocomplete="current-password"
                           placeholder="••••••••"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white text-ink placeholder:text-ink-faint focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="remember" id="remember" class="shrink-0">
                    <label for="remember" class="text-xs text-ink-muted">Remember me</label>
                </div>

                <x-button type="submit" class="w-full py-2.5 mt-2">Sign In</x-button>

                <div class="text-center mt-4 pt-4 border-t border-hairline">
                    <a href="{{ route('landing') }}"
                       class="text-xs text-ink-muted hover:text-ink transition-colors">
                        What is LayRate?
                    </a>
                </div>
            </form>
        </div>

        <p class="text-center text-xs mt-5" style="color: rgba(255,255,255,0.7);">
            LayRate · Offline Poultry Farm Management System
        </p>
    </div>

    <script>
        lucide.createIcons();
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
