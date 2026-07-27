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
        <form method="POST" action="{{ route('feed.batch.store') }}"
              data-turbo="false" data-feed-ajax="tab-batches-frame">
            @csrf
            <p class="text-xs text-[#6B7280] mb-4">Batch code is auto-generated (e.g. F-2026-001).</p>

            <label class="block text-sm text-[#333333] mb-1.5">Brand</label>
            <input name="brand" value="{{ old('brand') }}" placeholder="e.g. Purina, Nutrena"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Crude Protein % <span class="text-[#9B1C24]">*</span></label>
            <input name="crude_protein" type="number" step="0.1" min="0" max="100" value="{{ old('crude_protein') }}" required
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
            <x-input-error name="crude_protein" />

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Total Quantity (kg)</label>
                    <input name="total_quantity_kg" type="number" step="0.01" min="0" value="{{ old('total_quantity_kg') }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Unit Cost (per kg)</label>
                    <input name="unit_cost" type="number" step="0.01" min="0" value="{{ old('unit_cost') }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                </div>
            </div>

            <label class="block text-sm text-[#333333] mb-1.5">Date Received <span class="text-[#9B1C24]">*</span></label>
            <input name="date_received" type="date" value="{{ old('date_received', now()->toDateString()) }}" required
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
            <x-input-error name="date_received" />

            <label class="block text-sm text-[#333333] mb-1.5">Low Stock Threshold (kg)</label>
            <input name="low_stock_threshold" type="number" step="0.01" min="0" value="{{ old('low_stock_threshold') }}" placeholder="e.g. 100"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Notes</label>
            <textarea name="notes" rows="2"
                      class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-5 focus:outline-none focus:border-[#002D5E]">{{ old('notes') }}</textarea>

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

@if(session('reopen_add_batch'))
<x-modal-reopen modal-id="addBatchModal" session-key="reopen_add_batch" guard="addBatch">
    document.getElementById('addBatchModal').classList.remove('hidden');
    document.getElementById('addBatchModal').classList.add('flex');
</x-modal-reopen>
@endif

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
        <form id="editBatchForm" method="POST" data-turbo="false" data-feed-ajax="tab-batches-frame">
            @csrf @method('PUT')

            <label class="block text-sm text-[#333333] mb-1.5">Brand</label>
            <input id="editBrand" name="brand" value="{{ old('brand') }}"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Crude Protein % <span class="text-[#9B1C24]">*</span></label>
            <input id="editCp" name="crude_protein" type="number" step="0.1" value="{{ old('crude_protein') }}"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
            <x-input-error name="crude_protein" />

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Total Quantity (kg)</label>
                    <input id="editQty" name="total_quantity_kg" type="number" step="0.01" min="0" value="{{ old('total_quantity_kg') }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Unit Cost (per kg)</label>
                    <input id="editCost" name="unit_cost" type="number" step="0.01" min="0" value="{{ old('unit_cost') }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                </div>
            </div>

            <label class="block text-sm text-[#333333] mb-1.5">Low Stock Threshold (kg)</label>
            <input id="editThreshold" name="low_stock_threshold" type="number" step="0.01" min="0" value="{{ old('low_stock_threshold') }}"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Notes</label>
            <textarea id="editNotes" name="notes" rows="2"
                      class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-5 focus:outline-none focus:border-[#002D5E]">{{ old('notes') }}</textarea>

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

@if(session('reopen_edit_batch'))
@php $editBatch = app(\App\Http\Controllers\FeedController::class)->batchSessionData(); @endphp
@if($editBatch)
<x-modal-reopen modal-id="editBatchModal" session-key="reopen_edit_batch" guard="editBatch">
    openEditBatch(
        {{ $editBatch->id }},
        '{{ addslashes($editBatch->brand ?? '') }}',
        {{ $editBatch->crude_protein }},
        {{ $editBatch->total_quantity_kg ?? 'null' }},
        {{ $editBatch->unit_cost ?? 'null' }},
        {{ $editBatch->low_stock_threshold ?? 'null' }},
        '{{ addslashes($editBatch->notes ?? '') }}'
    );
</x-modal-reopen>
@endif
@endif

{{-- Add/Edit Consumption Modal --}}
<div id="consumptionModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeConsumptionModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 id="consumptionModalTitle" class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Log Consumption</h2>
            <button type="button" onclick="closeConsumptionModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>
        <form id="consumptionForm" method="POST" action="{{ route('feed.consumption.store') }}" data-turbo="false" data-feed-ajax="tab-consumption-frame">
            @csrf
            <input type="hidden" name="_method" id="consumptionMethod" value="POST">

            <label class="block text-sm text-[#333333] mb-1.5">Cage <span class="text-[#9B1C24]">*</span></label>
            <select name="cage_id" required
                    class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                <option value="">Select cage...</option>
            </select>
            <x-input-error name="cage_id" />

            <label class="block text-sm text-[#333333] mb-1.5">Feed Batch <span class="text-[#9B1C24]">*</span></label>
            <select name="feed_batch_id" required
                    class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                <option value="">Select batch...</option>
            </select>
            <x-input-error name="feed_batch_id" />

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Date <span class="text-[#9B1C24]">*</span></label>
                    <input name="log_date" type="date" value="{{ old('log_date', now()->toDateString()) }}" required
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                    <x-input-error name="log_date" />
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Time</label>
                    <input name="log_time" type="time" value="{{ old('log_time') }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                </div>
            </div>

            <label class="block text-sm text-[#333333] mb-1.5">Consumed (kg) <span class="text-[#9B1C24]">*</span></label>
            <input name="feed_consumed_kg" type="number" step="0.01" min="0" required id="consumptionKgInput" value="{{ old('feed_consumed_kg') }}"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-1 focus:outline-none focus:border-[#002D5E]">
            <x-input-error name="feed_consumed_kg" />
            <p id="consumptionExceedsWarning" class="hidden text-xs text-[#9B1C24] mb-4"></p>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeConsumptionModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">Cancel</button>
                <x-button type="submit" class="flex-1 py-2.5" id="consumptionSaveBtn">Save</x-button>
            </div>
        </form>
    </div>
</div>

@if(session('reopen_add_consumption'))
<x-modal-reopen modal-id="consumptionModal" session-key="reopen_add_consumption" guard="addConsumption">
    document.getElementById('consumptionModal').classList.remove('hidden');
    document.getElementById('consumptionModal').classList.add('flex');
</x-modal-reopen>
@endif

@if(session('reopen_edit_consumption'))
@php $editLog = app(\App\Http\Controllers\FeedController::class)->consumptionSessionData(); @endphp
@if($editLog)
<x-modal-reopen modal-id="consumptionModal" session-key="reopen_edit_consumption" guard="editConsumption">
    openConsumptionModal(
        {{ $editLog->cage_id }},
        {{ $editLog->feed_batch_id }},
        '{{ $editLog->log_date->format('Y-m-d') }}',
        '{{ $editLog->log_time?->format('H:i') ?? '' }}',
        {{ $editLog->feed_consumed_kg }},
        {{ $editLog->id }}
    );
</x-modal-reopen>
@endif
@endif

{{-- Whole-Farm Entry Modal --}}
<div id="farmEntryModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeFarmEntryModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 id="farmEntryModalTitle" class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Log Whole-Farm Feeding</h2>
            <button type="button" onclick="closeFarmEntryModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>
        <form id="farmEntryForm" method="POST" action="{{ route('feed.farm-entry.store') }}" data-turbo="false" data-feed-ajax="tab-consumption-frame">
            @csrf
            <input type="hidden" name="_method" id="farmEntryMethod" value="POST">

            <label class="block text-sm text-[#333333] mb-1.5">Feed Batch <span class="text-[#9B1C24]">*</span></label>
            <select name="feed_batch_id" required
                    class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                <option value="">Select batch...</option>
            </select>
            <x-input-error name="feed_batch_id" />

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Date <span class="text-[#9B1C24]">*</span></label>
                    <input name="log_date" type="date" value="{{ old('log_date', now()->toDateString()) }}" required
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                    <x-input-error name="log_date" />
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Time</label>
                    <input name="log_time" type="time" value="{{ old('log_time') }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">
                </div>
            </div>

            <label class="block text-sm text-[#333333] mb-1.5">Total Fed (kg) <span class="text-[#9B1C24]">*</span></label>
            <input name="total_kg" type="number" step="0.01" min="0" required id="farmKgInput" value="{{ old('total_kg') }}"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-1 focus:outline-none focus:border-[#002D5E]">
            <x-input-error name="total_kg" />
            <p id="farmExceedsWarning" class="hidden text-xs text-[#9B1C24] mb-4"></p>

            <label class="block text-sm text-[#333333] mb-1.5">Unit Cost (per kg)</label>
            <input name="unit_cost" type="number" step="0.01" min="0" value="{{ old('unit_cost') }}"
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
                <x-button type="submit" class="flex-1 py-2.5" id="farmSaveBtn">Distribute</x-button>
            </div>
        </form>
    </div>
</div>

@if(session('reopen_add_farm_entry'))
<x-modal-reopen modal-id="farmEntryModal" session-key="reopen_add_farm_entry" guard="addFarmEntry">
    document.getElementById('farmEntryModal').classList.remove('hidden');
    document.getElementById('farmEntryModal').classList.add('flex');
</x-modal-reopen>
@endif

@if(session('reopen_edit_farm_entry'))
@php $editFarmEntry = app(\App\Http\Controllers\FeedController::class)->farmEntrySessionData(); @endphp
@if($editFarmEntry)
@php
    $fb = $editFarmEntry->feedBatch;
@endphp
<x-modal-reopen modal-id="farmEntryModal" session-key="reopen_edit_farm_entry" guard="editFarmEntry">
    openFarmEntryModal(
        {{ $editFarmEntry->id }},
        {{ $editFarmEntry->feed_batch_id }},
        '{{ $editFarmEntry->log_date->format('Y-m-d') }}',
        '{{ $editFarmEntry->log_time?->format('H:i') ?? '' }}',
        {{ $editFarmEntry->total_kg }},
        {{ $editFarmEntry->unit_cost ?? 'null' }}
    );
</x-modal-reopen>
@endif
@endif

@endsection

@push('scripts')
<script>
function feedAjaxSubmit(form) {
    var submitBtn = form.querySelector('[type="submit"]');
    var btnText = submitBtn ? submitBtn.textContent : '';
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving\u2026'; }

    var data = {};
    (new FormData(form)).forEach(function(v, k) { data[k] = v; });

    var methodEl = form.querySelector('[name="_method"]');
    var method = methodEl ? methodEl.value : 'POST';
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute('content') || '';

    form.querySelectorAll('.feed-error').forEach(function(el) { el.remove(); });
    form.querySelectorAll('input, select, textarea').forEach(function(el) { el.classList.remove('border-[#9b1c24]', 'ring-1', 'ring-[#f3cdd0]'); });

    function reEnable() {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = btnText; }
    }

    fetch(form.action, {
        method: method.toUpperCase(),
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify(data),
    })
    .then(function(r) { return r.json().then(function(d) { return { status: r.status, body: d }; }); })
    .then(function(res) {
        if (res.body.success) {
            closeAllFeedModals();
            feedReloadLiveData();
            showNotification('Saved successfully.', 'success');
            reEnable();
        } else {
            if (res.body.errors) {
                Object.keys(res.body.errors).forEach(function(key) {
                    var input = form.querySelector('[name="' + key + '"]');
                    if (input) {
                        input.classList.add('border-[#9b1c24]', 'ring-1', 'ring-[#f3cdd0]');
                        var wrapper = document.createElement('div');
                        wrapper.className = 'feed-error flex items-center gap-1 mt-1';
                        wrapper.innerHTML = '<i data-lucide="alert-circle" class="w-3 h-3 shrink-0" style="color: #9b1c24;"></i><p class="text-xs" style="color: #9b1c24;">' + res.body.errors[key][0].replace(/"/g, '&quot;') + '</p>';
                        input.parentNode.appendChild(wrapper);
                        if (window.lucide) lucide.createIcons();
                    }
                });
            }
            reEnable();
        }
    })
    .catch(function() {
        reEnable();
        showNotification('An error occurred. Please try again.', 'error');
    });
}

function closeAllFeedModals() {
    closeAddBatchModal();
    closeEditBatchModal();
    closeConsumptionModal();
    closeFarmEntryModal();
}

function feedReloadLiveData() {
    var frame = document.getElementById('feed-live-data');
    if (frame && frame.src) frame.src = frame.src;
}

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

    if (cageSelect) cageSelect.value = cageId || '';
    if (batchSelect) batchSelect.value = batchId || '';
    var dateInput = document.querySelector('#consumptionModal input[name="log_date"]');
    if (dateInput) dateInput.value = date || '{{ now()->toDateString() }}';
    var timeInput = document.querySelector('#consumptionModal input[name="log_time"]');
    if (timeInput) timeInput.value = time || '';
    var kgInput = document.querySelector('#consumptionModal input[name="feed_consumed_kg"]');
    if (kgInput) kgInput.value = kg || '';

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

    var farmBatch = document.querySelector('#farmEntryModal select[name="feed_batch_id"]');
    if (farmBatch) farmBatch.value = batchId || '';
    var farmDate = document.querySelector('#farmEntryModal input[name="log_date"]');
    if (farmDate) farmDate.value = date || '{{ now()->toDateString() }}';
    var farmTime = document.querySelector('#farmEntryModal input[name="log_time"]');
    if (farmTime) farmTime.value = time || '';
    var farmKg = document.querySelector('#farmEntryModal input[name="total_kg"]');
    if (farmKg) farmKg.value = totalKg || '';
    var farmCost = document.querySelector('#farmEntryModal input[name="unit_cost"]');
    if (farmCost) farmCost.value = unitCost || '';

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
            closeAllFeedModals();
        }
    });

    // Wire AJAX submit handlers directly on each form
    function bindFeedAjax() {
        document.querySelectorAll('[data-feed-ajax]').forEach(function(form) {
            if (form.dataset.feedAjaxBound) return;
            form.dataset.feedAjaxBound = 'true';
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                feedAjaxSubmit(form);
            });
        });
    }
    bindFeedAjax();
    document.addEventListener('turbo:frame-load', bindFeedAjax);
    document.addEventListener('turbo:load', bindFeedAjax);
})();

