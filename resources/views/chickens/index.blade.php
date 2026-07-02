@extends('layouts.app')
@section('title', 'Chicken Inventory')

@section('content')
<div class="space-y-5">

    <x-page-header title="Chickens" subtitle="Manage hen inventory, movements, and mortality records" />

    {{-- Tabs --}}
    <div id="chickens-tabs-nav" class="mb-5">
        <x-underline-tabs :tabs="[
            'inventory' => ['label' => 'Inventory', 'icon' => 'list', 'onclick' => 'switchTab(\'inventory\')'],
            'mortality' => ['label' => 'Mortality', 'icon' => 'skull', 'onclick' => 'switchTab(\'mortality\')'],
            'culling'   => ['label' => 'Culled',   'icon' => 'crosshair', 'onclick' => 'switchTab(\'culling\')'],
            'removal'   => ['label' => 'Removed',   'icon' => 'log-out',  'onclick' => 'switchTab(\'removal\')'],
        ]" active="{{ $tab }}" />
    </div>

    {{-- ============================================ --}}
    {{-- INVENTORY TAB --}}
    {{-- ============================================ --}}
    <div id="panelInventory" class="{{ $tab !== 'inventory' ? 'hidden' : '' }}">

        {{-- Actions --}}
        <div class="flex items-center justify-between mb-5">
            <div></div>
            <button type="button" onclick="openRegisterModal()"
                    class="px-3 py-1.5 text-xs bg-[#002D5E] text-white rounded hover:bg-[#001F42] transition-colors">
                <i data-lucide="plus" class="w-3 h-3 inline"></i> Register New Chickens
            </button>
        </div>

        {{-- Filter Bar --}}
        <div id="inventoryFilters" class="mb-5">
            <x-card padding="p-4">
            <div class="flex flex-wrap items-end gap-3">

                {{-- Status Toggle --}}
                <div class="flex items-center gap-1 border border-[#D9D9D9] rounded overflow-hidden">
                    @foreach(['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $val => $label)
                    <label class="px-3 py-1.5 text-xs cursor-pointer transition-colors {{ $isActive === $val ? 'bg-[#002D5E] text-white' : 'bg-white text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                        <input type="radio" name="status" value="{{ $val }}" class="hidden" onchange="filterInventory()" {{ $isActive === $val ? 'checked' : '' }}>
                        {{ $label }}
                    </label>
                    @endforeach
                </div>

                {{-- Cage Filter --}}
                <div>
                    <label class="block text-xs font-medium text-[#9CA3AF] mb-1">Cage</label>
                    <select name="cage_id" class="border border-[#D9D9D9] rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-[#002D5E]" onchange="filterInventory()">
                        <option value="">All Cages</option>
                        @foreach($cages as $c)
                        <option value="{{ $c->id }}" {{ $cageId == $c->id ? 'selected' : '' }}>{{ $c->cage_code }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Breed Filter --}}
                <div>
                    <label class="block text-xs font-medium text-[#9CA3AF] mb-1">Breed</label>
                    <select name="breed" class="border border-[#D9D9D9] rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-[#002D5E]" onchange="filterInventory()">
                        <option value="">All Breeds</option>
                        @foreach($breeds as $b)
                        <option value="{{ $b }}" {{ $breed == $b ? 'selected' : '' }}>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Search --}}
                <div>
                    <label class="block text-xs font-medium text-[#9CA3AF] mb-1">Tag Code</label>
                    <div class="flex gap-1">
                        <input type="text" name="search" value="{{ $search }}" placeholder="Search tag..."
                               class="border border-[#D9D9D9] rounded px-2 py-1.5 text-xs w-36 focus:outline-none focus:ring-1 focus:ring-[#002D5E]"
                               id="tagSearchInput"
                               onkeydown="if(event.key==='Enter'){ event.preventDefault(); filterInventory(); }">
                        <button type="button" onclick="filterInventory()" class="px-2 py-1.5 bg-[#002D5E] text-white rounded text-xs hover:bg-[#001F42]">
                            <i data-lucide="search" class="w-3 h-3"></i>
                        </button>
                    </div>
                </div>

                @if($cageId || $breed || $search)
                <a href="#" onclick="clearFilters(); return false;"
                   class="px-2 py-1.5 text-xs text-red-500 hover:underline">Clear filters</a>
                @endif
            </div>
            </x-card>
        </div>

        {{-- Bulk Action Bar --}}
        <div id="bulkActionBar" class="hidden bg-white rounded-lg border border-[#D9D9D9] px-4 py-3 flex items-center justify-between">
            <span class="text-sm text-[#6B7280]">
                <strong id="bulkCount" class="text-[#002D5E]">0</strong> hen(s) selected
            </span>
            <div class="flex items-center gap-2">
                <button type="button" onclick="bulkMove()"
                        class="px-3 py-1.5 text-xs border border-[#002D5E] text-[#002D5E] rounded hover:bg-[#002D5E]/5">
                    <i data-lucide="arrow-right" class="w-3 h-3 inline"></i> Move Selected
                </button>
                <button type="button" onclick="bulkRemove()"
                        class="px-3 py-1.5 text-xs border border-red-400 text-red-500 rounded hover:bg-red-50">
                    <i data-lucide="trash-2" class="w-3 h-3 inline"></i> Remove Selected
                </button>
            </div>
        </div>

        {{-- Hen List (lazy loaded) --}}
        <turbo-frame id="chickens-inventory-list" src="{{ route('chickens.inventory-list', request()->query()) }}" loading="lazy" target="_top">
            @include('chickens._inventory-list-skeleton')
        </turbo-frame>
    </div>

    {{-- ============================================ --}}
    {{-- MORTALITY TAB --}}
    {{-- ============================================ --}}
    <div id="panelMortality" class="{{ $tab !== 'mortality' ? 'hidden' : '' }}">

        {{-- Today's Summary Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-4 text-center">
                <div class="text-2xl font-bold text-[#333]">{{ $todayTotal }}</div>
                <div class="text-xs text-[#6B7280] mt-1">Deaths Today</div>
            </div>
            @foreach($cages as $c)
            @php $count = $todayByCage->get($c->cage_code, 0); @endphp
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-4 text-center {{ $count > 0 ? 'bg-red-50 border-red-200' : '' }}">
                <div class="text-2xl font-bold {{ $count > 0 ? 'text-red-600' : 'text-[#333]' }}">{{ $count }}</div>
                <div class="text-xs text-[#6B7280] mt-1">{{ $c->cage_code }}</div>
            </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            {{-- Record Form --}}
            <x-card>
                <h3 class="text-sm font-semibold text-[#333] mb-4">Record Mortality</h3>
                <form method="POST" action="{{ route('mortality.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Cage <span class="text-red-500">*</span></label>
                        <select name="cage_id" required
                                class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                            <option value="">Select cage...</option>
                            @foreach($cages as $c)
                            <option value="{{ $c->id }}">{{ $c->cage_code }} — {{ $c->formatted_location }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="log_date" required value="{{ today()->toDateString() }}"
                               class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Count <span class="text-red-500">*</span></label>
                        <input type="number" name="count" min="1" required placeholder="e.g. 2"
                               class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Reason <span class="text-red-500">*</span></label>
                        <select name="reason" required
                                class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                            <option value="">Select reason...</option>
                            @foreach(['Disease', 'Heat Stress', 'Injury', 'Predator', 'Unknown', 'Other'] as $reason)
                            <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Notes</label>
                        <textarea name="notes" rows="2" placeholder="Optional details..."
                                  class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E] resize-none"></textarea>
                    </div>
                    <button type="submit"
                            class="w-full py-2 bg-[#002D5E] text-white text-sm rounded hover:bg-[#001F42]">
                        Save Record
                    </button>
                </form>
            </x-card>

            {{-- Recent Records (lazy loaded) --}}
            <turbo-frame id="chickens-mortality-records" src="{{ route('chickens.mortality-records') }}" loading="lazy" target="_top" class="lg:col-span-2">
                @include('chickens._mortality-records-skeleton')
            </turbo-frame>
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- CULLING TAB --}}
    {{-- ============================================ --}}
    <div id="panelCulling" class="{{ $tab !== 'culling' ? 'hidden' : '' }}">
        <turbo-frame id="chickens-culling-records" src="{{ route('chickens.culling-records') }}" loading="lazy" target="_top">
            @include('chickens._culling-records-skeleton')
        </turbo-frame>
    </div>

    {{-- ============================================ --}}
    {{-- REMOVAL TAB --}}
    {{-- ============================================ --}}
    <div id="panelRemoval" class="{{ $tab !== 'removal' ? 'hidden' : '' }}">
        <turbo-frame id="chickens-removal-records" src="{{ route('chickens.removal-records') }}" loading="lazy" target="_top">
            @include('chickens._removal-records-skeleton')
        </turbo-frame>
    </div>

