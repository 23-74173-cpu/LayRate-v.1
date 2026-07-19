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

    {{-- Live Data (lazy): metrics, tabs, batches, consumption --}}
    <turbo-frame id="feed-live-data" src="{{ route('feed.live-data', request()->only('cage_id')) }}" loading="lazy">
        @include('feed._live-data-skeleton')
    </turbo-frame>
</div>

{{-- Add Batch Modal --}}
<div id="addBatchModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeAddBatchModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6 overflow-y-auto max-h-[90vh]" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Add Feed Batch</h2>
            <button type="button" onclick="closeAddBatchModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('feed.batch.store') }}" onsubmit="loadingButton(this.querySelector('button[type=submit]'), 'Adding\u2026')">
            @csrf
            <p class="text-xs text-[#6B7280] mb-4">Batch code is auto-generated (e.g. F-2026-001).</p>

            <label class="block text-sm text-[#333333] mb-1.5">Brand</label>
            <input name="brand" placeholder="e.g. Purina, Nutrena"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Crude Protein % <span class="text-[#9B1C24]">*</span></label>
            <input name="crude_protein" type="number" step="0.1" min="0" max="100" required
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Total Quantity (kg)</label>
                    <input name="total_quantity_kg" type="number" step="0.01" min="0"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Unit Cost (per kg)</label>
                    <input name="unit_cost" type="number" step="0.01" min="0"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                </div>
            </div>

            <label class="block text-sm text-[#333333] mb-1.5">Date Received <span class="text-[#9B1C24]">*</span></label>
            <input name="date_received" type="date" value="{{ now()->toDateString() }}" required
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Low Stock Threshold (kg)</label>
            <input name="low_stock_threshold" type="number" step="0.01" min="0" placeholder="e.g. 100"
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
                <x-button type="submit" class="flex-1 py-2.5">Add Batch</x-button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Batch Modal --}}
<div id="editBatchModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEditBatchModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6 overflow-y-auto max-h-[90vh]" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Edit Feed Batch</h2>
            <button type="button" onclick="closeEditBatchModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>
        <form id="editBatchForm" method="POST" onsubmit="loadingButton(this.querySelector('button[type=submit]'))">
            @csrf @method('PUT')

            <label class="block text-sm text-[#333333] mb-1.5">Brand</label>
            <input id="editBrand" name="brand"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Crude Protein % <span class="text-[#9B1C24]">*</span></label>
            <input id="editCp" name="crude_protein" type="number" step="0.1"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Total Quantity (kg)</label>
                    <input id="editQty" name="total_quantity_kg" type="number" step="0.01" min="0"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Unit Cost (per kg)</label>
                    <input id="editCost" name="unit_cost" type="number" step="0.01" min="0"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                </div>
            </div>

            <label class="block text-sm text-[#333333] mb-1.5">Low Stock Threshold (kg)</label>
            <input id="editThreshold" name="low_stock_threshold" type="number" step="0.01" min="0"
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

