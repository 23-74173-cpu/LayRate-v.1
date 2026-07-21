@extends('layouts.app')
@section('title', 'Confirm Delete Cage')

@section('content')
<div class="max-w-lg mx-auto">
    @if ($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-5 text-sm" style="color: #9b1c24;">
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <x-card>
        <x-slot:headerSlot>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <i data-lucide="alert-triangle" class="w-5 h-5 text-red-600"></i>
                </div>
                <div>
                    <h1 class="text-lg font-semibold" style="color: #1f1f1f;">Delete {{ $cage->cage_code }}?</h1>
                    <p class="text-sm" style="color: #6B7280;">This action cannot be undone.</p>
                </div>
            </div>
        </x-slot:headerSlot>

        <form method="POST" action="{{ route('cages.force-destroy', $cage) }}" id="deleteForm">
            @csrf @method('DELETE')

            {{-- Data-loss summary --}}
            <div class="mb-5">
                <p class="text-sm font-semibold mb-2" style="color: #1f1f1f;">Records that will be permanently deleted:</p>
                <div class="bg-[#F9F9F7] border border-[#D9D9D9] rounded-lg p-4 space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <span style="color: #6B7280;">Cage slots</span>
                        <span class="font-medium" style="color: #1f1f1f;">{{ $slotCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6B7280;">Sensor-equipped slots</span>
                        <span class="font-medium" style="color: #1f1f1f;">{{ $sensorSlotCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6B7280;">Active hen records</span>
                        <span class="font-medium" style="color: {{ $henCount > 0 ? '#9b1c24' : '#1f1f1f' }};">{{ $henCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6B7280;">Production logs</span>
                        <span class="font-medium" style="color: {{ $productionLogCount > 0 ? '#9b1c24' : '#1f1f1f' }};">{{ $productionLogCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6B7280;">Environmental logs</span>
                        <span class="font-medium" style="color: {{ $envLogCount > 0 ? '#9b1c24' : '#1f1f1f' }};">{{ $envLogCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6B7280;">Feed consumption logs</span>
                        <span class="font-medium" style="color: {{ $feedLogCount > 0 ? '#9b1c24' : '#1f1f1f' }};">{{ $feedLogCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6B7280;">Mortality records</span>
                        <span class="font-medium" style="color: {{ $mortalityCount > 0 ? '#9b1c24' : '#1f1f1f' }};">{{ $mortalityCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6B7280;">Alerts</span>
                        <span class="font-medium" style="color: {{ $alertCount > 0 ? '#9b1c24' : '#1f1f1f' }};">{{ $alertCount }}</span>
                    </div>
                </div>
            </div>

            {{-- Preserve options --}}
            <div class="mb-5">
                <p class="text-sm font-semibold mb-2" style="color: #1f1f1f;">Preserve historical data</p>
                <p class="text-xs mb-3" style="color: #6B7280;">Check the types of records you want to keep (FKeys will be nulled, records themselves are preserved).</p>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="preserve_production" value="1" {{ old('preserve_production', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-[#0075de] focus:ring-[#0075de]">
                        <span style="color: #1f1f1f;">Preserve production logs ({{ $productionLogCount }})</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="preserve_mortality" value="1" {{ old('preserve_mortality', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-[#0075de] focus:ring-[#0075de]">
                        <span style="color: #1f1f1f;">Preserve mortality records ({{ $mortalityCount }})</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="preserve_feed" value="1" {{ old('preserve_feed', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-[#0075de] focus:ring-[#0075de]">
                        <span style="color: #1f1f1f;">Preserve feed consumption logs ({{ $feedLogCount }})</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="preserve_environment" value="1" {{ old('preserve_environment', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-[#0075de] focus:ring-[#0075de]">
                        <span style="color: #1f1f1f;">Preserve environmental logs ({{ $envLogCount }})</span>
                    </label>
                </div>
            </div>

            {{-- Hens action --}}
            <div class="mb-5">
                <p class="text-sm font-semibold mb-2" style="color: #1f1f1f;">Active hens ({{ $henCount }})</p>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="radio" name="hens_action" value="move" {{ old('hens_action', 'move') === 'move' ? 'checked' : '' }}
                               class="border-gray-300 text-[#0075de] focus:ring-[#0075de]">
                        <span style="color: #1f1f1f;">Detach hens (null out cage_slot_id, keep hen records)</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="radio" name="hens_action" value="delete" {{ old('hens_action') === 'delete' ? 'checked' : '' }}
                               class="border-gray-300 text-[#9b1c24] focus:ring-[#9b1c24]">
                        <span style="color: #9b1c24;">Delete all hen records permanently</span>
                    </label>
                </div>
            </div>

            {{-- Return sensors --}}
            <div class="mb-5">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="return_sensors" value="1" {{ old('return_sensors', true) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-[#0075de] focus:ring-[#0075de]">
                    <span style="color: #1f1f1f;">Return sensors to spare inventory</span>
                </label>
            </div>

            {{-- Type to confirm --}}
            <div class="mb-5">
                <label class="block text-sm font-semibold mb-1.5" style="color: #1f1f1f;">
                    Type <span class="font-mono font-bold text-red-600">{{ $cage->cage_code }}</span> to confirm
                </label>
                <input type="text" name="confirmation" id="confirmationInput" value="{{ old('confirmation') }}" autocomplete="off" required
                       oninput="document.getElementById('deleteBtn').disabled = (this.value !== '{{ $cage->cage_code }}')"
                       class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1"
                       style="border-color: #e6e6e6; color: #1f1f1f;"
                       placeholder="Type {{ $cage->cage_code }} here...">
                <x-input-error name="confirmation" />
            </div>

            <div class="flex gap-3">
                <a href="{{ route('cages.index') }}"
                   class="flex-1 border border-[#D9D9D9] py-2.5 rounded-lg text-sm text-center hover:bg-[#F5F6F8]"
                   style="color: #6B7280;">
                    Cancel
                </a>
                <button type="submit" id="deleteBtn" disabled
                        class="flex-1 py-2.5 text-sm font-medium rounded-full text-white transition-opacity disabled:opacity-40 disabled:cursor-not-allowed"
                        style="background-color: #9b1c24;">
                    Delete {{ $cage->cage_code }}
                </button>
            </div>
        </form>
    </x-card>
</div>
@endsection