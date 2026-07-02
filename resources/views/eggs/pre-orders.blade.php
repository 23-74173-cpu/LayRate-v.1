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
            <div class="text-xs tracking-wider mb-2" style="color: {{ $color }}">{{ $label }}</div>
            <div class="text-lg font-semibold" style="color: {{ $isDeficit ? '#9b1c24' : '#333333' }}">
                {{ number_format($data['available']) }} available
            </div>
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
                <button type="submit"
                        class="bg-[#102A4C] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#1D4E8F] transition-colors">
                    Apply Filters
                </button>
                <a href="{{ route('eggs.preorders') }}"
                   class="flex items-center gap-1.5 border border-[#D9D9D9] text-[#6B7280] px-4 py-2 rounded-lg text-sm hover:bg-[#F5F6F8] transition-colors">
                    Reset
                </a>
            </form>
        </x-card>

    {{-- ── Header ── --}}
    <div class="flex items-center justify-between">
        <button onclick="document.getElementById('addOrderModal').classList.remove('hidden')"
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
<div id="addOrderModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeAddOrderModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Add Pre-Order</h2>
            <button onclick="closeAddOrderModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('eggs.preorders.store') }}" onsubmit="loadingButton(this.querySelector('button[type=submit]'), 'Adding\u2026')">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">CUSTOMER NAME</label>
                    <input type="text" name="customer_name" required
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">REFERENCE <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span></label>
                    <input type="text" name="customer_reference"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">EGG SIZE</label>
                    <select name="egg_size" required
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">Select size…</option>
                        <option value="small">Small</option>
                        <option value="medium">Medium</option>
                        <option value="large">Large</option>
                        <option value="jumbo">Jumbo</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">EGG COUNT</label>
                    <input type="number" name="egg_count" id="orderEggCount" min="1" required
                           oninput="updateOrderTrays()"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                    <div class="mt-1 text-xs" style="color: #6B7280;">
                        <span id="orderTrayDisplay">0</span> trays
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">REQUESTED DATE</label>
                        <input type="date" name="requested_date" value="{{ now()->toDateString() }}" required
                               class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                               style="border-color: #e6e6e6; color: #1f1f1f;">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">FULFILLMENT DATE <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span></label>
                        <input type="date" name="fulfillment_date"
                               class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                               style="border-color: #e6e6e6; color: #1f1f1f;">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">NOTES <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span></label>
                    <textarea name="notes" rows="2"
                              class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1 resize-y"
                              style="border-color: #e6e6e6; color: #1f1f1f;"></textarea>
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeAddOrderModal()"
                        class="flex-1 border border-[#D9D9D9] text-[#6B7280] py-2.5 rounded-lg text-sm">Cancel</button>
                <button type="submit"
                        class="flex-1 bg-[#002D5E] text-white py-2.5 rounded-lg text-sm">Add Pre-Order</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Status Modal --}}
<div id="editStatusModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
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
                </div>
                <div id="editFulfillmentDateWrap" class="hidden">
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">FULFILLMENT DATE</label>
                    <input type="date" name="fulfillment_date" id="editFulfillmentDate"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
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
@endsection

@push('scripts')
<script>
function closeAddOrderModal() {
    document.getElementById('addOrderModal').classList.add('hidden');
}

function updateOrderTrays() {
    var count = parseInt(document.getElementById('orderEggCount').value) || 0;
    document.getElementById('orderTrayDisplay').textContent = Math.ceil(count / 30);
}

function openEditStatus(id, currentStatus, fulfillmentDate) {
    document.getElementById('editStatusForm').action = '/eggs/pre-orders/' + id;
    document.getElementById('editCurrentStatus').textContent = currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1);
    document.getElementById('editStatusSelect').value = currentStatus;
    document.getElementById('editFulfillmentDate').value = fulfillmentDate || '';
    toggleEditFulfillmentDate();
    document.getElementById('editStatusModal').classList.remove('hidden');
}

function closeEditStatusModal() {
    document.getElementById('editStatusModal').classList.add('hidden');
}

function toggleEditFulfillmentDate() {
    var wrap = document.getElementById('editFulfillmentDateWrap');
    var status = document.getElementById('editStatusSelect').value;
    wrap.classList.toggle('hidden', status !== 'fulfilled');
}

document.addEventListener('turbo:load', function() {
    updateOrderTrays();
});
</script>
@endpush
