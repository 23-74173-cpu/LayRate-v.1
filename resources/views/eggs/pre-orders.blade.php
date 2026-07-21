@extends('layouts.app')
@section('title', 'Pre-Orders')

@section('content')
<div class="space-y-5">

    <x-page-header title="Pre-Orders" subtitle="Customer orders and fulfillment tracking" />

    @include('eggs._tabs', ['activeTab' => 'preorders'])

    {{-- ── Supply Summary ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($summary as $size => $data)
        @php
            $label = ucfirst($size);
            $colors = [
                'small'  => ['#2D7D46', '#d6f0e3'],
                'medium' => ['#1D4E8F', '#dcebfa'],
                'large'  => ['#C2703E', '#fae3d0'],
                'jumbo'  => ['#6B4C8A', '#e9e0f5'],
            ];
            [$color, $soft] = $colors[$size];
            $isDeficit = $data['available'] < 0;
        @endphp
        <div class="rounded-lg border p-4 {{ $isDeficit ? 'border-red-300 bg-red-50' : 'bg-white border-[#D9D9D9]' }}">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-1" style="color: {{ $color }}">{{ $label }}</div>
            <div class="text-2xl font-bold leading-none tracking-[-0.5px]" style="color: {{ $isDeficit ? '#9b1c24' : '#333333' }}">
                {{ number_format($data['available']) }}
            </div>
            <div class="text-xs mt-1" style="color: #6B7280">available</div>
            <div class="text-xs mt-1" style="color: #6B7280">
                {{ number_format($data['current_stock']) }} stock · {{ number_format($data['forecasted']) }} forecast · {{ number_format($data['committed']) }} committed
            </div>
            @if($isDeficit)
            <div class="mt-2 text-xs font-medium" style="color: #9b1c24">
                Shortfall: {{ number_format(abs($data['available'])) }} eggs · {{ (int) ceil(abs($data['available']) / 30) }} trays
            </div>
            @endif
        </div>
        @endforeach
    </div>

    {{-- ── Filters ── --}}
    <x-card padding="p-4">
        <form method="GET" action="{{ route('eggs.preorders') }}" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">STATUS</label>
                    <select name="status"
                            class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        <option value="all" {{ $filters['status'] === 'all' ? 'selected' : '' }}>All Statuses</option>
                        <option value="pending" {{ $filters['status'] === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="fulfilled" {{ $filters['status'] === 'fulfilled' ? 'selected' : '' }}>Fulfilled</option>
                        <option value="cancelled" {{ $filters['status'] === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">EGG SIZE</label>
                    <select name="egg_size"
                            class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        <option value="all" {{ $filters['egg_size'] === 'all' ? 'selected' : '' }}>All Sizes</option>
                        <option value="small" {{ $filters['egg_size'] === 'small' ? 'selected' : '' }}>Small</option>
                        <option value="medium" {{ $filters['egg_size'] === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="large" {{ $filters['egg_size'] === 'large' ? 'selected' : '' }}>Large</option>
                        <option value="jumbo" {{ $filters['egg_size'] === 'jumbo' ? 'selected' : '' }}>Jumbo</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">FROM</label>
                    <input type="date" name="from" value="{{ $filters['from'] }}"
                           class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">TO</label>
                    <input type="date" name="to" value="{{ $filters['to'] }}"
                           class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <x-button type="submit">
                    Apply Filters
                </x-button>
                <a href="{{ route('eggs.preorders') }}"
                   class="flex items-center gap-1.5 border border-[#D9D9D9] text-[#6B7280] px-4 py-2 rounded-lg text-sm hover:bg-[#F5F6F8] transition-colors">
                    Reset
                </a>
            </form>
        </x-card>

    {{-- ── Header ── --}}
    <div class="flex items-center justify-between">
        <button onclick="document.getElementById('addOrderModal').style.display = 'flex'"
                class="flex items-center gap-2 bg-[#002D5E] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#001F42] transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Add Pre-Order
        </button>
    </div>

    {{-- ── Orders Table ── --}}
    <turbo-frame id="eggs-preorders-table" src="{{ route('eggs.preorders.table', request()->query()) }}" loading="lazy" target="_top">
        @include('eggs.pre-orders._table-skeleton')
    </turbo-frame>

</div>

{{-- Add Pre-Order Modal --}}
<div id="addOrderModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true" style="display: none;">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeAddOrderModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Add Pre-Order</h2>
            <button onclick="closeAddOrderModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('eggs.preorders.store') }}"
              data-confirm="Create this pre-order?" data-confirm-action="Create"
              onsubmit="loadingButton(this.querySelector('button[type=submit]'), 'Adding\u2026')">
            @csrf
            <div class="space-y-4">

                {{-- ── Live Stock Summary ── --}}
                <div id="preorderStockSummary" class="rounded-lg p-3 text-xs space-y-1" style="background-color: #f6f5f4; border: 1px solid #e6e6e6;">
                    <div class="font-semibold uppercase tracking-[0.05em] mb-1.5" style="color: #615d59;">Available Stock</div>
                    <div id="preorderStockSummaryContent" class="grid grid-cols-2 gap-x-3 gap-y-1" style="color: #6B7280;">
                        <span>Small: <strong id="stockSmall" style="color: #333333;">—</strong></span>
                        <span>Medium: <strong id="stockMedium" style="color: #333333;">—</strong></span>
                        <span>Large: <strong id="stockLarge" style="color: #333333;">—</strong></span>
                        <span>Jumbo: <strong id="stockJumbo" style="color: #333333;">—</strong></span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">CUSTOMER NAME</label>
                    <input type="text" name="customer_name" value="{{ old('customer_name') }}" required
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                    <x-input-error name="customer_name" />
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">REFERENCE <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span></label>
                    <input type="text" name="customer_reference" value="{{ old('customer_reference') }}"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">EGG SIZE</label>
                    <select name="egg_size" id="orderEggSize" required onchange="onOrderSizeChange()"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">Select size…</option>
                        <option value="small" {{ old('egg_size') === 'small' ? 'selected' : '' }}>Small</option>
                        <option value="medium" {{ old('egg_size') === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="large" {{ old('egg_size') === 'large' ? 'selected' : '' }}>Large</option>
                        <option value="jumbo" {{ old('egg_size') === 'jumbo' ? 'selected' : '' }}>Jumbo</option>
                    </select>
                    <x-input-error name="egg_size" />
                    <div id="orderSizeSuggestion" class="hidden mt-1.5 text-xs" style="color: #8a5a00;"></div>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">EGG COUNT</label>
                    <input type="number" name="egg_count" id="orderEggCount" min="1" value="{{ old('egg_count') }}" required
                           oninput="onOrderCountChange()"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                    <x-input-error name="egg_count" />
                    <div id="orderTrayLabel" class="mt-1 text-xs" style="color: #6B7280;"></div>
                    <div id="orderRemainingIndicator" class="hidden mt-1 text-xs"></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">REQUESTED DATE</label>
                        <input type="date" name="requested_date" value="{{ old('requested_date', now()->toDateString()) }}" required
                               class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                               style="border-color: #e6e6e6; color: #1f1f1f;">
                        <x-input-error name="requested_date" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">FULFILLMENT DATE <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span></label>
                        <input type="date" name="fulfillment_date" value="{{ old('fulfillment_date') }}"
                               class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                               style="border-color: #e6e6e6; color: #1f1f1f;">
                        <x-input-error name="fulfillment_date" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">NOTES <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span></label>
                    <textarea name="notes" rows="2"
                              class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1 resize-y"
                              style="border-color: #e6e6e6; color: #1f1f1f;">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeAddOrderModal()"
                        class="flex-1 border border-[#D9D9D9] text-[#6B7280] py-2.5 rounded-lg text-sm">Cancel</button>
                <button type="submit" id="addOrderSubmitBtn"
                        class="flex-1 bg-[#002D5E] text-white py-2.5 rounded-lg text-sm disabled:opacity-50 disabled:cursor-not-allowed">Add Pre-Order</button>
            </div>
        </form>
    </div>
</div>

@if(session('reopen_add_order'))
<x-modal-reopen modal-id="addOrderModal" session-key="reopen_add_order" guard="addOrder">
    lucide.createIcons();
    updateOrderTrays();
</x-modal-reopen>
@endif

{{-- Edit Status Modal --}}
<div id="editStatusModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true" style="display: none;">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEditStatusModal()"></div>
    <div class="relative w-full max-w-sm rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Update Status</h2>
            <button onclick="closeEditStatusModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>

        <form id="editStatusForm" method="POST" onsubmit="loadingButton(this.querySelector('button[type=submit]'))">
            @csrf @method('PATCH')
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">CURRENT STATUS</label>
                    <div id="editCurrentStatus" class="text-sm" style="color: #333333;"></div>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">NEW STATUS</label>
                    <select name="status" id="editStatusSelect" required onchange="toggleEditFulfillmentDate()"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="pending">Pending</option>
                        <option value="fulfilled">Fulfilled</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <x-input-error name="status" />
                </div>
                <div id="editFulfillmentDateWrap" class="hidden">
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">FULFILLMENT DATE</label>
                    <input type="date" name="fulfillment_date" id="editFulfillmentDate"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                    <x-input-error name="fulfillment_date" />
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeEditStatusModal()"
                        class="flex-1 border border-[#D9D9D9] text-[#6B7280] py-2.5 rounded-lg text-sm">Cancel</button>
                <button type="submit"
                        class="flex-1 bg-[#002D5E] text-white py-2.5 rounded-lg text-sm">Update</button>
            </div>
        </form>
    </div>
</div>

@if(isset($editOrder) && $editOrder)
<x-modal-reopen modal-id="editStatusModal" session-key="reopen_edit_order" guard="editOrder">
    openEditStatus(
        {{ $editOrder->id }},
        '{{ $editOrder->status }}',
        '{{ $editOrder->fulfillment_date?->toDateString() ?? '' }}'
    );
</x-modal-reopen>
@endif
@endsection

@push('scripts')
<script>
var _preorderPools = {};

function eggCountLabel(count) {
    if (count === 0) return '0 eggs';
    if (count === 1) return '1 egg';
    if (count === 12) return '1 dozen';
    if (count === 15) return 'half tray';
    if (count % 30 === 0) {
        var t = count / 30;
        return t + ' ' + (t === 1 ? 'tray' : 'trays');
    }
    if (count > 30 && count % 15 === 0) {
        return (count / 15 / 2) + ' trays';
    }
    var trays = (count / 30).toFixed(1);
    return count.toLocaleString() + ' eggs (' + trays + ' trays)';
}

function fetchPreorderPools(callback) {
    var url = '{{ route("eggs.preorders.pool-data") }}';
    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.pools) {
                _preorderPools = data.pools;
                if (callback) callback(data.pools);
            }
        })
        .catch(function() { console.warn('fetchPreorderPools failed'); });
}

function updateStockSummary(pools) {
    var sizes = ['small', 'medium', 'large', 'jumbo'];
    sizes.forEach(function(s) {
        var el = document.getElementById('stock' + s.charAt(0).toUpperCase() + s.slice(1));
        if (el) el.textContent = (pools[s] !== undefined ? pools[s].toLocaleString() : '—');
    });
}

function getAvailableForSize(size) {
    return _preorderPools[size] !== undefined ? _preorderPools[size] : 0;
}

function onOrderSizeChange() {
    var select = document.getElementById('orderEggSize');
    var selected = select ? select.value : '';
    var suggestion = document.getElementById('orderSizeSuggestion');

    if (!selected) {
        if (suggestion) suggestion.classList.add('hidden');
        onOrderCountChange();
        return;
    }

    var avail = getAvailableForSize(selected);
    if (avail < 1) {
        var bestSize = '';
        var bestAvail = 0;
        ['small', 'medium', 'large', 'jumbo'].forEach(function(s) {
            var a = getAvailableForSize(s);
            if (a > bestAvail) { bestAvail = a; bestSize = s; }
        });
        if (suggestion && bestSize) {
            suggestion.textContent = 'No ' + selected + ' eggs available. ' +
                bestAvail.toLocaleString() + ' ' + bestSize + ' eggs available instead.';
            suggestion.classList.remove('hidden');
        }
    } else {
        if (suggestion) suggestion.classList.add('hidden');
    }

    onOrderCountChange();
}

function onOrderCountChange() {
    var count = parseInt(document.getElementById('orderEggCount').value) || 0;
    var select = document.getElementById('orderEggSize');
    var selected = select ? select.value : '';
    var trayLabel = document.getElementById('orderTrayLabel');
    var remainingEl = document.getElementById('orderRemainingIndicator');
    var submitBtn = document.getElementById('addOrderSubmitBtn');

    if (count < 1 || !selected) {
        trayLabel.textContent = selected ? 'Enter a count.' : 'Select a size first.';
        if (remainingEl) remainingEl.classList.add('hidden');
        if (submitBtn) submitBtn.disabled = true;
        return;
    }

    trayLabel.textContent = eggCountLabel(count);

    var avail = getAvailableForSize(selected);
    if (avail < 1) {
        if (remainingEl) {
            remainingEl.textContent = 'No ' + selected + ' eggs available for this size.';
            remainingEl.style.color = '#9b1c24';
            remainingEl.classList.remove('hidden');
        }
        if (submitBtn) submitBtn.disabled = true;
        return;
    }

    var remaining = avail - count;
    if (remaining < 0) {
        if (remainingEl) {
            remainingEl.textContent = 'Only ' + avail.toLocaleString() + ' ' + selected + ' eggs available — cannot order ' + count.toLocaleString() + '.';
            remainingEl.style.color = '#9b1c24';
            remainingEl.classList.remove('hidden');
        }
        if (submitBtn) submitBtn.disabled = true;
    } else {
        if (remainingEl) {
            remainingEl.textContent = 'Remaining after this order: ' + remaining.toLocaleString() + ' ' + selected + ' eggs';
            remainingEl.style.color = '#1f6b3a';
            remainingEl.classList.remove('hidden');
        }
        if (submitBtn) submitBtn.disabled = false;
    }
}

function closeAddOrderModal() {
    document.getElementById('addOrderModal').style.display = 'none';
}

function openEditStatus(id, currentStatus, fulfillmentDate) {
    document.getElementById('editStatusForm').action = '/eggs/pre-orders/' + id;
    document.getElementById('editCurrentStatus').textContent = currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1);
    document.getElementById('editStatusSelect').value = currentStatus;
    document.getElementById('editFulfillmentDate').value = fulfillmentDate || '';
    toggleEditFulfillmentDate();
    document.getElementById('editStatusModal').style.display = 'flex';
}

function closeEditStatusModal() {
    document.getElementById('editStatusModal').style.display = 'none';
}

function toggleEditFulfillmentDate() {
    var wrap = document.getElementById('editFulfillmentDateWrap');
    var status = document.getElementById('editStatusSelect').value;
    wrap.classList.toggle('hidden', status !== 'fulfilled');
}

document.addEventListener('turbo:load', function() {
    fetchPreorderPools(function(pools) {
        updateStockSummary(pools);
        onOrderSizeChange();
    });
});

// Also re-fetch when the modal opens, in case stock changed
var _addOrderObserver = new MutationObserver(function() {
    var modal = document.getElementById('addOrderModal');
    if (modal && modal.style.display !== 'none') {
        fetchPreorderPools(function(pools) {
            updateStockSummary(pools);
            onOrderSizeChange();
        });
    }
});
var _addOrderModalEl = document.getElementById('addOrderModal');
if (_addOrderModalEl) {
    _addOrderObserver.observe(_addOrderModalEl, { attributes: true, attributeFilter: ['style'] });
}
</script>
@endpush
