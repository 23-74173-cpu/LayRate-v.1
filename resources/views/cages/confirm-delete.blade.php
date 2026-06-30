@extends('layouts.app')
@section('title', 'Confirm Delete Cage')

@section('content')
<main class="p-5 max-w-lg">
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-red-600"></i>
            </div>
            <h1 class="text-lg font-medium text-[#333333]">Delete {{ $cage->cage_code }}?</h1>
        </div>

        <p class="text-sm text-[#6B7280] mb-5">
            This will permanently delete the cage and all associated data. This action cannot be undone.
        </p>

        <div class="bg-[#F9F9F7] border border-[#D9D9D9] rounded-lg p-4 mb-5 space-y-1.5">
            <div class="flex justify-between text-sm">
                <span class="text-[#6B7280]">Slots</span>
                <span class="font-medium text-[#333333]">{{ $slotCount }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-[#6B7280]">Sensor-equipped slots</span>
                <span class="font-medium text-[#333333]">{{ $sensorSlotCount }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-[#6B7280]">Hen records</span>
                <span class="font-medium text-[#333333]">{{ $henCount }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-[#6B7280]">Production logs</span>
                <span class="font-medium text-[#333333]">{{ $productionLogCount }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-[#6B7280]">Environmental logs</span>
                <span class="font-medium text-[#333333]">{{ $envLogCount }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-[#6B7280]">Feed consumption logs</span>
                <span class="font-medium text-[#333333]">{{ $feedLogCount }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-[#6B7280]">Mortality records</span>
                <span class="font-medium text-[#333333]">{{ $mortalityCount }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-[#6B7280]">Alerts</span>
                <span class="font-medium text-[#333333]">{{ $alertCount }}</span>
            </div>
        </div>

        <div class="flex gap-3">
            <a href="{{ route('cages.index') }}"
               class="flex-1 border border-[#D9D9D9] text-[#6B7280] py-2.5 rounded-lg text-sm text-center hover:bg-[#F5F6F8]">
                Cancel
            </a>
            <form method="POST" action="{{ route('cages.force-destroy', $cage) }}" class="flex-1">
                @csrf @method('DELETE')
                <button type="submit"
                        class="w-full bg-red-600 text-white py-2.5 rounded-lg text-sm hover:bg-red-700">
                    Delete Forever
                </button>
            </form>
        </div>
    </div>
</main>
@endsection