</div>

{{-- Modals --}}
@include('chickens.partials.register-modal')
@include('chickens.partials.move-modal')
@include('chickens.partials.remove-modal')
@include('chickens.partials.health-event-modal')
@include('chickens.partials.weight-check-modal')
@include('chickens.partials.cull-modal')
@include('chickens.partials.removal-modal')
@endsection

@push('scripts')
<script>
function filterInventory() {
    const status = document.querySelector('input[name="status"]:checked')?.value || 'all';
    const cageId = document.querySelector('select[name="cage_id"]')?.value || '';
    const breed = document.querySelector('select[name="breed"]')?.value || '';
    const search = document.getElementById('tagSearchInput')?.value || '';

    const params = new URLSearchParams();
    if (status !== 'all') params.set('status', status);
    if (cageId) params.set('cage_id', cageId);
    if (breed) params.set('breed', breed);
    if (search) params.set('search', search);

    const frame = document.getElementById('chickens-inventory-list');
    frame.src = '{{ route("chickens.inventory-list") }}?' + params.toString();

    const url = new URL(window.location);
    url.search = params.toString();
    url.searchParams.set('tab', 'inventory');
    window.history.replaceState({}, '', url);
}

function clearFilters() {
    document.querySelectorAll('input[name="status"]').forEach(r => r.checked = r.value === 'all');
    document.querySelector('select[name="cage_id"]').value = '';
    document.querySelector('select[name="breed"]').value = '';
    document.getElementById('tagSearchInput').value = '';

    const frame = document.getElementById('chickens-inventory-list');
    frame.src = '{{ route("chickens.inventory-list") }}';

    window.history.replaceState({}, '', '{{ route("chickens.index") }}');
}

