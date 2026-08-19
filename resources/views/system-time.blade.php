@extends('layouts.app')
@section('title', 'Set System Time')

@section('content')
<div class="max-w-xl mx-auto space-y-5">
    <x-page-header title="Set System Time" subtitle="Manually set the Pi clock when offline." />

    <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
        <div class="mb-4">
            <h2 class="text-base font-medium text-[#333333] mb-1">Current System Time</h2>
            <p class="text-sm text-[#6B7280]">
                Detected: <span class="font-medium text-[#333333]">{{ $current->format('l, F j, Y g:i A') }} (Asia/Manila)</span>
            </p>
            <p class="text-xs text-[#6B7280] mt-1">
                PHP timezone: <span class="font-mono">{{ $timezone }}</span>
            </p>
        </div>

        <form method="POST" action="{{ route('settings.system-time.update') }}" class="space-y-4">
            @csrf
            <div>
                <label for="system_time" class="block text-sm text-[#333333] mb-1.5">New Date &amp; Time</label>
                <input id="system_time" type="datetime-local" name="system_time" required
                       value="{{ old('system_time', $current->format('Y-m-d\TH:i')) }}"
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]">
                @error('system_time')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="bg-[#FFF3CD] border border-[#FFE69C] rounded-lg p-4 text-sm text-[#664D03]">
                <p class="font-medium mb-1">Why is this needed?</p>
                <p>This Raspberry Pi has no real-time clock (RTC) and no internet access. After a power-off, the system clock may be wrong. Setting it here keeps egg counts, feed logs, and reports on the correct farm day.</p>
            </div>

            <div class="flex gap-3">
                <a href="{{ route('dashboard') }}" class="px-5 py-2.5 rounded-lg text-sm border border-[#D9D9D9] text-[#333333] hover:bg-gray-50">
                    Skip for now
                </a>
                <button type="submit" class="bg-[#002D5E] text-white px-5 py-2.5 rounded-lg text-sm hover:bg-[#001F42]">
                    Set System Time
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
