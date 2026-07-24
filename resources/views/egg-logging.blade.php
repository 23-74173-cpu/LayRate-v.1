@extends('layouts.app')
@section('title', 'Egg Logging')

@section('content')
<div class="space-y-5">

    <x-page-header title="Egg Logging" subtitle="Log daily egg production per cage slot" />

    @include('eggs._tabs', ['activeTab' => 'logging'])

    {{-- ── Stacked Layout: Cage Overview row above Log Entry ── --}}
    <div class="space-y-6">

        {{-- ── Cage Overview (full-width row) ── --}}
        <x-card>
            <x-slot:headerSlot>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-semibold" style="color: #1f1f1f;">Cage Overview</h2>
                        <p class="text-sm" style="color: #6B7280;">Overview of all cages</p>
                    </div>
                    <span class="text-sm font-medium whitespace-nowrap" style="color: #31302e;">
                        Today: <strong style="color: #1f1f1f;">{{ number_format($todayTotal) }}</strong> eggs
                    </span>
                </div>
            </x-slot:headerSlot>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach($cages as $cage)
                @php
                    $slotCount = $cage->rows * $cage->slots_per_row;
                    $loggedCount = $todayLoggedCountByCage[$cage->cage_code] ?? 0;
                    $allLogged = $loggedCount >= $slotCount;
                @endphp
                <div class="rounded-xl border p-4 flex flex-col gap-2 min-h-[7rem]"
                     style="background-color: #ffffff; border-color: #e6e6e6;">
                    <div class="flex items-center justify-between gap-2">
                        <x-cage-color :cage="$cage" />
                        <span class="text-xs px-2 py-0.5 rounded-full font-semibold whitespace-nowrap shrink-0"
                              style="background-color: {{ $cage->colorSoft }}; color: {{ $cage->color }};">
                            {{ $slotCount }} slot{{ $slotCount !== 1 ? 's' : '' }}
                        </span>
                    </div>
                    <span class="text-xs truncate" style="color: #615d59;">{{ $cage->formatted_location }}</span>
                    <div class="flex items-center gap-2 text-xs mt-auto" style="color: #a39e98;">
                        <span>Logged:</span>
                        <span class="font-semibold whitespace-nowrap" style="color: {{ $allLogged ? '#1f6b3a' : '#1f1f1f' }};">
                            {{ $loggedCount }}/{{ $slotCount }}
                        </span>
                        @if($allLogged)
                        <span class="text-xs font-medium whitespace-nowrap" style="color: #1f6b3a;">Complete</span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </x-card>

        {{-- ── Log Entry (full-width row) ── --}}
        <x-card>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                <h3 class="text-lg font-semibold" style="color: #1f1f1f;">Log Entry</h3>
            </div>

                {{-- Single cage dropdown --}}
                <div class="mb-3">
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Select Cage</label>
                    <select id="cageSelect" onchange="switchCage(this.value)"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">Select a cage…</option>
                        @foreach($cages as $cage)
                        <option value="{{ $cage->id }}" {{ $cageFilter == $cage->id ? 'selected' : '' }}>
                            {{ $cage->cage_code }} — {{ $cage->formatted_location }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Per-cage slot grids (hidden; shown via dropdown) --}}
                @php $slotsByCageId = $cageSlots->groupBy('cage_id'); @endphp

                @foreach($cages as $cage)
                @php
                    $cageSlotsForThis = $slotsByCageId->get($cage->id, collect());
                    $totalSlots = $cageSlotsForThis->count();
                    $gridCols = $totalSlots;
                    for ($i = min(8, $totalSlots); $i >= 2; $i--) {
                        if ($totalSlots % $i === 0) { $gridCols = $i; break; }
                    }
                @endphp
                <div class="cage-grid hidden" data-cage-id="{{ $cage->id }}">
                    <div class="grid gap-1.5" style="grid-template-columns: repeat({{ $gridCols }}, minmax(80px, 110px));">
                        @foreach($cageSlotsForThis as $slot)
                        @php
                            $primaryHen = $slot->primaryHen();
                            $isSensor = $slot->hasBreakbeam();
                            $isLogged = $slot->today_egg_count > 0;
                        @endphp
                        @php $activeHenCount = $slot->active_hen_count; @endphp
                        <button type="button"
                                class="slot-card flex flex-col items-center justify-center aspect-square rounded-lg border transition-all relative {{ $activeHenCount === 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}"
                                style="background-color: {{ $isLogged ? '#eaf6ee' : '#ffffff' }}; border-color: {{ $isLogged ? '#b8dfc6' : '#e6e6e6' }};"
                                data-slot-id="{{ $slot->id }}"
                                data-cage-id="{{ $cage->id }}"
                                data-cage-code="{{ $cage->cage_code }}"
                                data-slot-number="{{ $slot->slot_number }}"
                                data-row="{{ $slot->row_number }}"
                                data-col="{{ $slot->column_number }}"
                                data-hens="{{ $activeHenCount }}"
                                data-breed="{{ $primaryHen?->breed ?? '—' }}"
                                data-age="{{ $primaryHen?->current_age_weeks ?? 0 }}"
                                data-has-sensor="{{ $isSensor ? 1 : 0 }}"
                                data-today-eggs="{{ $slot->today_egg_count }}"
                                data-empty="{{ $activeHenCount === 0 ? 1 : 0 }}"
                                onclick="{{ $activeHenCount === 0 ? 'event.preventDefault(); return false;' : 'selectSlot(this)' }}"
                                aria-label="{{ $cage->cage_code }} slot {{ $slot->row_number }}-{{ $slot->column_number }}, {{ $activeHenCount }} hens{{ $activeHenCount === 0 ? ', no hens assigned' : '' }}"
                                tabindex="{{ $activeHenCount === 0 ? '-1' : '0' }}"
                                title="{{ $activeHenCount === 0 ? 'No hens assigned to this slot' : '' }}"
                                onkeydown="{{ $activeHenCount === 0 ? '' : 'if(event.key===\'Enter\'||event.key===\' \') selectSlot(this)' }}">

                                @if($isSensor)
                                <span class="absolute top-0.5 right-0.5 w-1.5 h-1.5 rounded-full" style="background-color: #0075de;"></span>
                                @endif

                                @if($isLogged)
                                <span class="absolute top-0.5 left-0.5 w-3 h-3 rounded-full flex items-center justify-center" style="background-color: #1f6b3a;">
                                    <i data-lucide="check" class="w-2 h-2 text-white"></i>
                                </span>
                                @endif

                                @if($activeHenCount === 0)
                                <span class="text-xs text-center leading-tight" style="color: #a39e98;" title="No hens assigned">No<br>hens</span>
                                @else
                                <span class="text-xs font-semibold leading-none" style="color: {{ $activeHenCount >= $cage->max_chickens_per_slot ? '#9b1c24' : '#1f1f1f' }}">
                                    {{ $activeHenCount }}
                                </span>
                                @endif
                        </button>
                        @endforeach
                    </div>
                    <div class="mt-2 text-xs text-right" style="color: #a39e98;">
                        Logged today:
                        <span class="font-semibold" style="color: #1f1f1f;">
                            {{ $todayLoggedCountByCage[$cage->cage_code] ?? 0 }}/{{ $cageSlotsForThis->count() }} slots
                        </span>
                    </div>
                </div>
                @endforeach

                {{-- Empty state --}}
                <div id="slotFormPlaceholder" class="text-center py-10 text-sm" style="color: #a39e98;">
                    <i data-lucide="mouse-pointer-2" class="w-6 h-6 mx-auto mb-2" style="color: #d1d5db;"></i>
                    Select a cage above, then click a slot to log.
                </div>

                {{-- Active form --}}
                <div id="slotForm" class="hidden">
                    <form method="POST" action="{{ route('eggs.logging.store') }}" id="eggForm" data-turbo="false">
                        @csrf

                        {{-- Selected slot info bar --}}
                        <div id="selectedSlotBar" class="mb-3 p-3 rounded-lg flex items-center flex-wrap gap-3 text-xs" style="background-color: #f6f5f4;">
                            <div>
                                <span style="color: #a39e98;">Slot:</span>
                                <span id="formSlotLabel" class="font-semibold" style="color: #1f1f1f;"></span>
                            </div>
                            <div>
                                <span style="color: #a39e98;">Breed:</span>
                                <span id="formBreed" style="color: #31302e;"></span>
                            </div>
                            <div>
                                <span style="color: #a39e98;">Hens:</span>
                                <span id="formHenCount" style="color: #31302e;"></span>
                            </div>
                        </div>

                        <input type="hidden" name="cage_slot_id" id="cageSlotId" value="">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {{-- Date --}}
                            <div>
                                <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Date</label>
                                <input type="date" name="log_date" value="{{ now()->toDateString() }}" required
                                       class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                       style="border-color: #e6e6e6; color: #1f1f1f;">
                            </div>

                            {{-- Egg Count --}}
                            <div>
                                <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Egg Count</label>
                                <input type="number" name="egg_count" id="eggCount" min="0" required
                                       oninput="computeHdep(); checkSizeSum(); validateForm()"
                                       class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                       style="border-color: #e6e6e6; color: #1f1f1f;">
                                @error('egg_count')
                                <p class="text-xs mt-1" style="color: #9b1c24;">{{ $message }}</p>
                                @enderror
                                <button type="button" id="overrideLabel" onclick="event.preventDefault(); openOverrideModal()"
                                        class="hidden mt-1.5 text-xs flex items-center gap-1" style="color: #8a5a00;">
                                    <i data-lucide="lock" class="w-3 h-3"></i> Sensor reading — click to override
                                </button>
                            </div>

                            {{-- Hen Count --}}
                            <div>
                                <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Hen Count</label>
                                <input type="number" name="hen_count" id="henCount" min="1" value="1" required readonly
                                       class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white cursor-not-allowed focus:outline-none"
                                       style="border-color: #e6e6e6; color: #615d59; background-color: #f6f5f4;">
                                <div id="hdepDisplay" class="mt-2 inline-block border rounded-lg px-3 py-1.5 text-sm font-mono cursor-default"
                                     title="Hen-Day Egg Production = (eggs ÷ hens) × 100% — measures how many eggs were laid per hen present"
                                     style="background-color: #f6f5f4; border-color: #e6e6e6; color: #1f1f1f;">
                                    <span class="relative group">
                                        HDEP: —
                                        <i data-lucide="info" class="inline w-3 h-3 ml-0.5" style="color: #a39e98;"></i>
                                    </span>
                                </div>
                            </div>

                            {{-- Notes --}}
                            <div>
                                <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Notes <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span></label>
                                <textarea name="notes" rows="2" placeholder="e.g. 2 broken eggs"
                                          class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1 resize-y"
                                          style="border-color: #e6e6e6; color: #1f1f1f;"></textarea>
                            </div>
                        </div>

                        {{-- Size Breakdown --}}
                        <div class="mt-4 border-t pt-3" style="border-color: #e6e6e6;">
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-3" style="color: #615d59;">
                                Size Breakdown
                                <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional — fill all or leave blank)</span>
                            </label>
                            <div class="grid grid-cols-4 gap-3" id="sizeBreakdown">
                                <div>
                                    <label class="block text-xs text-center mb-1" style="color: #2D7D46;">Small</label>
                                    <input type="number" name="size_small" min="0" value="0"
                                           oninput="checkSizeSum(); validateForm()"
                                           class="size-input w-full border rounded-lg px-2 py-2 text-sm text-center bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                           style="border-color: #e6e6e6; color: #1f1f1f;">
                                </div>
                                <div>
                                    <label class="block text-xs text-center mb-1" style="color: #1D4E8F;">Medium</label>
                                    <input type="number" name="size_medium" min="0" value="0"
                                           oninput="checkSizeSum(); validateForm()"
                                           class="size-input w-full border rounded-lg px-2 py-2 text-sm text-center bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                           style="border-color: #e6e6e6; color: #1f1f1f;">
                                </div>
                                <div>
                                    <label class="block text-xs text-center mb-1" style="color: #C2703E;">Large</label>
                                    <input type="number" name="size_large" min="0" value="0"
                                           oninput="checkSizeSum(); validateForm()"
                                           class="size-input w-full border rounded-lg px-2 py-2 text-sm text-center bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                           style="border-color: #e6e6e6; color: #1f1f1f;">
                                </div>
                                <div>
                                    <label class="block text-xs text-center mb-1" style="color: #6B4C8A;">Jumbo</label>
                                    <input type="number" name="size_jumbo" min="0" value="0"
                                           oninput="checkSizeSum(); validateForm()"
                                           class="size-input w-full border rounded-lg px-2 py-2 text-sm text-center bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                           style="border-color: #e6e6e6; color: #1f1f1f;">
                                </div>
                            </div>
                            <div id="sizeSumMsg" class="mt-2 text-xs" style="color: #a39e98;">Sum: 0 <span id="sizeSumStatus" style="color: #a39e98;">—</span></div>
                        </div>

                        <div class="flex items-center gap-3 mt-4">
                            <button type="button" onclick="clearSlotSelection()"
                                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                                    style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                                    onmouseover="this.style.backgroundColor='#f6f5f4'"
                                    onmouseout="this.style.backgroundColor='transparent'">
                                Cancel
                            </button>
                            <x-button type="submit" id="saveBtn" disabled class="px-6 py-2">
                                Save Record
                            </x-button>
                        </div>
                    </form>
                </div>
        </x-card>
    </div>

    @include('egg-logging._edit-modal')

    @if(isset($editLog) && $editLog)
    <x-modal-reopen modal-id="editLogModal" session-key="reopen_edit_log" guard="editLog">
        @php $sizes = $editLog->eggSizeLogs->keyBy('egg_size'); @endphp
        openEditLog(
            {{ $editLog->id }},
            '{{ $editLog->log_date->format('Y-m-d') }}',
            {{ $editLog->egg_count }},
            {{ $editLog->hen_count }},
            '{{ addslashes($editLog->notes ?? '') }}',
            {{ $editLog->cage_slot_id }},
            {{ $sizes->get('small')?->count ?? 0 }},
            {{ $sizes->get('medium')?->count ?? 0 }},
            {{ $sizes->get('large')?->count ?? 0 }},
            {{ $sizes->get('jumbo')?->count ?? 0 }}
        );
    </x-modal-reopen>
    @endif

    {{-- ── Sensor Override Modal ── --}}
    <div id="overrideModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" style="display: none;" role="dialog" aria-modal="true">
        <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeOverrideModal()"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Override Sensor Reading</h2>
                <button onclick="closeOverrideModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>
            <div id="overridePinSection">
                <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Override PIN</label>
                <input type="text" id="overridePinInput" inputmode="numeric" maxlength="6"
                       class="w-full border rounded-lg px-3 py-2.5 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                       style="border-color: #e6e6e6; color: #1f1f1f;">
            </div>
            <div id="overridePasswordSection" class="hidden">
                <p class="text-xs mb-2" style="color: #615d59;">No override PIN set — verify with your login password instead.</p>
                <input type="password" id="overridePasswordInput"
                       class="w-full border rounded-lg px-3 py-2.5 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                       style="border-color: #e6e6e6; color: #1f1f1f;">
            </div>
            <p id="overrideError" class="hidden text-xs mb-3" style="color: #9b1c24;"></p>
            <x-button type="button" onclick="submitOverride()" class="w-full py-2.5">
                Unlock Field
            </x-button>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function() {
    let currentSlotId = null;
    let currentHasSensor = false;
    let overrideVerified = false;

    function resetSlotStyle(c) {
        c.style.borderColor = '#e6e6e6';
        c.style.borderWidth = '1px';
        var wasLogged = parseInt(c.dataset.todayEggs) > 0;
        c.style.backgroundColor = wasLogged ? '#eaf6ee' : '#ffffff';
    }

    function selectSlot(card) {
        document.querySelectorAll('.slot-card').forEach(resetSlotStyle);

        const cageColors = @json(\App\Models\Cage::getColorMap());
        const softColors = @json(\App\Models\Cage::getSoftColorMap());
        const color = cageColors[card.dataset.cageCode] || '#6B7280';
        const soft = softColors[card.dataset.cageCode] || '#f0f0f0';

        card.style.borderColor = color;
        card.style.borderWidth = '2px';
        card.style.backgroundColor = soft;

        currentSlotId = parseInt(card.dataset.slotId);
        currentHasSensor = card.dataset.hasSensor === '1';

        document.getElementById('cageSlotId').value = currentSlotId;
        document.getElementById('formSlotLabel').textContent =
            card.dataset.cageCode + ' · R' + card.dataset.row + '-C' + card.dataset.col;
        document.getElementById('formBreed').textContent = card.dataset.breed || '—';
        document.getElementById('formHenCount').textContent = card.dataset.hens;
        document.getElementById('henCount').value = card.dataset.hens;

        var existingEggs = parseInt(card.dataset.todayEggs) || 0;
        document.getElementById('eggCount').value = existingEggs > 0 ? existingEggs : (currentHasSensor ? 0 : '');

        const eggInput = document.getElementById('eggCount');
        const overrideLabel = document.getElementById('overrideLabel');

        if (currentHasSensor) {
            eggInput.readOnly = true;
            overrideLabel.classList.remove('hidden');
        } else {
            eggInput.readOnly = false;
            overrideLabel.classList.add('hidden');
            overrideVerified = false;
        }

        overrideVerified = false;
        document.getElementById('slotFormPlaceholder').classList.add('hidden');
        document.getElementById('slotForm').classList.remove('hidden');
        computeHdep();
        checkSizeSum();
        validateForm();
    }

    function clearSlotSelection() {
        document.querySelectorAll('.slot-card').forEach(resetSlotStyle);
        currentSlotId = null;
        overrideVerified = false;
        document.getElementById('slotFormPlaceholder').classList.remove('hidden');
        document.getElementById('slotForm').classList.add('hidden');
        checkSizeSum();
        validateForm();
    }

    function switchCage(cageId) {
        clearSlotSelection();

        document.querySelectorAll('.cage-grid').forEach(g => {
            g.classList.add('hidden');
        });

        if (cageId) {
            var target = document.querySelector('.cage-grid[data-cage-id="' + cageId + '"]');
            if (target) target.classList.remove('hidden');
        }

        checkSizeSum();
        validateForm();
    }

    function computeHdep() {
        const eggs = parseInt(document.getElementById('eggCount').value) || 0;
        const hens = parseInt(document.getElementById('henCount').value) || 1;
        const hdep = ((eggs / hens) * 100).toFixed(1);
        const el = document.getElementById('hdepDisplay');
        el.innerHTML = '<span class="relative group">HDEP: ' + hdep + '% <i data-lucide="info" class="inline w-3 h-3 ml-0.5" style="color: #a39e98;"></i></span>';
        el.style.backgroundColor = eggs > hens ? '#fbe4e6' : '#f6f5f4';
        el.style.borderColor = eggs > hens ? '#f3cdd0' : '#e6e6e6';
        el.style.color = eggs > hens ? '#9b1c24' : '#1f1f1f';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function checkSizeSum() {
        const totalEggs = parseInt(document.getElementById('eggCount').value) || 0;
        const eggInput = document.getElementById('eggCount').value;
        const inputs = document.querySelectorAll('#sizeBreakdown .size-input');
        let sum = 0;
        inputs.forEach(function(inp) { sum += parseInt(inp.value) || 0; });
        const msg = document.getElementById('sizeSumMsg');

        if (!eggInput || parseInt(eggInput) < 0) {
            msg.innerHTML = 'Sum: ' + sum + ' <span id="sizeSumStatus" style="color: #a39e98;">—</span>';
            msg.style.color = '#a39e98';
            return;
        }

        if (sum === totalEggs) {
            msg.innerHTML = 'Sum: ' + sum + ' <span id="sizeSumStatus" style="color: #1f6b3a;">✓</span>';
            msg.style.color = '#1f6b3a';
        } else {
            msg.innerHTML = 'Sum: ' + sum + ' <span id="sizeSumStatus" style="color: #9b1c24;">(should be ' + totalEggs + ')</span>';
            msg.style.color = '#9b1c24';
        }
    }

    function validateForm() {
        const eggInput = document.getElementById('eggCount');
        const eggVal = eggInput.value;
        const saveBtn = document.getElementById('saveBtn');

        if (!eggVal || parseInt(eggVal) < 0 || isNaN(parseInt(eggVal))) {
            saveBtn.disabled = true;
            return;
        }

        const totalEggs = parseInt(eggVal);
        const inputs = document.querySelectorAll('#sizeBreakdown .size-input');
        let sum = 0;
        let anySizeFilled = false;
        inputs.forEach(function(inp) {
            const v = parseInt(inp.value) || 0;
            sum += v;
            if (v > 0) anySizeFilled = true;
        });

        if (anySizeFilled && sum !== totalEggs) {
            saveBtn.disabled = true;
            return;
        }

        saveBtn.disabled = false;
    }

    function openOverrideModal() {
        if (!currentSlotId) return;
        document.getElementById('overrideError').classList.add('hidden');
        document.getElementById('overridePinInput').value = '';
        document.getElementById('overridePinSection').classList.remove('hidden');
        document.getElementById('overridePasswordSection').classList.add('hidden');
        document.getElementById('overrideModal').style.display = 'flex';
        document.getElementById('overridePinInput').focus();
        lucide.createIcons();
    }

    function closeOverrideModal() {
        document.getElementById('overrideModal').style.display = 'none';
    }

    function submitOverride() {
        const pin = document.getElementById('overridePinInput').value;
        const password = document.getElementById('overridePasswordInput').value;

        fetch('{{ route("eggs.logging.verify-override") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ cage_slot_id: currentSlotId, pin: pin, password: password }),
        })
        .then(r => r.json().then(body => ({ status: r.status, body })))
        .then(({ status, body }) => {
            if (status === 200 && body.ok) {
                document.getElementById('eggCount').readOnly = false;
                overrideVerified = true;
                closeOverrideModal();
                if (body.needs_pin_setup) {
                    showNotification('No override PIN set yet — please set one in Account Settings.', 'info');
                }
            } else {
                const errEl = document.getElementById('overrideError');
                errEl.textContent = body.error || 'Verification failed.';
                errEl.classList.remove('hidden');
                const noPinYet = (body.error || '').includes('password');
                document.getElementById('overridePinSection').classList.toggle('hidden', noPinYet);
                document.getElementById('overridePasswordSection').classList.toggle('hidden', !noPinYet);
            }
        })
        .catch(function() {
            showNotification('Network error — please try again.', 'error');
        });
    }

    // ── Edit Log Modal ──────────────────────────────────────
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

    // ── AJAX form submission ─────────────────────────────────
    var eggForm = document.getElementById('eggForm');
    if (eggForm) {
        eggForm.addEventListener('submit', function(e) {
            if (eggForm._submitting) { e.preventDefault(); return; }

            var saveBtn = document.getElementById('saveBtn');
            var origText = saveBtn ? saveBtn.innerHTML : '';

            var eggCount = parseInt(document.getElementById('eggCount').value) || 0;
            var hens = parseInt(document.getElementById('henCount').value) || 0;
            if (eggCount > hens) {
                e.preventDefault();
                return;
            }

            e.preventDefault();
            eggForm._submitting = true;
            if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving\u2026'; }

            fetch(eggForm.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value || '',
                },
                body: new FormData(eggForm),
            })
            .then(function(r) {
                return r.json().then(function(body) {
                    return { status: r.status, body: body, ok: r.ok };
                });
            })
            .then(function(resp) {
                eggForm._submitting = false;
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = origText; }

                if (resp.ok && resp.body.success) {
                    if (typeof showNotification === 'function') {
                        showNotification(resp.body.message || 'Production log saved.', 'success');
                    }

                    // Update slot card visual to show it's logged
                    var slotCard = document.querySelector('.slot-card[data-slot-id="' + currentSlotId + '"]');
                    if (slotCard) {
                        slotCard.dataset.todayEggs = resp.body.log.egg_count;
                        slotCard.style.backgroundColor = '#eaf6ee';

                        // Remove existing checkmark if any, then add green check
                        var existingCheck = slotCard.querySelector('.logged-check');
                        if (existingCheck) existingCheck.remove();

                        var check = document.createElement('span');
                        check.className = 'logged-check absolute top-0.5 left-0.5 w-3 h-3 rounded-full flex items-center justify-center';
                        check.style.backgroundColor = '#1f6b3a';
                        check.innerHTML = '<svg class="w-2 h-2 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                        slotCard.appendChild(check);

                    }

                    // Reset size breakdown fields
                    document.querySelectorAll('#sizeBreakdown .size-input').forEach(function(inp) {
                        inp.value = '0';
                    });
                    // Reset notes
                    var notesEl = eggForm.querySelector('textarea[name="notes"]');
                    if (notesEl) notesEl.value = '';

                    // Show the value that was actually saved (not a hardcoded 0) — the slot
                    // stays selected, but the field must reflect what's now persisted, or a
                    // save looks indistinguishable from the field silently resetting/failing.
                    var eggInput = document.getElementById('eggCount');
                    if (eggInput) {
                        eggInput.value = resp.body.log.egg_count;
                        if (eggInput.readOnly) {
                            overrideVerified = false;
                        }
                    }

                    computeHdep();
                    checkSizeSum();
                    validateForm();
                } else {
                    var errors = resp.body.errors || {};
                    var firstMsg = '';
                    for (var key in errors) {
                        if (errors.hasOwnProperty(key) && errors[key]) {
                            firstMsg = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
                            break;
                        }
                    }
                    if (typeof showNotification === 'function') {
                        showNotification(firstMsg || 'Validation failed.', 'error');
                    }
                }
            })
            .catch(function() {
                eggForm._submitting = false;
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = origText; }
                if (typeof showNotification === 'function') {
                    showNotification('Network error — please try again.', 'error');
                }
            });
        });
    }

    // Expose functions to global scope for inline onclick/onchange/oninput handlers
    window.selectSlot = selectSlot;
    window.clearSlotSelection = clearSlotSelection;
    window.switchCage = switchCage;
    window.computeHdep = computeHdep;
    window.checkSizeSum = checkSizeSum;
    window.validateForm = validateForm;
    window.openOverrideModal = openOverrideModal;
    window.closeOverrideModal = closeOverrideModal;
    window.submitOverride = submitOverride;
    window.openEditLog = openEditLog;
    window.closeEditLogModal = closeEditLogModal;
    window.editComputeHdep = editComputeHdep;

    // Auto-select cage from URL filter on page load
    var cageSelect = document.getElementById('cageSelect');
    if (cageSelect && cageSelect.value) {
        switchCage(cageSelect.value);
    }

    if (!window.__eggLoggingEscapeBound) {
        window.__eggLoggingEscapeBound = true;
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeOverrideModal();
                closeEditLogModal();
            }
        });
    }
})();
</script>
@endpush