function deleteBatch(id) {
    fetch('{{ url('feed/batch') }}/' + id + '/delete-check')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.can_delete) {
                confirmModal('Delete this feed batch? All associated data will be permanently removed.', { submit: function() {
                    fetch('{{ url('feed/batch') }}/' + id, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute('content') || '',
                            'Accept': 'application/json',
                        },
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            feedReloadLiveData();
                            showNotification('Feed batch deleted.', 'success');
                        } else {
                            showNotification('Delete failed.', 'error');
                        }
                    })
                    .catch(function() { showNotification('Delete failed.', 'error'); });
                }}, 'Delete', 'destructive');
            } else {
                confirmModal('This batch has ' + data.count + ' recorded consumption log(s) and cannot be deleted. Remove those records first.', null, 'Got it', 'info');
            }
        })
        .catch(function() {
            showNotification('Could not check batch status. Please try again.', 'error');
        });
}

function deleteConsumption(id) {
    fetch('{{ url('feed/consumption') }}/' + id, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute('content') || '',
            'Accept': 'application/json',
        },
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            feedReloadLiveData();
            showNotification('Consumption log deleted.', 'success');
        } else {
            showNotification('Delete failed.', 'error');
        }
    })
    .catch(function() { showNotification('Delete failed.', 'error'); });
}

function deleteFarmEntry(id) {
    fetch('{{ url('feed/farm-entry') }}/' + id, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute('content') || '',
            'Accept': 'application/json',
        },
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            feedReloadLiveData();
            showNotification('Whole-farm entry deleted.', 'success');
        } else {
            showNotification('Delete failed.', 'error');
        }
    })
    .catch(function() { showNotification('Delete failed.', 'error'); });
}
</script>
@endpush
