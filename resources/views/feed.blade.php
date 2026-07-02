@extends('layouts.app')
@section('title', 'Feed & Nutrition')

@section('content')
<div class="space-y-5">

    <x-page-header title="Feed & Nutrition" subtitle="Track feed batches, crude protein, and daily consumption">
        <x-slot:actions>
            <button onclick="document.getElementById('addBatchModal').classList.remove('hidden'); document.getElementById('addBatchModal').classList.add('flex');"
                    class="flex items-center gap-2 bg-[#002D5E] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#001F42] transition-colors">
                <i data-lucide="plus" class="w-4 h-4"></i> Add Feed Batch
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- ── Live Data (lazy): metrics, tabs, batches, consumption ── --}}
    <turbo-frame id="feed-live-data" src="{{ route('feed.live-data') }}" loading="lazy">
        @include('feed._live-data-skeleton')
    </turbo-frame>
</div>

{{-- Add Batch Modal --}}
<div id="addBatchModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    {{-- Backdrop --}}
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeAddBatchModal()"></div>

    {{-- Card --}}
    <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Add Feed Batch</h2>
            <button type="button" onclick="closeAddBatchModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('feed.batch.store') }}" onsubmit="loadingButton(this.querySelector('button[type=submit]'), 'Adding\u2026')">
            @csrf
            <label class="block text-sm text-[#333333] mb-1.5">Batch Code</label>
            <input name="batch_code" placeholder="e.g. F-004" required
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
            <label class="block text-sm text-[#333333] mb-1.5">Crude Protein %</label>
            <input name="crude_protein" type="number" step="0.1" min="0" max="100" required
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
            <label class="block text-sm text-[#333333] mb-1.5">Date Received</label>
            <input name="date_received" type="date" value="{{ now()->toDateString() }}" required
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
            <label class="block text-sm text-[#333333] mb-1.5">Notes</label>
            <textarea name="notes" rows="2"
                      class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-5 focus:outline-none focus:border-[#002D5E]"></textarea>
            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeAddBatchModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 text-sm font-medium rounded-full text-white transition-opacity" style="background-color: #0075de;" onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">Add Batch</button>
            </div>
        </form>
    </div>
</div>

<x-confirm-modal />

{{-- Edit Batch Modal --}}
<div id="editBatchModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    {{-- Backdrop --}}
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEditBatchModal()"></div>

    {{-- Card --}}
    <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Edit Feed Batch</h2>
            <button type="button" onclick="closeEditBatchModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>
        <form id="editBatchForm" method="POST" onsubmit="loadingButton(this.querySelector('button[type=submit]'))">
            @csrf @method('PUT')
            <label class="block text-sm text-[#333333] mb-1.5">Crude Protein %</label>
            <input id="editCp" name="crude_protein" type="number" step="0.1"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
            <label class="block text-sm text-[#333333] mb-1.5">Notes</label>
            <textarea id="editNotes" name="notes" rows="2"
                      class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-5 focus:outline-none focus:border-[#002D5E]"></textarea>
            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeEditBatchModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 text-sm font-medium rounded-full text-white transition-opacity" style="background-color: #0075de;" onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openEditBatch(id, cp, notes) {
    document.getElementById('editBatchForm').action = '/feed/batch/' + id;
    document.getElementById('editCp').value    = cp;
    document.getElementById('editNotes').value = notes;
    document.getElementById('editBatchModal').classList.remove('hidden');
    document.getElementById('editBatchModal').classList.add('flex');
}

function closeAddBatchModal() {
    document.getElementById('addBatchModal').classList.add('hidden');
    document.getElementById('addBatchModal').classList.remove('flex');
}

function closeEditBatchModal() {
    document.getElementById('editBatchModal').classList.add('hidden');
    document.getElementById('editBatchModal').classList.remove('flex');
}

// Escape key closes modals
(function() {
    if (window.__feedModalEscapeBound) return;
    window.__feedModalEscapeBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddBatchModal();
            closeEditBatchModal();
        }
    });
})();

function deleteBatch(id) {
    fetch('/feed/batch/' + id + '/delete-check')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.can_delete) {
                var form = document.getElementById('delete-batch-form-' + id);
                if (!form) {
                    form = document.createElement('form');
                    form.id = 'delete-batch-form-' + id;
                    form.method = 'POST';
                    form.action = '/feed/batch/' + id;
                    form.style.display = 'none';
                    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    form.innerHTML = '<input type="hidden" name="_token" value="' + csrf + '"><input type="hidden" name="_method" value="DELETE">';
                    document.body.appendChild(form);
                }
                confirmModal('Delete this feed batch? All associated data will be permanently removed.', form, 'Delete');
            } else {
                confirmModal('This batch has ' + data.count + ' recorded consumption log(s) and cannot be deleted. Remove those records first.', null, 'Got it');
            }
        })
        .catch(function() {
            alert('Could not check batch status. Please try again.');
        });
}
</script>
@endpush
