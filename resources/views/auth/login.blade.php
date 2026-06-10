<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — LayRate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        body { background-color: #F5F5F0; font-family: ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm">

        {{-- Logo --}}
        <div class="flex items-center justify-center gap-3 mb-8">
            <div class="w-10 h-10 rounded-xl bg-[#102A4C] flex items-center justify-center">
                <i data-lucide="feather" class="w-5 h-5 text-white"></i>
            </div>
            <div>
                <div class="text-lg font-semibold text-[#102A4C] leading-tight">LayRate</div>
                <div class="text-[11px] text-[#6B7280]">Farm Monitor</div>
            </div>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-xl border border-[#D9D9D9] p-7 shadow-sm">
            <h1 class="text-base font-semibold text-[#333333] mb-1">Sign in</h1>
            <p class="text-xs text-[#6B7280] mb-6">Enter your credentials to access the dashboard.</p>

            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">EMAIL</label>
                    <input type="email" name="email" required autofocus
                           value="{{ old('email') }}"
                           placeholder="operator@layrate.local"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333]
                                  focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]
                                  {{ $errors->has('email') ? 'border-red-400' : '' }}">
                    @error('email')
                    <p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">PASSWORD</label>
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

                <button type="submit"
                        class="w-full bg-[#102A4C] text-white py-2.5 rounded-lg text-sm font-medium
                               hover:bg-[#1D4E8F] transition-colors mt-2">
                    Sign In
                </button>
            </form>
        </div>

        <p class="text-center text-[11px] text-[#6B7280] mt-5">
            LayRate · Offline Poultry Farm Management System
        </p>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
