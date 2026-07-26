@extends('layouts.app')
@section('title', 'Egg Management')

@section('content')
<div class="space-y-5">

    <x-page-header title="Egg Management" subtitle="Review and manage egg production records" subtitle-id="egg-header-subtitle" actions-id="egg-header-actions" />

    @include('eggs._tabs', ['activeTab' => 'recent-logs'])

    <turbo-frame id="egg-content">

    <x-card header="Production Logs">
        <form id="recentLogsForm" onsubmit="return false" class="mb-4">
            <div class="flex flex-wrap items-end gap-4">
                {{-- Cage filter --}}
                <div>
                    <label class="block text-xs mb-1" style="color: #615d59;">Cage</label>
                    <select name="cage_id" onchange="recentLogsFilter()"
                            class="border rounded-lg px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">All Cages</option>
                        @foreach($cages as $c)
                        <option value="{{ $c->id }}" {{ $filters['cage_id'] == $c->id ? 'selected' : '' }}>
                            {{ $c->cage_code }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Slot filter --}}
                <div>
                    <label class="block text-xs mb-1" style="color: #615d59;">Slot</label>
                    <select name="cage_slot_id" onchange="recentLogsFilter()"
                            class="border rounded-lg px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">All Slots</option>
                        @foreach($cageSlots as $slot)
                        <option value="{{ $slot->id }}" {{ $filters['cage_slot_id'] == $slot->id ? 'selected' : '' }}>
                            {{ $slot->cage->cage_code }} · R{{ $slot->row_number }}-C{{ $slot->column_number }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Breed filter --}}
                <div>
                    <label class="block text-xs mb-1" style="color: #615d59;">Breed</label>
                    <select name="breed" onchange="recentLogsFilter()"
                            class="border rounded-lg px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">All Breeds</option>
                        @foreach($breeds as $b)
                        <option value="{{ $b }}" {{ $filters['breed'] == $b ? 'selected' : '' }}>
                            {{ $b }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Manual vs Sensor filter (checkbox chips) --}}
                <div>
                    <label class="block text-xs mb-1" style="color: #615d59;">Logged Via</label>
                    <div class="flex items-center gap-3">
                        @foreach(['manual' => 'Manual', 'sensor' => 'Sensor', 'unknown' => 'Unknown'] as $value => $label)
                        <label class="inline-flex items-center gap-1.5 text-sm cursor-pointer" style="color: #31302e;">
                            <input type="checkbox" name="logged_via[]" value="{{ $value }}" onchange="recentLogsFilter()"
                                   {{ in_array($value, (array) $filters['logged_via']) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-[#0075de] focus:ring-[#0075de]">
                            {{ $label }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('eggs.recent-logs') }}"
                       class="px-3 py-1.5 text-xs font-medium rounded-lg border border-[#e6e6e6] text-[#1f1f1f] hover:bg-[#f6f5f4] transition-colors">
                        Reset
                    </a>
                </div>
            </div>
        </form>

        <turbo-frame id="egg-logs-list" src="{{ route('eggs.logging.logs', request()->only(['cage_id', 'cage_slot_id', 'breed', 'logged_via'])) }}" loading="lazy">
            @include('egg-logging._logs-skeleton')
        </turbo-frame>
    </x-card>

    @include('egg-logging._edit-modal')

<script>
function recentLogsFilter() {
    var form = document.getElementById('recentLogsForm');
    if (!form) return;
    var params = new URLSearchParams(new FormData(form));
    var frame = document.querySelector('turbo-frame#egg-logs-list');
    if (frame) frame.setAttribute('src', '/eggs/logging/logs?' + params.toString());
}

function openEditLog(id, date, eggCount, henCount, notes, cageSlotId, sizeSmall, sizeMedium, sizeLarge, sizeJumbo) {
    document.getElementById('editLogForm').action = '/eggs/logging/' + id;
    document.getElementById('editLogDate').value = date;
    document.getElementById('editEggCount').value = eggCount;
    document.getElementById('editHenCountDisplay').value = henCount;
    document.getElementById('editNotes').value = notes || '';
    document.getElementById('editSizeSmall').value = sizeSmall ?? 0;
    document.getElementById('editSizeMedium').value = sizeMedium ?? 0;
    document.getElementById('editSizeLarge').value = sizeLarge ?? 0;
    document.getElementById('editSizeJumbo').value = sizeJumbo ?? 0;
    document.getElementById('editLogModal').style.display = 'flex';
    editComputeHdep();
    editCheckSizeSum();
    lucide.createIcons();
}

function closeEditLogModal() {
    document.getElementById('editLogModal').style.display = 'none';
}

function editComputeHdep() {
    const eggs = parseInt(document.getElementById('editEggCount').value) || 0;
    const hens = parseInt(document.getElementById('editHenCountDisplay').value) || 1;
    const hdep = ((eggs / hens) * 100).toFixed(1);
    const el = document.getElementById('editHdepDisplay');
    el.textContent = 'HDEP:  ' + hdep + '%';
    el.style.backgroundColor = eggs > hens ? '#fbe4e6' : '#f6f5f4';
    el.style.borderColor = eggs > hens ? '#f3cdd0' : '#e6e6e6';
    el.style.color = eggs > hens ? '#9b1c24' : '#1f1f1f';
}

(function() {
    if (window.__recentLogsBound) return;
    window.__recentLogsBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var modal = document.getElementById('editLogModal');
            if (modal && !modal.classList.contains('hidden')) closeEditLogModal();
        }
    });
})();
</script>
</turbo-frame>
</div>
@endsection