{{-- Add/Edit Consumption Modal --}}
<div id="consumptionModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeConsumptionModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 id="consumptionModalTitle" class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Log Consumption</h2>
            <button type="button" onclick="closeConsumptionModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>
        <form id="consumptionForm" method="POST" action="{{ route('feed.consumption.store') }}" onsubmit="loadingButton(this.querySelector('button[type=submit]'), 'Saving\u2026')">
            @csrf
            <input type="hidden" name="_method" id="consumptionMethod" value="POST">

            <label class="block text-sm text-[#333333] mb-1.5">Cage <span class="text-[#9B1C24]">*</span></label>
            <select name="cage_id" required
                    class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                <option value="">Select cage...</option>
            </select>

            <label class="block text-sm text-[#333333] mb-1.5">Feed Batch <span class="text-[#9B1C24]">*</span></label>
            <select name="feed_batch_id" required
                    class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                <option value="">Select batch...</option>
            </select>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Date <span class="text-[#9B1C24]">*</span></label>
                    <input name="log_date" type="date" value="{{ now()->toDateString() }}" required
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Time</label>
                    <input name="log_time" type="time"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                </div>
            </div>

            <label class="block text-sm text-[#333333] mb-1.5">Consumed (kg) <span class="text-[#9B1C24]">*</span></label>
            <input name="feed_consumed_kg" type="number" step="0.01" min="0" required
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-5 focus:outline-none focus:border-[#002D5E]">

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeConsumptionModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">Cancel</button>
                <x-button type="submit" class="flex-1 py-2.5">Save</x-button>
            </div>
        </form>
    </div>
</div>

{{-- Whole-Farm Entry Modal --}}
<div id="farmEntryModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeFarmEntryModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 id="farmEntryModalTitle" class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Log Whole-Farm Feeding</h2>
            <button type="button" onclick="closeFarmEntryModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>
        <form id="farmEntryForm" method="POST" action="{{ route('feed.farm-entry.store') }}" onsubmit="loadingButton(this.querySelector('button[type=submit]'), 'Saving\u2026')">
            @csrf
            <input type="hidden" name="_method" id="farmEntryMethod" value="POST">

            <label class="block text-sm text-[#333333] mb-1.5">Feed Batch <span class="text-[#9B1C24]">*</span></label>
            <select name="feed_batch_id" required
                    class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                <option value="">Select batch...</option>
            </select>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Date <span class="text-[#9B1C24]">*</span></label>
                    <input name="log_date" type="date" value="{{ now()->toDateString() }}" required
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Time</label>
                    <input name="log_time" type="time"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                </div>
            </div>

            <label class="block text-sm text-[#333333] mb-1.5">Total Fed (kg) <span class="text-[#9B1C24]">*</span></label>
            <input name="total_kg" type="number" step="0.01" min="0" required
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Unit Cost (per kg)</label>
            <input name="unit_cost" type="number" step="0.01" min="0"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-5 focus:outline-none focus:border-[#002D5E]">

            <p class="text-xs text-[#6B7280] mb-5">
                This will proportionally split the total across active cages by hen count. Distributed rows appear as "estimated" in the consumption log.
            </p>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeFarmEntryModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">Cancel</button>
                <x-button type="submit" class="flex-1 py-2.5">Distribute</x-button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
function openEditBatch(id, brand, cp, qty, cost, threshold, notes) {
    document.getElementById('editBatchForm').action = '/feed/batch/' + id;
    document.getElementById('editBrand').value     = brand || '';
    document.getElementById('editCp').value        = cp;
    document.getElementById('editQty').value       = qty || '';
    document.getElementById('editCost').value      = cost || '';
    document.getElementById('editThreshold').value = threshold || '';
    document.getElementById('editNotes').value     = notes || '';
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

function openConsumptionModal(cageId, batchId, date, time, kg, entryId) {
    var title = entryId ? 'Edit Consumption' : 'Log Consumption';
    document.getElementById('consumptionModalTitle').textContent = title;

    var form = document.getElementById('consumptionForm');
    var method = document.getElementById('consumptionMethod');

    if (entryId) {
        form.action = '/feed/consumption/' + entryId;
        method.value = 'PUT';
    } else {
        form.action = '{{ route('feed.consumption.store') }}';
        method.value = 'POST';
    }

    var cageSelect = document.querySelector('#consumptionModal select[name="cage_id"]');
    var batchSelect = document.querySelector('#consumptionModal select[name="feed_batch_id"]');

    cageSelect.value = cageId || '';
    batchSelect.value = batchId || '';
    document.querySelector('#consumptionModal input[name="log_date"]').value = date || '{{ now()->toDateString() }}';
    document.querySelector('#consumptionModal input[name="log_time"]').value = time || '';
    document.querySelector('#consumptionModal input[name="feed_consumed_kg"]').value = kg || '';

    document.getElementById('consumptionModal').classList.remove('hidden');
    document.getElementById('consumptionModal').classList.add('flex');
}

function closeConsumptionModal() {
    document.getElementById('consumptionModal').classList.add('hidden');
    document.getElementById('consumptionModal').classList.remove('flex');
}

function openFarmEntryModal(entryId, batchId, date, time, totalKg, unitCost) {
    var title = entryId ? 'Edit Whole-Farm Feeding' : 'Log Whole-Farm Feeding';
    document.getElementById('farmEntryModalTitle').textContent = title;

    var form = document.getElementById('farmEntryForm');
    var method = document.getElementById('farmEntryMethod');

    if (entryId) {
        form.action = '/feed/farm-entry/' + entryId;
        method.value = 'PUT';
    } else {
        form.action = '{{ route('feed.farm-entry.store') }}';
        method.value = 'POST';
    }

    document.querySelector('#farmEntryModal select[name="feed_batch_id"]').value = batchId || '';
    document.querySelector('#farmEntryModal input[name="log_date"]').value = date || '{{ now()->toDateString() }}';
    document.querySelector('#farmEntryModal input[name="log_time"]').value = time || '';
    document.querySelector('#farmEntryModal input[name="total_kg"]').value = totalKg || '';
    document.querySelector('#farmEntryModal input[name="unit_cost"]').value = unitCost || '';

    document.getElementById('farmEntryModal').classList.remove('hidden');
    document.getElementById('farmEntryModal').classList.add('flex');
}

function closeFarmEntryModal() {
    document.getElementById('farmEntryModal').classList.add('hidden');
    document.getElementById('farmEntryModal').classList.remove('flex');
}

    (function() {
        if (window.__feedModalEscapeBound) return;
        window.__feedModalEscapeBound = true;
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddBatchModal();
                closeEditBatchModal();
                closeConsumptionModal();
                closeFarmEntryModal();
            }
        });
    })();

function deleteBatch(id) {
    // url() keeps this working under both artisan serve and the XAMPP subfolder
    fetch('{{ url('feed/batch') }}/' + id + '/delete-check')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.can_delete) {
                var form = document.getElementById('delete-batch-form-' + id);
                if (!form) {
                    form = document.createElement('form');
                    form.id = 'delete-batch-form-' + id;
                    form.method = 'POST';
                    form.action = '{{ url('feed/batch') }}/' + id;
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
            showNotification('Could not check batch status. Please try again.', 'error');
        });
}
</script>
@endpush
