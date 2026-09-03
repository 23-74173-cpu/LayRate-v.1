@extends('layouts.app')
@section('title', 'Hen Inventory')

@section('content')
<style>
.slot-mini-active { outline: 2px solid #002D5E; outline-offset: 1px; box-shadow: 0 0 0 3px rgba(0,45,94,0.15); }
.slot-card { cursor: pointer; }
.slot-card:hover { border-color: #0075de; }
</style>
<div class="space-y-5">

    <x-page-header title="Hens" subtitle="Manage hen inventory, movements, and mortality records" />

    <x-fab>
        <button type="button" onclick="openRegisterModal()"
                class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Register New Hens</span>
            <div class="w-8 h-8 rounded-full bg-[#002D5E]/10 flex items-center justify-center">
                <i data-lucide="plus" class="w-4 h-4 text-[#002D5E]"></i>
            </div>
        </button>
    </x-fab>

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

        {{-- Filter Bar (single row, wraps on mobile) --}}
        <div id="inventoryFilters" class="mb-5">
            <x-card padding="p-4">
            <div class="flex flex-wrap items-end gap-x-4 gap-y-3">

                {{-- Status --}}
                <div>
                    <label class="block text-xs font-medium text-[#9CA3AF] mb-1">Status</label>
                    <div class="flex items-center">
                        @foreach(['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $val => $label)
                        <label class="status-pill px-3 py-1.5 text-xs border border-[#D9D9D9] {{ $loop->first ? 'rounded-l' : ($loop->last ? 'rounded-r' : '') }} -ml-px {{ $loop->first ? 'ml-0' : '' }} cursor-pointer transition-colors {{ $isActive === $val ? 'bg-[#002D5E] text-white z-10 border-[#002D5E]' : 'bg-white text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                            <input type="radio" name="status" value="{{ $val }}" class="hidden" onchange="filterInventory()" {{ $isActive === $val ? 'checked' : '' }}>
                            {{ $label }}
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Tag Code (live debounced search, capped width) --}}
                <div>
                    <label class="block text-xs font-medium text-[#9CA3AF] mb-1">Tag Code</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search tag..."
                           class="border border-[#D9D9D9] rounded px-2 py-1.5 text-xs w-36 sm:w-40 focus:outline-none focus:ring-1 focus:ring-[#002D5E]"
                           id="tagSearchInput"
                           oninput="debounceFilter()">
                </div>

                {{-- Cage --}}
                <div>
                    <label class="block text-xs font-medium text-[#9CA3AF] mb-1">Cage</label>
                    <select name="cage_id" class="border border-[#D9D9D9] rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-[#002D5E]" onchange="filterInventory()">
                        <option value="">All Cages</option>
                        @foreach($cages as $c)
                        <option value="{{ $c->id }}" {{ $cageId == $c->id ? 'selected' : '' }}>{{ $c->cage_code }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Breed --}}
                <div>
                    <label class="block text-xs font-medium text-[#9CA3AF] mb-1">Breed</label>
                    <select name="breed" class="border border-[#D9D9D9] rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-[#002D5E]" onchange="filterInventory()">
                        <option value="">All Breeds</option>
                        @foreach($breeds as $b)
                        <option value="{{ $b }}" {{ $breed == $b ? 'selected' : '' }}>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Sort --}}
                <div>
                    <label class="block text-xs font-medium text-[#9CA3AF] mb-1">Sort</label>
                    <select name="sort" class="border border-[#D9D9D9] rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-[#002D5E]" onchange="filterInventory()">
                        <option value="" {{ $sort === '' ? 'selected' : '' }}>Hen ID (A-Z)</option>
                        <option value="chicken_id_desc" {{ $sort === 'chicken_id_desc' ? 'selected' : '' }}>Hen ID (Z-A)</option>
                        <option value="age_asc" {{ $sort === 'age_asc' ? 'selected' : '' }}>Age (Youngest)</option>
                        <option value="age_desc" {{ $sort === 'age_desc' ? 'selected' : '' }}>Age (Oldest)</option>
                        <option value="breed_asc" {{ $sort === 'breed_asc' ? 'selected' : '' }}>Breed (A-Z)</option>
                        <option value="breed_desc" {{ $sort === 'breed_desc' ? 'selected' : '' }}>Breed (Z-A)</option>
                        <option value="date_asc" {{ $sort === 'date_asc' ? 'selected' : '' }}>Date Acquired (Oldest)</option>
                        <option value="date_desc" {{ $sort === 'date_desc' ? 'selected' : '' }}>Date Acquired (Newest)</option>
                    </select>
                </div>

                {{-- Clear filters --}}
                <x-button variant="secondary" size="sm" onclick="clearFilters()" class="mb-px">Clear filters</x-button>

            </div>
            </x-card>
        </div>

        {{-- Bulk Action Bar --}}
        <div id="bulkActionBar" class="hidden bg-white rounded-lg border border-[#D9D9D9] px-4 py-3 flex items-center justify-between">
            <span class="text-sm text-[#6B7280]">
                <strong id="bulkCount" class="text-[#002D5E]">0</strong> hen(s) selected
            </span>
            <div class="flex items-center gap-2">
                <x-button variant="outline-primary" size="sm" onclick="bulkMove()">
                    <i data-lucide="arrow-right" class="w-3 h-3 inline"></i> Move
                </x-button>
                <x-button variant="outline-warning" size="sm" onclick="bulkCull()">
                    <i data-lucide="crosshair" class="w-3 h-3 inline"></i> Cull
                </x-button>
                <x-button variant="outline-danger" size="sm" onclick="bulkRemoval()">
                    <i data-lucide="log-out" class="w-3 h-3 inline"></i> Remove
                </x-button>
            </div>
        </div>

        {{-- Hen List (lazy loaded) --}}
        <turbo-frame id="chickens-inventory-list" src="{{ route('chickens.inventory-list', ['sort' => $sort ?: null] + request()->query()) }}" target="_top">
            @include('chickens._inventory-list-skeleton')
        </turbo-frame>
    </div>

    {{-- ============================================ --}}
    {{-- MORTALITY TAB --}}
    {{-- ============================================ --}}
    <div id="panelMortality" class="{{ $tab !== 'mortality' ? 'hidden' : '' }}">

        {{-- Today's Summary Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5" id="mortality-summary">
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
                <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Deaths Today</div>
                <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]" data-mortality-total>{{ $todayTotal }}</div>
            </div>
            @foreach($cages as $c)
            @php $count = $todayByCage->get($c->cage_code, 0); @endphp
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-4 {{ $count > 0 ? 'bg-red-50 border-red-200' : '' }}" data-mortality-cage="{{ $c->cage_code }}">
                <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">{{ $c->cage_code }}</div>
                <div class="text-2xl font-bold leading-none tracking-[-0.5px] {{ $count > 0 ? 'text-red-600' : 'text-[#333333]' }}" data-mortality-cage-count="{{ $c->cage_code }}">{{ $count }}</div>
            </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Record Form --}}
            <x-card>
                <h3 class="text-sm font-medium text-[#333333] mb-4">Record Mortality</h3>
                <form method="POST" action="{{ route('mortality.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">DATE <span class="text-red-500">*</span></label>
                        <input type="date" name="log_date" required value="{{ today()->toDateString() }}"
                               class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 focus:border-[#002D5E]">
                    </div>
                    <div>
                        <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">SELECT HENS <span class="text-red-500">*</span></label>
                        <input type="hidden" name="hen_ids" id="mortalityHenIds" value="">
                        <button type="button" onclick="setPickerContext('mortalityHenIds', 'henPickerLabel')" id="henPickerBtn"
                                class="w-full flex items-center justify-between border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white text-left focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 focus:border-[#002D5E] transition-colors hover:border-[#9CA3AF]">
                            <span id="henPickerLabel" class="text-[#9CA3AF]">Click to select hens...</span>
                            <i data-lucide="chevron-right" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div>
                        <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CAUSE OF DEATH <span class="text-red-500">*</span></label>
                        <select name="reason" required
                                class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 focus:border-[#002D5E]">
                            <option value="">Select reason…</option>
                            @foreach(['Disease', 'Heat Stress', 'Injury', 'Predator', 'Unknown', 'Other'] as $reason)
                            <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">ADDITIONAL NOTES</label>
                        <textarea name="notes" rows="2" placeholder="Optional details…"
                                  class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] resize-none focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 focus:border-[#002D5E]"></textarea>
                    </div>
                    <x-button type="button" onclick="submitMortality(this.form)" class="w-full py-2.5">
                        Save Record
                    </x-button>
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
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Record Cull form --}}
            <x-card>
                <h3 class="text-sm font-medium text-[#333333] mb-4">Record Cull</h3>
                <form method="POST" action="{{ route('chickens.cull') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CULL DATE <span class="text-red-500">*</span></label>
                        <input type="date" name="cull_date" required value="{{ today()->toDateString() }}"
                               class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 focus:border-[#002D5E]">
                    </div>
                    <div>
                        <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">SELECT HENS <span class="text-red-500">*</span></label>
                        <input type="hidden" name="hen_id" id="cullingHenIds" value="">
                        <button type="button" onclick="setPickerContext('cullingHenIds', 'cullingHenLabel')" id="cullingHenBtn"
                                class="w-full flex items-center justify-between border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white text-left focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 focus:border-[#002D5E] transition-colors hover:border-[#9CA3AF]">
                            <span id="cullingHenLabel" class="text-[#9CA3AF]">Click to select hens...</span>
                            <i data-lucide="chevron-right" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div>
                        <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">REASON <span class="text-red-500">*</span></label>
                        <select name="reason" required
                                class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 focus:border-[#002D5E]">
                            <option value="">Select reason...</option>
                            <option value="low_production">Low Production</option>
                            <option value="illness">Illness</option>
                            <option value="aggression">Aggression</option>
                            <option value="age">Age</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">ADDITIONAL NOTES</label>
                        <textarea name="notes" rows="2" placeholder="Optional details…"
                                  class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] resize-none focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 focus:border-[#002D5E]"></textarea>
                    </div>
                    <x-button type="button" onclick="submitCullRecord(this.form)" class="w-full py-2.5">
                        Save Record
                    </x-button>
                </form>
            </x-card>

            {{-- Culling Records (lazy loaded) --}}
            <turbo-frame id="chickens-culling-records" src="{{ route('chickens.culling-records') }}" loading="lazy" target="_top" class="lg:col-span-2">
                @include('chickens._culling-records-skeleton')
            </turbo-frame>
        </div>
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
@include('chickens.partials.health-event-modal')
@include('chickens.partials.weight-check-modal')
@include('chickens.partials.cull-modal')
@include('chickens.partials.removal-modal')

{{-- Hen Picker Modal --}}
<div id="henPickerModal" class="fixed inset-0 z-[60] hidden items-center justify-center" style="background:rgba(0,0,0,0.4);backdrop-filter:blur(2px);">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 flex flex-col" style="max-height: 80vh;">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-[#E6E6E6]">
            <h3 class="text-sm font-semibold text-[#333333]">Select Hens</h3>
            <button type="button" onclick="closeHenPickerModal()" class="p-1 rounded hover:bg-[#F0F0F0] transition-colors">
                <i data-lucide="x" class="w-4 h-4" style="color: #615d59;"></i>
            </button>
        </div>
        <div class="px-5 pt-4 pb-3 space-y-3 border-b border-[#E6E6E6]">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-[#6B7280] mb-1">Cage</label>
                    <select id="pickerCageSelect" onchange="onPickerCageChange()" class="w-full border border-[#D9D9D9] rounded-lg px-2.5 py-1.5 text-xs text-[#333333] bg-white focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 focus:border-[#002D5E]">
                        <option value="">All cages</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-[#6B7280] mb-1">Slot</label>
                    <select id="pickerSlotSelect" onchange="onPickerFilterChange()" class="w-full border border-[#D9D9D9] rounded-lg px-2.5 py-1.5 text-xs text-[#333333] bg-white focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 focus:border-[#002D5E]" disabled>
                        <option value="">All slots</option>
                    </select>
                </div>
            </div>
            <div class="relative">
                <i data-lucide="search" class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5" style="color: #9CA3AF;"></i>
                <input type="text" id="pickerSearch" placeholder="Search by ID..." oninput="onPickerFilterChange()"
                       class="w-full border border-[#D9D9D9] rounded-lg pl-8 pr-2.5 py-1.5 text-xs text-[#333333] bg-white focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 focus:border-[#002D5E]">
            </div>
        </div>
        <div id="henPickerList" class="flex-1 overflow-y-auto px-5 py-3" style="scrollbar-width: thin;"></div>
        <div class="flex items-center justify-between px-5 py-3 border-t border-[#E6E6E6] bg-[#FAFAFA] rounded-b-xl">
            <span id="modalHenCount" class="text-xs font-semibold text-[#002D5E]">0 selected</span>
            <div class="flex gap-2">
                <button type="button" onclick="closeHenPickerModal()"
                        class="px-4 py-1.5 text-xs font-medium rounded-lg border border-[#D9D9D9] text-[#333333] hover:bg-[#F0F0F0] transition-colors">
                    Cancel
                </button>
                <button type="button" onclick="confirmHenSelection()"
                        class="px-4 py-1.5 text-xs font-medium rounded-lg bg-[#002D5E] text-white hover:bg-[#1D4E8F] transition-colors">
                    Confirm Selection
                </button>
            </div>
        </div>
    </div>
</div>

@if(session('reopen_register'))
<x-modal-reopen modal-id="registerModal" session-key="reopen_register" guard="registerHens">
    openRegisterModal();
</x-modal-reopen>
@endif
@endsection

@push('scripts')
<script>
function updateStatusPills() {
    const labels = document.querySelectorAll('.status-pill');
    labels.forEach(l => {
        l.classList.remove('bg-[#002D5E]', 'text-white');
        l.classList.add('bg-white', 'text-[#6B7280]', 'hover:bg-[#F5F6F8]');
    });
    const checked = document.querySelector('input[name="status"]:checked');
    if (checked) {
        const label = checked.closest('label');
        if (label) {
            label.classList.remove('bg-white', 'text-[#6B7280]', 'hover:bg-[#F5F6F8]');
            label.classList.add('bg-[#002D5E]', 'text-white');
        }
    }
}

function filterInventory() {
    const status = document.querySelector('input[name="status"]:checked')?.value || 'all';
    const cageId = document.querySelector('select[name="cage_id"]')?.value || '';
    const breed = document.querySelector('select[name="breed"]')?.value || '';
    const search = document.getElementById('tagSearchInput')?.value || '';
    const sort = document.querySelector('select[name="sort"]')?.value || '';

    updateStatusPills();

    const params = new URLSearchParams();
    if (status !== 'all') params.set('status', status);
    if (cageId) params.set('cage_id', cageId);
    if (breed) params.set('breed', breed);
    if (search) params.set('search', search);
    if (sort) params.set('sort', sort);

    const frame = document.getElementById('chickens-inventory-list');
    frame.src = '{{ route("chickens.inventory-list") }}?' + params.toString();

    const url = new URL(window.location);
    url.search = params.toString();
    url.searchParams.set('tab', 'inventory');
    window.history.replaceState({}, '', url);
}

function clearFilters() {
    document.querySelectorAll('input[name="status"]').forEach(r => r.checked = r.value === 'all');
    var cageSelect = document.querySelector('select[name="cage_id"]');
    if (cageSelect) cageSelect.value = '';
    var breedSelect = document.querySelector('select[name="breed"]');
    if (breedSelect) breedSelect.value = '';
    var sortSelect = document.querySelector('select[name="sort"]');
    if (sortSelect) sortSelect.value = '';
    document.getElementById('tagSearchInput').value = '';

    updateStatusPills();

    const frame = document.getElementById('chickens-inventory-list');
    frame.src = '{{ route("chickens.inventory-list") }}';

    window.history.replaceState({}, '', '{{ route("chickens.index") }}');
}

var _debounceTimer = null;
function debounceFilter() {
    if (_debounceTimer) clearTimeout(_debounceTimer);
    _debounceTimer = setTimeout(filterInventory, 300);
}

function switchTab(tab) {
    var currentTab = window.__chickensActiveTab || 'inventory';
    if (tab === currentTab) return;
    window.__chickensActiveTab = tab;

    document.getElementById('panelInventory').classList.toggle('hidden', tab !== 'inventory');
    document.getElementById('panelMortality').classList.toggle('hidden', tab !== 'mortality');
    document.getElementById('panelCulling').classList.toggle('hidden', tab !== 'culling');
    document.getElementById('panelRemoval').classList.toggle('hidden', tab !== 'removal');

    // Keep the URL's ?tab= in sync so redirect()->back() after a form submit
    // (record mortality, register a chicken, etc.) returns to the tab the
    // user was actually on, instead of always bouncing to Inventory.
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);

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

function switchChickenCage(cageId) {
    document.querySelectorAll('.cage-overview-card').forEach(function(card) {
        var isSelected = card.dataset.cageId == cageId;
        if (isSelected) {
            card.style.borderColor = '#0075de';
            card.style.borderWidth = '2px';
            card.style.backgroundColor = '#f0f7ff';
        } else {
            card.style.borderColor = '#e6e6e6';
            card.style.borderWidth = '1px';
            card.style.backgroundColor = '#ffffff';
        }
    });

    document.querySelectorAll('.cage-grid').forEach(g => g.classList.add('hidden'));
    document.querySelectorAll('.slot-hens-panel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.slot-mini').forEach(t => t.classList.remove('slot-mini-active'));
    document.querySelectorAll('.slot-panel-placeholder').forEach(p => p.classList.remove('hidden'));

    if (cageId) {
        var target = document.querySelector('.cage-grid[data-cage-id="' + cageId + '"]');
        if (target) target.classList.remove('hidden');
    }

    var card = document.querySelector('.cage-overview-card[data-cage-id="' + cageId + '"]');
    var title = document.getElementById('cageSlotsModalTitle');
    if (title && card) title.textContent = card.dataset.cageCode || '';
    var subtitle = document.getElementById('cageSlotsModalSubtitle');
    if (subtitle && card) {
        var loc = card.dataset.cageLocation || '';
        var slots = card.dataset.cageSlots || '';
        subtitle.textContent = [loc, slots ? slots + ' slots' : ''].filter(Boolean).join(' \u00b7 ') || 'Select a slot to view its hens';
    }
    var iconEl = document.getElementById('cageSlotsModalIcon');
    if (iconEl && card) {
        iconEl.style.backgroundColor = card.dataset.cageSoft || '#e8f3fe';
        iconEl.style.color = card.dataset.cageColor || '#0075de';
    }
    var modal = document.getElementById('cageSlotsModal');
    if (modal) modal.style.display = 'flex';
    lucide.createIcons();
}

function closeChickenCageModal() {
    document.getElementById('cageSlotsModal').style.display = 'none';
}

function showSlotHens(cageId, slotId) {
    const card = document.querySelector(`[data-cage-card="${cageId}"]`);
    if (!card) return;
    const panels = card.querySelectorAll('.slot-hens-panel');
    const target = card.querySelector(`[data-slot-hens="${slotId}"]`);
    if (!target) return;
    const isOpen = !target.classList.contains('hidden');

    panels.forEach(p => p.classList.add('hidden'));
    card.querySelectorAll('.slot-mini').forEach(t => t.classList.remove('slot-mini-active'));

    if (!isOpen) {
        target.classList.remove('hidden');
        card.querySelector(`[data-slot-tile="${slotId}"]`)?.classList.add('slot-mini-active');
    }
    card.querySelectorAll('.slot-panel-placeholder').forEach(p => p.classList.add('hidden'));
    if (isOpen) {
        const anyPanelOpen = Array.from(panels).some(p => !p.classList.contains('hidden'));
        if (!anyPanelOpen) card.querySelectorAll('.slot-panel-placeholder').forEach(p => p.classList.remove('hidden'));
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

function bulkRemoval() {
    const ids = getCheckedHenIds();
    const count = document.querySelectorAll('.hen-checkbox:checked').length;
    openRemovalModal(ids, count + ' selected hen' + (count > 1 ? 's' : ''));
}

function bulkCull() {
    const ids = getCheckedHenIds();
    const count = document.querySelectorAll('.hen-checkbox:checked').length;
    if (count === 0) return;
    openCullModal(ids, count + ' selected hen' + (count > 1 ? 's' : ''));
}

function toggleAllInSlot(checkbox) {
    const container = checkbox.closest('.slot-hens');
    if (!container) return;
    container.querySelectorAll('.hen-checkbox').forEach(cb => cb.checked = checkbox.checked);
    updateBulkBar();
}

function toggleColumns(btn) {
    const card = btn.closest('.rounded-lg');
    const hidden = card.querySelectorAll('.col-toggle');
    const isToggled = btn.querySelector('i').dataset.toggled === '1';
    hidden.forEach(el => el.classList.toggle('hidden', !isToggled));
    btn.querySelector('i').dataset.toggled = isToggled ? '' : '1';
}

function toggleCageHens(cageCode, checked) {
    document.querySelectorAll('#henPickerList .hen-cage-check').forEach(function(cb) {
        if (cb.closest('.hen-row')) cb.checked = checked;
    });
    updateModalHenCount();
}

function updateCageAllCheck() {}

function updateModalHenCount() {
    var n = document.querySelectorAll('#henPickerList .hen-cage-check:checked').length;
    var el = document.getElementById('modalHenCount');
    if (el) el.textContent = n + ' selected';
}

var henPickerData = @json($henPickerData ?? []);

var pickerContext = { hiddenId: 'mortalityHenIds', labelId: 'henPickerLabel' };
function setPickerContext(hiddenId, labelId) {
    pickerContext.hiddenId = hiddenId || 'mortalityHenIds';
    pickerContext.labelId = labelId || 'henPickerLabel';
    openHenPickerModal();
}

function openHenPickerModal() {
    var modal = document.getElementById('henPickerModal');
    var hidden = document.getElementById(pickerContext.hiddenId);
    var savedIds = hidden.value ? hidden.value.split(',').map(Number) : [];

    var cageSelect = document.getElementById('pickerCageSelect');
    cageSelect.innerHTML = '<option value="">All cages</option>';
    Object.keys(henPickerData).sort().forEach(function(cage) {
        var total = 0;
        Object.values(henPickerData[cage]).forEach(function(hens) { total += hens.length; });
        var opt = document.createElement('option');
        opt.value = cage;
        opt.textContent = cage + ' (' + total + ')';
        cageSelect.appendChild(opt);
    });

    var savedCage = cageSelect.value;
    onPickerCageChange(savedCage);

    document.querySelectorAll('#henPickerList .hen-cage-check').forEach(function(cb) {
        cb.checked = savedIds.indexOf(parseInt(cb.dataset.henId)) !== -1;
    });

    updateModalHenCount();
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (window.lucide) lucide.createIcons();
}

function closeHenPickerModal() {
    var modal = document.getElementById('henPickerModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function onPickerCageChange() {
    var cageCode = document.getElementById('pickerCageSelect').value;
    var slotSelect = document.getElementById('pickerSlotSelect');
    slotSelect.innerHTML = '<option value="">All slots</option>';
    slotSelect.disabled = !cageCode;

    if (cageCode && cageCode === 'Unplaced') {
        slotSelect.disabled = true;
    } else if (cageCode && henPickerData[cageCode]) {
        Object.keys(henPickerData[cageCode]).sort().forEach(function(slot) {
            var opt = document.createElement('option');
            opt.value = slot;
            opt.textContent = 'Slot ' + slot + ' (' + henPickerData[cageCode][slot].length + ')';
            slotSelect.appendChild(opt);
        });
    }

    document.getElementById('pickerSearch').value = '';
    onPickerFilterChange();
}

function onPickerFilterChange() {
    var cageCode = document.getElementById('pickerCageSelect').value;
    var slotCode = document.getElementById('pickerSlotSelect').value;
    var search = document.getElementById('pickerSearch').value.toLowerCase().trim();
    var list = document.getElementById('henPickerList');

    var hens = [];
    if (cageCode) {
        var slots = slotCode ? { [slotCode]: henPickerData[cageCode][slotCode] || [] } : (henPickerData[cageCode] || {});
        Object.entries(slots).forEach(function(entry) {
            var slot = entry[0], slotHens = entry[1];
            slotHens.forEach(function(h) { h._slot = slot; h._cage = cageCode; hens.push(h); });
        });
    } else {
        Object.entries(henPickerData).forEach(function(cageEntry) {
            var c = cageEntry[0], slots = cageEntry[1];
            Object.entries(slots).forEach(function(slotEntry) {
                var slot = slotEntry[0], slotHens = slotEntry[1];
                slotHens.forEach(function(h) { h._slot = slot; h._cage = c; hens.push(h); });
            });
        });
    }

    if (search) {
        hens = hens.filter(function(h) {
            return (h.chicken_id && h.chicken_id.toLowerCase().indexOf(search) !== -1) ||
                   (h.tag_code && h.tag_code.toLowerCase().indexOf(search) !== -1) ||
                   (h.breed && h.breed.toLowerCase().indexOf(search) !== -1);
        });
    }

    var savedIds = (document.getElementById(pickerContext.hiddenId).value || '').split(',').map(Number).filter(Boolean);

    if (hens.length === 0) {
        list.innerHTML = '<div class="py-8 text-center text-sm text-[#9CA3AF]">No hens found.</div>';
        return;
    }

    var grouped = {};
    hens.forEach(function(h) {
        var key = h._cage;
        if (!grouped[key]) grouped[key] = [];
        grouped[key].push(h);
    });

    var html = '';
    Object.entries(grouped).forEach(function(entry) {
        var cage = entry[0], cageHens = entry[1];
        html += '<div class="mb-3 last:mb-0">';
        html += '<div class="flex items-center gap-2 px-3 py-1.5 bg-[#F5F6F8] rounded-lg mb-1">';
        html += '<input type="checkbox" class="cage-all-check rounded border-[#D9D9D9] text-[#002D5E] focus:ring-[#002D5E]/30" onchange="toggleCageHens(\'' + cage + '\', this.checked)">';
        html += '<span class="text-xs font-semibold text-[#333333]">' + cage + ' <span class="text-[#9CA3AF] font-normal">(' + cageHens.length + ')</span></span>';
        html += '</div>';
        html += '<div class="divide-y divide-[#F0F0F0] border border-[#E6E6E6] rounded-lg overflow-hidden">';
        cageHens.forEach(function(h) {
            var checked = savedIds.indexOf(h.id) !== -1 ? 'checked' : '';
            html += '<label class="flex items-center gap-2.5 px-3 py-1.5 hover:bg-[#FAFAFA] cursor-pointer transition-colors hen-row">';
            html += '<input type="checkbox" class="hen-cage-check rounded border-[#D9D9D9] text-[#002D5E] focus:ring-[#002D5E]/30" data-hen-id="' + h.id + '" ' + checked + ' onchange="updateModalHenCount()">';
            html += '<span class="text-xs text-[#333333]">' + h.chicken_id + '</span>';
            if (h.tag_code) html += ' <span class="text-[10px] text-[#9CA3AF]">(' + h.tag_code + ')</span>';
            if (h._slot && h._slot !== '—') html += '<span class="text-[10px] text-[#9CA3AF]">Slot ' + h._slot + '</span>';
            html += '<span class="text-[10px] text-[#9CA3AF] ml-auto">' + h.breed + '</span>';
            html += '</label>';
        });
        html += '</div></div>';
    });

    list.innerHTML = html;
    updateModalHenCount();
}

function confirmHenSelection() {
    var checked = document.querySelectorAll('#henPickerList .hen-cage-check:checked');
    var henIds = Array.from(checked).map(function(cb) { return cb.dataset.henId; });

    var hidden = document.getElementById(pickerContext.hiddenId);
    hidden.value = henIds.join(',');

    var label = document.getElementById(pickerContext.labelId);
    if (henIds.length === 0) {
        label.textContent = 'Click to select hens...';
        label.classList.add('text-[#9CA3AF]');
        label.classList.remove('text-[#333333]');
    } else {
        label.textContent = henIds.length + ' hen(s) selected';
        label.classList.remove('text-[#9CA3AF]');
        label.classList.add('text-[#333333]');
    }

    closeHenPickerModal();
}

function submitMortality(form) {
    var hidden = document.getElementById('mortalityHenIds');
    var henIds = hidden.value ? hidden.value.split(',').filter(Boolean) : [];

    if (henIds.length === 0) {
        confirmModal('Please select at least one hen.', {}, 'OK');
        return;
    }

    confirmModal(
        'Record mortality: ' + henIds.length + ' hen(s)? The selected hen(s) will be deactivated.',
        { submit: function() { mortalityAjaxSubmit(form); } },
        'Record', 'destructive'
    );
}

function mortalityAjaxSubmit(form) {
    var hidden = document.getElementById('mortalityHenIds');
    var henIds = hidden.value ? hidden.value.split(',').map(Number).filter(Boolean) : [];

    var data = {
        log_date: form.querySelector('input[name="log_date"]').value,
        hen_ids: henIds,
        reason: form.querySelector('select[name="reason"]').value,
        notes: form.querySelector('textarea[name="notes"]').value || ''
    };

    form.querySelectorAll('.mortality-error').forEach(function(el) { el.remove(); });
    form.querySelectorAll('.has-mortality-error').forEach(function(el) {
        el.classList.remove('has-mortality-error');
    });

    fetch(form.action, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    }).then(function(r) {
        return r.json().then(function(j) { return { ok: r.ok, json: j }; });
    }).then(function(result) {
        if (result.ok) {
            var frame = document.getElementById('chickens-mortality-records');
            if (frame) frame.src = frame.src;

            var totalEl = document.querySelector('[data-mortality-total]');
            if (totalEl) totalEl.textContent = parseInt(totalEl.textContent) + result.json.count;

            var cageCard = document.querySelector('[data-mortality-cage="' + result.json.cage_code + '"]');
            if (cageCard) {
                var countEl = cageCard.querySelector('[data-mortality-cage-count]');
                if (countEl) {
                    var newVal = parseInt(countEl.textContent) + result.json.count;
                    countEl.textContent = newVal;
                    if (newVal > 0) {
                        cageCard.classList.add('bg-red-50', 'border-red-200');
                        countEl.classList.remove('text-[#333333]');
                        countEl.classList.add('text-red-600');
                    }
                }
            }

            hidden.value = '';
            var label = document.getElementById('henPickerLabel');
            label.textContent = 'Click to select hens...';
            label.classList.add('text-[#9CA3AF]');
            label.classList.remove('text-[#333333]');
            var dateInput = form.querySelector('input[type="date"]');
            if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];
        } else {
            var errors = result.json.errors || {};
            Object.keys(errors).forEach(function(field) {
                var input = form.querySelector('[name="' + field + '"]') || document.getElementById('henPickerBtn');
                if (!input) return;
                var wrapper = input.closest('div');
                if (!wrapper) return;
                wrapper.classList.add('has-mortality-error');
                input.style.borderColor = '#9b1c24';
                input.classList.add('ring-1');
                input.style.setProperty('--tw-ring-color', '#f3cdd0');

                var msg = errors[field][0];
                var errorEl = document.createElement('p');
                errorEl.className = 'mortality-error flex items-center gap-1.5 text-sm mt-1';
                errorEl.style.color = '#9b1c24';
                errorEl.innerHTML = '<i data-lucide="alert-circle" class="w-4 h-4" style="color: #9b1c24; min-width: 16px;"></i> ' + msg;
                wrapper.appendChild(errorEl);
            });
            if (window.lucide) lucide.createIcons();
        }
    }).catch(function() {
        form.submit();
    });
}

function submitCullRecord(form) {
    var hidden = document.getElementById('cullingHenIds');
    var henIds = hidden.value ? hidden.value.split(',').filter(Boolean) : [];

    if (henIds.length === 0) {
        confirmModal('Please select at least one hen.', {}, 'OK');
        return;
    }

    confirmModal(
        'Cull ' + henIds.length + ' hen(s)? The selected hen(s) will be permanently deactivated.',
        { submit: function() { cullRecordAjaxSubmit(form); } },
        'Cull', 'destructive'
    );
}

function cullRecordAjaxSubmit(form) {
    var hidden = document.getElementById('cullingHenIds');
    var henIds = hidden.value ? hidden.value.split(',').map(Number).filter(Boolean) : [];

    var data = {
        cull_date: form.querySelector('input[name="cull_date"]').value,
        hen_id: henIds.join(','),
        reason: form.querySelector('select[name="reason"]').value,
        notes: form.querySelector('textarea[name="notes"]').value || ''
    };

    form.querySelectorAll('.cull-record-error').forEach(function(el) { el.remove(); });
    form.querySelectorAll('.has-cull-record-error').forEach(function(el) {
        el.classList.remove('has-cull-record-error');
    });

    fetch(form.action, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    }).then(function(r) {
        return r.json().then(function(j) { return { ok: r.ok, json: j }; });
    }).then(function(result) {
        if (result.ok) {
            if (typeof showNotification === 'function') {
                showNotification(result.json.message, 'success');
            }

            var frame = document.getElementById('chickens-culling-records');
            if (frame) {
                var src = frame.src;
                frame.src = '';
                frame.src = src;
            }

            var inventoryFrame = document.getElementById('chickens-inventory-list');
            if (inventoryFrame) {
                var invSrc = inventoryFrame.src;
                inventoryFrame.src = '';
                inventoryFrame.src = invSrc;
            }

            hidden.value = '';
            var label = document.getElementById('cullingHenLabel');
            label.textContent = 'Click to select hens...';
            label.classList.add('text-[#9CA3AF]');
            label.classList.remove('text-[#333333]');
            var dateInput = form.querySelector('input[type="date"]');
            if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];
            var reasonSelect = form.querySelector('select[name="reason"]');
            if (reasonSelect) reasonSelect.value = '';
            var notesArea = form.querySelector('textarea[name="notes"]');
            if (notesArea) notesArea.value = '';
        } else {
            var errors = result.json.errors || {};
            Object.keys(errors).forEach(function(field) {
                var input = form.querySelector('[name="' + field + '"]');
                if (!input) return;
                var wrapper = input.closest('div');
                if (!wrapper) return;
                wrapper.classList.add('has-cull-record-error');
                input.style.borderColor = '#9b1c24';
                input.classList.add('ring-1');
                input.style.setProperty('--tw-ring-color', '#f3cdd0');

                var msg = errors[field][0];
                var errorEl = document.createElement('p');
                errorEl.className = 'cull-record-error flex items-center gap-1.5 text-sm mt-1';
                errorEl.style.color = '#9b1c24';
                errorEl.innerHTML = '<i data-lucide="alert-circle" class="w-4 h-4" style="color: #9b1c24; min-width: 16px;"></i> ' + msg;
                wrapper.appendChild(errorEl);
            });
            if (window.lucide) lucide.createIcons();
        }
    }).catch(function() {
        form.submit();
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeChickenCageModal();
        closeHenPickerModal();
    }
});

// ── Guided Walkthrough #2 — Add & place chickens (User Manual Step 2) ──
// Triggered from the profile User Manual via ?walkthrough2=1. State persists in
// sessionStorage so it survives the page reload after hens are registered.
(function() {
    var STARTED = (new URLSearchParams(window.location.search)).get('walkthrough2') === '1';
    var inProgress = sessionStorage.getItem('wt2_active') === '1';
    if (!STARTED && !inProgress) return;

    // Resume from wherever the tutorial left off if it's already running; only
    // start fresh at Step 1 on the very first trigger. This matters because the
    // post-registration redirect keeps ?walkthrough2=1 in the URL, which would
    // otherwise reset the walkthrough to Step 1.
    var startAt = (inProgress && sessionStorage.getItem('wt2_step'))
        ? parseInt(sessionStorage.getItem('wt2_step'), 10)
        : 0;
    sessionStorage.setItem('wt2_active', '1');
    sessionStorage.setItem('wt2_step', String(startAt));

    // ── Overlay DOM ──
    if (!document.getElementById('wt2Overlay')) {
        var ov = document.createElement('div');
        ov.id = 'wt2Overlay';
        ov.style.cssText = 'position:fixed;inset:0;z-index:90;display:none;pointer-events:none;';
        ov.innerHTML =
            '<div id="wt2Spotlight" style="position:fixed;border-radius:12px;border:3px solid #0075de;background:transparent;transition:all .2s ease;pointer-events:none;z-index:91;"></div>'
            + '<div id="wt2DimT" style="position:fixed;left:0;top:0;right:0;background:rgba(15,20,35,0.55);pointer-events:auto;z-index:90;display:none;"></div>'
            + '<div id="wt2DimB" style="position:fixed;left:0;bottom:0;right:0;background:rgba(15,20,35,0.55);pointer-events:auto;z-index:90;display:none;"></div>'
            + '<div id="wt2DimL" style="position:fixed;left:0;top:0;background:rgba(15,20,35,0.55);pointer-events:auto;z-index:90;display:none;"></div>'
            + '<div id="wt2DimR" style="position:fixed;right:0;top:0;background:rgba(15,20,35,0.55);pointer-events:auto;z-index:90;display:none;"></div>'
            + '<div id="wt2Tooltip" style="position:fixed;max-width:340px;background:#fff;border:1px solid #e6e6e6;border-radius:14px;padding:16px 18px;box-shadow:0 20px 50px rgba(0,0,0,0.3);z-index:92;pointer-events:none;">'
            + '<div id="wt2StepLabel" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#0075de;margin-bottom:6px;"></div>'
            + '<div id="wt2StepText" style="font-size:14px;line-height:1.5;color:#1f1f1f;"></div>'
            + '<button id="wt2Next" style="display:none;margin-top:12px;padding:8px 16px;font-size:12px;font-weight:600;color:#fff;background:#002D5E;border:0;border-radius:8px;cursor:pointer;pointer-events:auto;">Next</button>'
            + '</div>'
            + '<div id="wt2Done" style="display:none;position:fixed;inset:0;z-index:95;background:rgba(15,20,35,0.72);align-items:center;justify-content:center;pointer-events:auto;">'
            + '<div style="max-width:380px;width:calc(100% - 2rem);background:#fff;border-radius:20px;padding:32px 28px;text-align:center;box-shadow:0 30px 70px rgba(0,0,0,0.45);">'
            + '<div style="width:64px;height:64px;margin:0 auto 18px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e8f5ec;color:#1f6b3a;"><i data-lucide="check" style="width:34px;height:34px;"></i></div>'
            + '<div style="font-size:20px;font-weight:700;color:#1f1f1f;">Hens registered!</div>'
            + '<div style="font-size:14px;color:#6B7280;margin-top:8px;">Your new hens are ready to be placed into cages.</div>'
            + '<button id="wt2DoneBtn" style="margin-top:24px;padding:11px 28px;font-size:14px;font-weight:600;color:#fff;background:#002D5E;border:0;border-radius:10px;cursor:pointer;">Done</button>'
            + '</div>'
            + '</div>'
            + '<div id="wt2Skip" style="position:fixed;top:16px;right:16px;z-index:93;background:#fff;border:1px solid #e6e6e6;color:#615d59;font-size:13px;padding:8px 14px;border-radius:999px;cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,0.15);pointer-events:auto;">End tutorial</div>';
        document.body.appendChild(ov);
    }

    var spotlight = document.getElementById('wt2Spotlight');
    var tooltip = document.getElementById('wt2Tooltip');
    var skipBtn = document.getElementById('wt2Skip');
    var overlay = document.getElementById('wt2Overlay');
    var stepLabel = document.getElementById('wt2StepLabel');
    var stepText = document.getElementById('wt2StepText');
    var nextBtn = document.getElementById('wt2Next');
    var doneOverlay = document.getElementById('wt2Done');
    var doneBtn = document.getElementById('wt2DoneBtn');
    var dimT = document.getElementById('wt2DimT');
    var dimB = document.getElementById('wt2DimB');
    var dimL = document.getElementById('wt2DimL');
    var dimR = document.getElementById('wt2DimR');
    var dims = [dimT, dimB, dimL, dimR];

    // ── Steps ── manual:true = informational (Next button); else auto-advance.
    var steps = [
        {
            text: '<strong>Open the FAB menu.</strong><br>Click the <em>+</em> floating button (bottom-right) to show the hen actions.',
            target: function() { return document.querySelector('.fab-toggle'); },
            done: function() {
                var menu = document.querySelector('.fab .fab-menu');
                return !!(menu && !menu.classList.contains('invisible'));
            }
        },
        {
            text: '<strong>Register new hens.</strong><br>Click the <em>Register New Hens</em> button in the menu.',
            target: function() {
                var m = document.querySelector('.fab .fab-menu button[onclick="openRegisterModal()"]');
                return m || document.querySelector('button[onclick="openRegisterModal()"]');
            },
            done: function() {
                return !!(document.getElementById('registerModal') && document.getElementById('registerModal').style.display === 'flex');
            }
        },
        {
            text: '<strong>Register the hens.</strong><br>Set the <em>Quantity</em>, <em>Breed</em>, and details, then click <em>Register Hens</em> to create them.',
            target: function() {
                var m = document.getElementById('registerModal');
                if (!m) return null;
                return m.querySelector('.relative') || m;
            },
            done: function() {
                // Advances the moment the form is submitted (page redirects back).
                return window.__wt2Registered === true;
            }
        },
        {
            text: '<strong>View the new hens.</strong><br>Click the <em>Unplaced</em> toggle to expand the table of unplaced hens.',
            target: function() {
                return document.getElementById('unplacedCard')
                    || document.querySelector('button[onclick="toggleUnplaced()"]')
                    || document.getElementById('unplacedList');
            },
            done: function() {
                var list = document.getElementById('unplacedList');
                return !!(list && !list.classList.contains('hidden'));
            }
        },
        {
            text: '<strong>Your new hens.</strong><br>This table lists the hens you just registered. Review the breeds and hen IDs shown.',
            target: function() {
                var list = document.getElementById('unplacedList');
                if (list && !list.classList.contains('hidden')) return list;
                return null;
            },
            done: function() { return false; },
            manual: true
        },
        {
            text: '<strong>Review the list.</strong><br>Use <em>Next</em> and <em>Prev</em> to page through the unplaced hens and confirm your registered flock.',
            target: function() {
                return document.querySelector('#unplacedPagination') || document.getElementById('unplacedList');
            },
            done: function() { return false; },
            manual: true
        },
        {
            text: '<strong>Place into cage.</strong><br>Click <em>Place into cage</em> to open the placement page and assign these hens to a cage.',
            target: function() {
                return document.querySelector('a[data-wt-place]') || document.querySelector('a[href*="bulk-add"]');
            },
            done: function() {
                // Clicking the link navigates to the bulk-add page; the poller
                // never sees it complete, so treat it as done when the phase flag
                // is set right before navigation.
                return window.__wt2Placing === true;
            }
        }
    ];

    var idx = Math.min(startAt, steps.length - 1);
    var pollTimer = null;

    function positionOverlay() {
        var s = steps[idx];
        if (!s) return;
        var el = s.target();
        if (!el) {
            tooltip.style.display = 'none'; spotlight.style.display = 'none';
            dims.forEach(function(d) { d.style.display = 'none'; });
            return;
        }
        var r = el.getBoundingClientRect();
        var pad = 6;
        var left = r.left - pad, top = r.top - pad;
        var right = r.right + pad, bottom = r.bottom + pad;

        spotlight.style.display = 'block';
        spotlight.style.left = left + 'px'; spotlight.style.top = top + 'px';
        spotlight.style.width = (r.width + pad * 2) + 'px';
        spotlight.style.height = (r.height + pad * 2) + 'px';

        if (s.manual) {
            dimT.style.display = 'block';
            dimT.style.left = '0px'; dimT.style.right = '0px'; dimT.style.top = '0px';
            dimT.style.height = window.innerHeight + 'px';
            dimB.style.display = 'none'; dimL.style.display = 'none'; dimR.style.display = 'none';
        } else {
            dimT.style.display = 'block'; dimT.style.left = '0px'; dimT.style.right = '0px';
            dimT.style.top = '0px'; dimT.style.height = Math.max(0, top) + 'px';
            dimB.style.display = 'block'; dimB.style.left = '0px'; dimB.style.right = '0px';
            dimB.style.bottom = '0px'; dimB.style.height = Math.max(0, window.innerHeight - bottom) + 'px';
            dimL.style.display = 'block'; dimL.style.left = '0px'; dimL.style.top = top + 'px';
            dimL.style.width = Math.max(0, left) + 'px'; dimL.style.height = Math.max(0, bottom - top) + 'px';
            dimR.style.display = 'block'; dimR.style.right = '0px'; dimR.style.top = top + 'px';
            dimR.style.width = Math.max(0, window.innerWidth - right) + 'px';
            dimR.style.height = Math.max(0, bottom - top) + 'px';
        }

        tooltip.style.display = 'block';
        stepLabel.textContent = 'Step ' + (idx + 1) + ' of ' + steps.length;
        stepText.innerHTML = s.text;
        nextBtn.style.display = s.manual ? 'inline-block' : 'none';
        var ttH = tooltip.offsetHeight;
        var ttTop = bottom + 14;
        if (ttTop + ttH > window.innerHeight - 20) {
            ttTop = Math.max(12, top - ttH - 14);
        }
        tooltip.style.left = Math.max(12, Math.min(left, window.innerWidth - tooltip.offsetWidth - 12)) + 'px';
        tooltip.style.top = ttTop + 'px';
    }

    function show() {
        overlay.style.display = 'block';
        skipBtn.style.display = 'block';
        positionOverlay();
        initPolling();
    }

    function advance() {
        idx++;
        if (idx >= steps.length) {
            outro();
        } else {
            sessionStorage.setItem('wt2_step', String(idx));
            positionOverlay();
        }
    }

    function outro() {
        stopPolling();
        overlay.querySelectorAll('#wt2Spotlight, #wt2Tooltip, #wt2Skip').forEach(function(n) { n.style.display = 'none'; });
        try { window.lucide.createIcons(); } catch (e) {}
        doneOverlay.style.display = 'flex';
    }

    function finish() {
        stopPolling();
        overlay.style.display = 'none';
        skipBtn.style.display = 'none';
        doneOverlay.style.display = 'none';
        sessionStorage.removeItem('wt2_active');
        sessionStorage.removeItem('wt2_step');
        try {
            history.replaceState({}, '', window.location.pathname);
            if (window.showToast) showToast('Walkthrough complete', true);
        } catch (e) {}
    }

    function initPolling() {
        stopPolling();
        pollTimer = setInterval(function() {
            var s = steps[idx];
            if (!s) return;
            var el = s.target();
            if (!el) { positionOverlay(); return; }
            positionOverlay();
            if (!s.manual && s.done()) advance();
        }, 600);
    }

    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    skipBtn.addEventListener('click', function() { finish(); });
    nextBtn.addEventListener('click', function() { advance(); });
    doneBtn.addEventListener('click', function() { finish(); });
    window.addEventListener('resize', positionOverlay);
    window.addEventListener('scroll', positionOverlay, true);

    // The dim/blocking bars intercept wheel events, so forward them to the page
    // scroll container to let the user scroll while the walkthrough is open.
    document.addEventListener('wheel', function(e) {
        if (sessionStorage.getItem('wt2_active') !== '1') return;
        var sc = document.querySelector('.page-wrapper') || document.scrollingElement || document.documentElement;
        if (!sc) return;
        sc.scrollTop = Math.max(0, Math.min(sc.scrollHeight, sc.scrollTop + e.deltaY));
        e.preventDefault();
    }, { passive: false });

    // When "Place into cage" is clicked, mark the phase so the bulk-add page's
    // own walkthrough resumes at its Step 1. Delegated on document (not bound to
    // the element at parse time) because the link lives inside the lazy-loaded
    // Turbo frame and may not exist when this script first runs. Guarded to the
    // tutorial so normal bulk-add navigation is unaffected.
    document.addEventListener('click', function(e) {
        if (sessionStorage.getItem('wt2_active') !== '1') return;
        var a = e.target.closest('a[href*="bulk-add"]');
        if (!a) return;
        window.__wt2Placing = true;
        sessionStorage.setItem('wt2_step', '0');
        sessionStorage.setItem('wt2_phase', 'bulkadd');
    });

    // When the register form is submitted, mark it so the poller advances
    // before the page reloads (redirect after registration), and persist the
    // next step (view unplaced list) so the walkthrough resumes there.
    var form = document.getElementById('registerModal') ?
        document.getElementById('registerModal').querySelector('form') : null;
    if (form && !form.__wt2Bound) {
        form.__wt2Bound = true;
        form.addEventListener('submit', function() {
            window.__wt2Registered = true;
            sessionStorage.setItem('wt2_step', '3'); // index of "View the new hens"
        });
    }

    var start = function() { show(); };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        setTimeout(start, 400);
    }
})();
</script>
@endpush
