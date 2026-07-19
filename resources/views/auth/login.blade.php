<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — LayRate</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link href="{{ asset('css/tailwind.css') }}" rel="stylesheet">
    <script src="{{ asset('js/lucide.min.js') }}"></script>
    <style>
        body { background-color: #f6f5f4; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
        :focus-visible { outline: 2px solid #0075de; outline-offset: 2px; border-radius: 4px; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/inter.css') }}">
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm">

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
        <div class="bg-white rounded-xl border border-[#D9D9D9] p-7 shadow-sm">
            {{-- Logo — lives inside the card: the PNG has a solid white
                 background, which blends invisibly here but would show as a
                 white box on the page's warm canvas. --}}
            <img src="{{ asset('images/layrate-logo.png') }}"
                 alt="LayRate — Egg Counting &amp; Forecasting System"
                 class="w-36 mx-auto -mt-3 -mb-5">

            <h1 class="text-base font-semibold text-[#333333] mb-1 text-center">Sign in</h1>
            <p class="text-xs text-[#6B7280] mb-6 text-center">Enter your credentials to access the dashboard.</p>

            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">EMAIL</label>
                    <input type="email" name="email" required autofocus
                           value="{{ old('email') }}"
                           placeholder="operator@layrate.local"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333]
                                  focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]
                                  {{ $errors->has('email') ? 'border-red-400' : '' }}">
                    @error('email')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">PASSWORD</label>
                    <input type="password" name="password" required
                           placeholder="••••••••"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333]
                                  focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="remember" id="remember"
                           class="w-3.5 h-3.5 rounded border-[#D9D9D9] text-[#102A4C]">
                    <label for="remember" class="text-xs text-[#6B7280]">Remember me</label>
                </div>

                <x-button type="submit" class="w-full py-2.5 mt-2">
                    Sign In
                </x-button>
            </form>
        </div>

        <p class="text-center text-xs text-[#6B7280] mt-5">
            LayRate · Offline Poultry Farm Management System
        </p>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
