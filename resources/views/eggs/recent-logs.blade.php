@extends('layouts.app')
@section('title', 'Recent Logs')

@section('content')
<div class="space-y-5">

    <x-page-header title="Recent Logs" subtitle="Review and manage egg production records" />

    @include('eggs._tabs', ['activeTab' => 'recent-logs'])

    <x-card header="Production Logs">
        <div class="flex items-center gap-4 mb-4">
            <label class="text-xs" style="color: #615d59;">Cage:</label>
            <select onchange="window.location.href = this.value ? '?cage_id=' + this.value : '?'"
                    class="border rounded-lg px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                    style="border-color: #e6e6e6; color: #1f1f1f;">
                <option value="">All Cages</option>
                @foreach($cages as $c)
                <option value="{{ $c->id }}" {{ $cageFilter == $c->id ? 'selected' : '' }}>
                    {{ $c->cage_code }}
                </option>
                @endforeach
            </select>
        </div>
        <turbo-frame id="egg-logs-list" src="{{ route('eggs.logging.logs', ['cage_id' => $cageFilter]) }}" loading="lazy">
            @include('egg-logging._logs-skeleton')
        </turbo-frame>
    </x-card>

    @include('egg-logging._edit-modal')

</div>
@endsection

@push('scripts')
<script>
function openEditLog(id, date, eggCount, henCount, notes, cageSlotId) {
    document.getElementById('editLogForm').action = '/eggs/logging/' + id;
    document.getElementById('editLogDate').value = date;
    document.getElementById('editEggCount').value = eggCount;
    document.getElementById('editHenCount').value = henCount;
    document.getElementById('editNotes').value = notes || '';
    document.getElementById('editLogModal').style.display = 'flex';
    editComputeHdep();
    lucide.createIcons();
}

function closeEditLogModal() {
    document.getElementById('editLogModal').style.display = 'none';
}

function editComputeHdep() {
    const eggs = parseInt(document.getElementById('editEggCount').value) || 0;
    const hens = parseInt(document.getElementById('editHenCount').value) || 1;
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
@endpush