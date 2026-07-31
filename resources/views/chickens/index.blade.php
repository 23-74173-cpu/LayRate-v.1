@extends('layouts.app')
@section('title', 'Chicken Inventory')

@section('content')
<div class="space-y-5">

    <x-page-header title="Chickens" subtitle="Manage hen inventory, movements, and mortality records" />

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
                        <option value="" {{ $sort === '' ? 'selected' : '' }}>Chicken ID (A-Z)</option>
                        <option value="chicken_id_desc" {{ $sort === 'chicken_id_desc' ? 'selected' : '' }}>Chicken ID (Z-A)</option>
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
                        <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CAGE <span class="text-red-500">*</span></label>
                        <select name="cage_id" required
                                class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 focus:border-[#002D5E]">
                            <option value="">Select cage…</option>
                            @foreach($cages as $c)
                            <option value="{{ $c->id }}" {{ $preselectedCageId === $c->id ? 'selected' : '' }}>{{ $c->cage_code }} — {{ $c->formatted_location }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">DATE <span class="text-red-500">*</span></label>
                        <input type="date" name="log_date" required value="{{ today()->toDateString() }}"
                               class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 focus:border-[#002D5E]">
                    </div>
                    <div>
                        <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">NUMBER OF DEATHS <span class="text-red-500">*</span></label>
                        <input type="number" name="count" min="1" required placeholder="e.g. 2"
                               class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 focus:border-[#002D5E]">
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
@include('chickens.partials.health-event-modal')
@include('chickens.partials.weight-check-modal')
@include('chickens.partials.cull-modal')
@include('chickens.partials.removal-modal')

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

function submitMortality(form) {
    var cageSelect = form.querySelector('select[name="cage_id"]');
    var countInput = form.querySelector('input[name="count"]');
    var cageCode = 'selected cage';
    if (cageSelect && cageSelect.selectedIndex > 0) {
        cageCode = cageSelect.options[cageSelect.selectedIndex].text.split('\u2014')[0].trim();
    }
    var count = countInput && countInput.value ? countInput.value : '(?)';

    confirmModal(
        'Record mortality: ' + count + ' hen(s) in ' + cageCode + '? The affected hen(s) will be deactivated and slot occupancy updated.',
        { submit: function() { mortalityAjaxSubmit(form); } },
        'Record', 'destructive'
    );
}

function mortalityAjaxSubmit(form) {
    var formData = new FormData(form);
    var data = {};
    formData.forEach(function(value, key) { data[key] = value; });

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

            form.reset();
            var dateInput = form.querySelector('input[type="date"]');
            if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];
        } else {
            var errors = result.json.errors || {};
            Object.keys(errors).forEach(function(field) {
                var input = form.querySelector('[name="' + field + '"]');
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
</script>
@endpush
