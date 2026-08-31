@extends('layouts.app')
@section('title', 'Initial Setup')

@section('content')
{{-- Locked setup overlay: covers the screen, blocks background interaction --}}
<div class="fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]"
         style="background-color: rgba(0,0,0,0.45); backdrop-filter: blur(3px);"></div>

    <div class="relative w-full max-w-2xl bg-white rounded-xl border border-[#D9D9D9] overflow-hidden shadow-2xl flex flex-col max-h-screen max-h-[100dvh]">

        {{-- Wizard title bar --}}
        <div class="shrink-0 px-6 pt-5 pb-3 border-b border-[#e6e6e6]" style="background-color:#002D5E;">
            <h1 class="text-lg font-bold text-white">Initial Setup</h1>
            <p class="text-sm text-white/75 mt-0.5">Set the farm clock to get started.</p>
        </div>

        {{-- Step indicator --}}
        <div class="shrink-0 flex items-center border-b border-[#e6e6e6] bg-[#f8f8f8]">
            <div class="flex-1 h-1.5 flex">
                <div class="h-full bg-[#002D5E]" style="width: 100%;"></div>
            </div>
        </div>

        <form method="POST" action="{{ route('setup.store') }}" data-turbo="false" onsubmit="loadingButton(this.querySelector('button[type=submit]'))" class="flex-1 flex flex-col min-h-0">
            @csrf

            {{-- Date & Time --}}
            <div class="p-6 flex-1 flex flex-col min-h-0 overflow-y-auto">
                <div class="flex items-center gap-2 mb-1">
                    <i data-lucide="calendar-clock" class="w-4 h-4" style="color:#002D5E;"></i>
                    <h2 class="text-base font-semibold" style="color:#1f1f1f;">Set the Date &amp; Time</h2>
                </div>
                <div class="text-sm mb-4 p-3 rounded-lg" style="background-color:#eef2fb;color:#002D5E;border:1px solid #d6e0f2;">
                    This is a floorless clock with no real-time battery and no internet. After a power cut the date can drift, which would
                    misdate your egg counts, feed logs, and reports. Set the correct farm time now.
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color:#615d59;">Current Farm Date &amp; Time</label>
                        <div class="text-sm font-medium" style="color:#1f1f1f;">{{ $current->format('l, F j, Y g:i A') }} ({{ $timezone }})</div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color:#615d59;">New Date &amp; Time <span class="text-red-500">*</span></label>
                        <input type="datetime-local" name="system_time" required
                               value="{{ old('system_time', $current->format('Y-m-d\TH:i')) }}"
                               class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                               style="border-color:#e6e6e6;color:#1f1f1f;">
                        <x-input-error name="system_time" />
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end shrink-0 border-t border-[#e6e6e6] px-6 py-4 bg-[#f8f8f8]">
                <button type="submit"
                        class="px-5 py-2.5 rounded-lg text-sm font-medium text-white transition-colors hover:brightness-95"
                        style="background-color:#002D5E;">
                    Finish Setup
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') e.preventDefault();
    });

    if (window.lucide) try { lucide.createIcons(); } catch (e) {}
})();
</script>
@endpush