function switchTab(tab) {
    document.getElementById('panelInventory').classList.toggle('hidden', tab !== 'inventory');
    document.getElementById('panelMortality').classList.toggle('hidden', tab !== 'mortality');
    document.getElementById('panelCulling').classList.toggle('hidden', tab !== 'culling');
    document.getElementById('panelRemoval').classList.toggle('hidden', tab !== 'removal');

    const nav = document.getElementById('chickens-tabs-nav');
    if (nav) {
        nav.querySelectorAll('button').forEach(btn => {
            btn.classList.remove('border-[#002D5E]', 'text-[#002D5E]');
            btn.classList.add('border-transparent', 'text-[#6B7280]');
        });
        const active = nav.querySelector('button[onclick*="'+tab+'"]');
        if (active) {
            active.classList.remove('border-transparent', 'text-[#6B7280]');
            active.classList.add('border-[#002D5E]', 'text-[#002D5E]');
        }
    }
}

function toggleCage(btn) {
    const slots = btn.closest('.bg-white').querySelector('.cage-slots');
    const chevron = btn.querySelector('.cage-chevron');
    if (slots.classList.contains('hidden')) {
        slots.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
    } else {
        slots.classList.add('hidden');
        chevron.style.transform = '';
    }
    lucide.createIcons();
}

function toggleSlot(btn) {
    const hens = btn.closest('.border-t').querySelector('.slot-hens');
    const chevron = btn.querySelector('.slot-chevron');
    if (hens.classList.contains('hidden')) {
        hens.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
    } else {
        hens.classList.add('hidden');
        chevron.style.transform = '';
    }
    lucide.createIcons();
}

function updateBulkBar() {
    const checked = document.querySelectorAll('.hen-checkbox:checked');
    const bar = document.getElementById('bulkActionBar');
    const count = document.getElementById('bulkCount');
    if (checked.length > 0) {
        bar.classList.remove('hidden');
        count.textContent = checked.length;
    } else {
        bar.classList.add('hidden');
    }
}

function getCheckedHenIds() {
    return Array.from(document.querySelectorAll('.hen-checkbox:checked')).map(el => el.value).join(',');
}

function bulkMove() {
    const ids = getCheckedHenIds();
    const count = document.querySelectorAll('.hen-checkbox:checked').length;
    openMoveModal(ids, count, null, null);
}

function bulkRemove() {
    const ids = getCheckedHenIds();
    const count = document.querySelectorAll('.hen-checkbox:checked').length;
    openRemoveModal(ids, count, null, null);
}
</script>
@endpush
