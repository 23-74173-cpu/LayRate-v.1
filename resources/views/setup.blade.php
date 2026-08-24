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
            <p class="text-sm text-white/75 mt-0.5">A few quick steps to get your farm ready.</p>
        </div>

        {{-- Step indicator --}}
        <div class="shrink-0 flex items-center border-b border-[#e6e6e6] bg-[#f8f8f8]">
            <div class="flex-1 h-1.5 flex">
                <div id="setupProgressFill" class="h-full bg-[#002D5E] transition-all duration-300" style="width: 33.33%;"></div>
            </div>
        </div>
        <div class="grid grid-cols-2 divide-x divide-[#e6e6e6] shrink-0">
            <button type="button" onclick="setupGoTo(0)" class="setup-step-nav py-3 px-2 text-center">
                <div class="w-6 h-6 mx-auto rounded-full flex items-center justify-center text-xs font-bold mb-1" id="setupDot0" style="background-color:#002D5E;color:#fff;">1</div>
                <div class="text-[11px] font-semibold" id="setupLabel0">Date &amp; Time</div>
            </button>
            <button type="button" onclick="setupGoTo(1)" class="setup-step-nav py-3 px-2 text-center">
                <div class="w-6 h-6 mx-auto rounded-full flex items-center justify-center text-xs font-bold mb-1" id="setupDot1" style="background-color:#e6e6e6;color:#615d59;">2</div>
                <div class="text-[11px] font-semibold" id="setupLabel1">Day Reset Time</div>
            </button>
        </div>

        <form method="POST" action="{{ route('setup.store') }}" data-turbo="false" onsubmit="loadingButton(this.querySelector('button[type=submit]'))" class="flex-1 flex flex-col min-h-0">
            @csrf

            {{-- STEP 1: Date & Time --}}
            <div class="setup-step p-6 flex-1 flex flex-col min-h-0 overflow-y-auto">
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

            {{-- STEP 2: Day Reset Time --}}
            <div class="setup-step hidden p-6 flex-1 flex flex-col min-h-0 overflow-y-auto">
                <div class="flex items-center gap-2 mb-1">
                    <i data-lucide="sunset" class="w-4 h-4" style="color:#002D5E;"></i>
                    <h2 class="text-base font-semibold" style="color:#1f1f1f;">Set the Day Reset Time</h2>
                </div>
                <div class="text-sm mb-4 p-3 rounded-lg" style="background-color:#eef2fb;color:#002D5E;border:1px solid #d6e0f2;">
                    Every farm "day" ends at this time. Anything logged before it counts toward the previous day.
                    Most farms reset at 6:00 AM so a single day runs dawn-to-dawn.
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color:#615d59;">Day Reset Time <span class="text-red-500">*</span></label>
                    <input type="time" name="day_reset_time" required value="{{ old('day_reset_time', $resetTime) }}"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color:#e6e6e6;color:#1f1f1f;">
                    <x-input-error name="day_reset_time" />
                </div>
            </div>

            {{-- Footer navigation --}}
            <div class="flex items-center justify-between shrink-0 border-t border-[#e6e6e6] px-6 py-4 bg-[#f8f8f8]">
                <button type="button" id="setupBackBtn" onclick="setupGoTo(setupCurrentStep - 1)"
                        class="px-5 py-2.5 rounded-lg border border-[#e6e6e6] text-sm font-medium text-[#1f1f1f] hover:bg-white transition-colors invisible">
                    Back
                </button>
                <div class="flex items-center justify-end">
                    <button type="button" id="setupNextBtn" onclick="setupNext()"
                            class="px-5 py-2.5 rounded-lg text-sm font-medium text-white transition-colors hover:brightness-95"
                            style="background-color:#002D5E;">
                        Next
                    </button>
                    <button type="submit" id="setupFinishBtn"
                            class="hidden px-5 py-2.5 rounded-lg text-sm font-medium text-white transition-colors hover:brightness-95"
                            style="background-color:#002D5E;">
                        Finish Setup
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
var setupCurrentStep = 0;
var setupTotalSteps = 2;
var setupMaxReached = 0;

window.setupGoTo = function(idx, force) {
    idx = Math.max(0, Math.min(setupTotalSteps - 1, idx));
    // Lock the wizard: cannot jump ahead past the furthest completed step.
    // force is used internally for Back navigation.
    if (!force && idx > setupMaxReached) {
        idx = setupMaxReached;
    }
    setupCurrentStep = idx;
    if (idx > setupMaxReached) setupMaxReached = idx;

    document.querySelectorAll('.setup-step').forEach(function(el, i) {
        el.classList.toggle('hidden', i !== idx);
    });

    document.getElementById('setupBackBtn').classList.toggle('invisible', idx === 0);
    document.getElementById('setupNextBtn').classList.toggle('hidden', idx === setupTotalSteps - 1);
    document.getElementById('setupFinishBtn').classList.toggle('hidden', idx !== setupTotalSteps - 1);

    document.getElementById('setupProgressFill').style.width = (((idx + 1) / setupTotalSteps) * 100) + '%';

    for (var i = 0; i < setupTotalSteps; i++) {
        var dot = document.getElementById('setupDot' + i);
        var label = document.getElementById('setupLabel' + i);
        var done = i < setupMaxReached;
        var reachable = i <= setupMaxReached;
        var active = i === idx;
        if (done) {
            dot.style.backgroundColor = '#2D7D46'; dot.style.color = '#fff'; dot.textContent = '✓';
            label.style.color = '#2D7D46';
        } else if (active) {
            dot.style.backgroundColor = '#002D5E'; dot.style.color = '#fff'; dot.textContent = (i + 1);
            label.style.color = '#002D5E';
        } else if (!reachable) {
            dot.style.backgroundColor = '#e6e6e6'; dot.style.color = '#d1d5db'; dot.textContent = (i + 1);
            label.style.color = '#b6bcc4';
        } else {
            dot.style.backgroundColor = '#e6e6e6'; dot.style.color = '#615d59'; dot.textContent = (i + 1);
            label.style.color = '#6B7280';
        }
    }

    if (window.lucide) try { lucide.createIcons(); } catch (e) {}
};

window.setupNext = function() {
    setupGoTo(setupCurrentStep + 1, true);
};

(function() {
    // Lock the page: no scroll, no Escape dismissal until setup completes.
    document.body.style.overflow = 'hidden';
    window.__setupEscBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') e.preventDefault();
    });

    setupGoTo(0);
})();
</script>
@endpush
