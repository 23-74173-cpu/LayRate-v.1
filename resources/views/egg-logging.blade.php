@extends('layouts.app')
@section('title', 'Egg Management')

@push('head')
<style>
    @media (max-width: 639px) {
        .cage-slot-grid {
            grid-template-columns: repeat(auto-fill, minmax(56px, 1fr)) !important;
        }
        .slot-card {
            min-height: 52px;
            font-size: 11px;
        }
    }
    .log-tab-btn.active {
        background: #fff !important;
        color: #1f1f1f !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .log-tab-content.hidden { display: none; }
</style>
@endpush

@section('content')
<div class="space-y-5">

    <x-page-header title="Egg Management" subtitle="Log daily egg production per cage slot" subtitle-id="egg-header-subtitle" />

    @include('eggs._tabs', ['activeTab' => 'logging'])

    <turbo-frame id="egg-content">

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
                        Today: <strong id="todayTotalEggs" style="color: #1f1f1f;">{{ number_format($todayTotal) }}</strong> eggs
                    </span>
                </div>
            </x-slot:headerSlot>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach($cages as $cage)
                @php
                    $slotCount = $cage->rows * $cage->slots_per_row;
                    $loggedCount = $todayLoggedCountByCage[$cage->cage_code] ?? 0;
                    $allLogged = $loggedCount >= $slotCount;
                @endphp
                <div class="rounded-xl border p-4 flex flex-col gap-2 min-h-[7rem] cage-overview-card cursor-pointer transition-all hover:shadow-md"
                     data-cage-id="{{ $cage->id }}" data-total-slots="{{ $slotCount }}"
                     onclick="switchCage('{{ $cage->id }}')"
                     role="button" tabindex="0"
                     onkeydown="if(event.key==='Enter'||event.key===' ') { event.preventDefault(); switchCage('{{ $cage->id }}'); }"
                     style="background-color: #ffffff; border-color: #e6e6e6;">
                    <div class="flex items-center justify-between gap-2">
                        <x-cage-color :cage="$cage" />
                        <span class="text-xs px-2 py-0.5 rounded-full font-semibold whitespace-nowrap shrink-0"
                              style="background-color: {{ $cage->colorSoft }}; color: {{ $cage->color }};">
                            {{ $slotCount }} slot{{ $slotCount !== 1 ? 's' : '' }}
                        </span>
                    </div>
                    <span class="text-xs truncate" style="color: #615d59;">{{ $cage->formatted_location }}</span>
                    @php $henCount = $henCountByCage[$cage->id] ?? 0; @endphp
                    <div class="flex items-center gap-1.5 text-xs mt-0.5">
                        @if($henCount === 0)
                            <span class="inline-flex items-center gap-1 font-semibold" style="color: #9b1c24;">
                                <i data-lucide="alert-circle" class="w-3 h-3"></i> Cage Empty
                            </span>
                        @else
                            <span style="color: #1f6b3a;">
                                <strong>{{ $henCount }}</strong> hen{{ $henCount !== 1 ? 's' : '' }}
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 text-xs mt-auto" style="color: #a39e98;">
                        <span>Logged:</span>
                        <span class="font-semibold whitespace-nowrap cage-logged-count" id="cage-logged-{{ $cage->id }}"
                              style="color: {{ $allLogged ? '#1f6b3a' : '#1f1f1f' }};">
                            {{ $loggedCount }}/{{ $slotCount }}
                        </span>
                        <span class="text-xs font-medium whitespace-nowrap cage-complete-badge" id="cage-complete-{{ $cage->id }}"
                              style="color: #1f6b3a; display: {{ $allLogged ? 'inline' : 'none' }};">Complete</span>
                        <span class="ml-auto font-semibold whitespace-nowrap cage-egg-count" id="cage-eggs-{{ $cage->id }}"
                              style="color: #1f1f1f;">
                            {{ number_format($todayByCage[$cage->cage_code] ?? 0) }} eggs
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </x-card>

        {{-- ── Log Entry (full-width row) ── --}}
        <x-card>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                <h3 class="text-lg font-semibold" style="color: #1f1f1f;">Log Entry</h3>
                <div id="logTabs" class="hidden flex items-center gap-1 bg-gray-100 rounded-lg p-1">
                    <button type="button" onclick="switchLogTab('manual')" id="logTabManual"
                            class="log-tab-btn px-4 py-1.5 text-sm font-medium rounded-md transition-all"
                            style="background: transparent; color: #6b7280;">
                        Manual Log
                    </button>
                    <button type="button" onclick="switchLogTab('total')" id="logTabTotal"
                            class="log-tab-btn active px-4 py-1.5 text-sm font-medium rounded-md transition-all"
                            style="background: #fff; color: #1f1f1f; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        Total Egg Log
                    </button>
                </div>
            </div>

            {{-- Manual Log Tab --}}
            <div id="logTabManualContent" class="log-tab-content hidden">

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
                <div class="cage-grid hidden overflow-x-auto" data-cage-id="{{ $cage->id }}">
                    <div class="grid gap-1.5 cage-slot-grid" style="grid-template-columns: repeat({{ $gridCols }}, minmax(60px, 100px));">
                        @foreach($cageSlotsForThis as $slot)
                        @php
                            $primaryHen = $slot->primaryHen();
                            $isSensor = $slot->hasBreakbeam();
                            $isLogged = $slot->today_egg_count > 0;
                        @endphp
                        @php $activeHenCount = $slot->active_hen_count; @endphp
                        <button type="button"
                                class="slot-card flex flex-col items-center justify-center aspect-square rounded-lg border transition-all relative select-none {{ $activeHenCount === 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}"
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
                                aria-label="{{ $cage->cage_code }} slot {{ $slot->row_number }}-{{ $slot->column_number }}, {{ $activeHenCount }} hens{{ $activeHenCount === 0 ? ', no hens assigned' : '' }}"
                                tabindex="{{ $activeHenCount === 0 ? '-1' : '0' }}"
                                title="{{ $activeHenCount === 0 ? 'No hens assigned to this slot' : '' }}">

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
                    Click a cage card above to start logging.
                </div>

                {{-- Active form --}}
                <div id="slotForm" class="hidden">
                    <form method="POST" action="{{ route('eggs.logging.store') }}" id="eggForm" data-turbo="false">
                        @csrf

                        {{-- Single slot info bar --}}
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

                        {{-- Multi-slot info bar --}}
                        <div id="multiSlotBar" class="hidden mb-3 p-3 rounded-lg text-xs" style="background-color: #e8f0fe; color: #1f1f1f;">
                            <span id="multiSlotCount">0 slots selected</span> — same egg count will be saved to all selected slots.
                        </div>

                        <input type="hidden" name="cage_slot_ids" id="cageSlotIdsInput" value="">
                        <input type="hidden" name="cage_slot_id" id="cageSlotId" value="">

                        <div id="logEntryFields">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {{-- Date --}}
                            <div>
                                <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Date</label>
                                <input type="date" name="log_date" value="{{ \App\Services\ReportingDateService::reportingDateString() }}" required
                                       class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                       style="border-color: #e6e6e6; color: #1f1f1f;">
                            </div>

                            {{-- Egg Count --}}
                            <div>
                                <label id="eggCountLabel" class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Egg Count</label>
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
                                <p id="multiEggHint" class="hidden mt-1.5 text-xs" style="color: #615d59;">
                                    This egg count will be saved to all <span id="multiEggHintCount">0</span> selected slots.
                                </p>
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
            </div>

            {{-- Total Egg Log Tab --}}
            <div id="logTabTotalContent" class="log-tab-content">
                <div id="totalCageLogEmpty" class="text-center py-10 text-sm" style="color: #a39e98;">
                    <i data-lucide="layout-grid" class="w-6 h-6 mx-auto mb-2" style="color: #d1d5db;"></i>
                    Select a cage above to log total eggs.
                </div>
                <div id="totalCageLogForm" class="hidden">
                    <div class="space-y-3 text-sm mb-4 p-4 rounded-lg" style="background-color: #f6f5f4;">
                        <div class="flex justify-between">
                            <span style="color: #615d59;">Cage</span>
                            <span id="tcCageCode" class="font-semibold" style="color: #1f1f1f;"></span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: #615d59;">Breed</span>
                            <span id="tcBreed" style="color: #1f1f1f;"></span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: #615d59;">Total Hens</span>
                            <span id="tcTotalHens" class="font-semibold" style="color: #1f1f1f;"></span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: #615d59;">Date</span>
                            <span id="tcDate" style="color: #1f1f1f;"></span>
                        </div>
                    </div>

                    <div class="border-t pt-4" style="border-color: #e6e6e6;">
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Total Eggs</label>
                        <input type="number" id="tcTotalEggs" min="0" placeholder="0"
                               oninput="validateTotalCageLog()"
                               class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                               style="border-color: #e6e6e6; color: #1f1f1f;">
                        <p id="tcError" class="hidden text-xs mt-1" style="color: #9b1c24;"></p>
                        <p class="text-xs mt-1" style="color: #a39e98;">Eggs will be distributed across slots (max per slot = hens in that slot).</p>
                    </div>

                    <div class="flex items-center gap-3 mt-4">
                        <button type="button" onclick="clearTotalCageLog()"
                                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                                style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                                onmouseover="this.style.backgroundColor='#f6f5f4'"
                                onmouseout="this.style.backgroundColor='transparent'">
                            Cancel
                        </button>
                        <x-button type="button" id="tcSaveBtn" onclick="submitTotalCageLog()" disabled class="px-6 py-2">
                            Save Record
                        </x-button>
                    </div>
                </div>
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
    <div id="overrideModal" data-modal  data-close="closeOverrideModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" style="display: none;" role="dialog" aria-modal="true">
        <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeOverrideModal()"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
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

    {{-- ── Multi-Save Confirmation Modal ── --}}
    <div id="confirmMultiModal" data-modal  data-close="closeConfirmMultiModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" style="display: none;" role="dialog" aria-modal="true">
        <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeConfirmMultiModal()"></div>
        <div class="relative w-full max-w-md rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Confirm Multi-Slot Logging</h2>
                <button onclick="closeConfirmMultiModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>
            <div class="space-y-3 text-sm">
                <div class="p-3 rounded-lg" style="background-color: #f6f5f4;">
                    <div class="flex justify-between mb-1">
                        <span style="color: #615d59;">Selected slots</span>
                        <span id="confirmSlotCount" class="font-semibold" style="color: #1f1f1f;"></span>
                    </div>
                    <div class="flex justify-between mb-1">
                        <span style="color: #615d59;">Slots</span>
                        <span id="confirmSlotList" class="font-semibold text-xs" style="color: #1f1f1f;"></span>
                    </div>
                    <div class="flex justify-between mb-1">
                        <span style="color: #615d59;">Egg count per slot</span>
                        <span id="confirmEggPerSlot" class="font-semibold" style="color: #1f1f1f;"></span>
                    </div>
                    <div class="flex justify-between pt-1 border-t" style="border-color: #e6e6e6;">
                        <span style="color: #615d59;">Total eggs to record</span>
                        <span id="confirmTotalEggs" class="font-semibold" style="color: #0075de;"></span>
                    </div>
                </div>
                <p class="text-xs" style="color: #a39e98;">This action will create or update production logs for all selected slots.</p>
            </div>
            <div class="flex items-center gap-3 mt-4">
                <button type="button" onclick="closeConfirmMultiModal()"
                        class="flex-1 px-4 py-2.5 text-sm font-medium rounded-lg transition-colors text-center"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    Cancel
                </button>
                <x-button type="button" id="confirmMultiSaveBtn" onclick="executeMultiSave()" class="flex-1 py-2.5 text-center">
                    Confirm Save
                </x-button>
            </div>
        </div>
    </div>

<script>
(function() {
    // ── ONE-TIME: function definitions and document-level listeners ──
    if (!window.__eggLoggingBound) {
        window.__eggLoggingBound = true;

        let currentSlotId = null;
        let currentHasSensor = false;
        let overrideVerified = false;

        // ── Drag-to-select state ──
        let isDragging = false;
        let dragMoved = false;
        let selectedSlotIds = new Set();
        let isMultiSelect = false;
        let pendingFormData = null;
        let pendingSaveBtn = null;
        let pendingOrigText = '';

        function resetSlotStyle(c) {
            c.style.borderColor = '#e6e6e6';
            c.style.borderWidth = '1px';
            var wasLogged = parseInt(c.dataset.todayEggs) > 0;
            c.style.backgroundColor = wasLogged ? '#eaf6ee' : '#ffffff';
        }

        function clearSlotSelection() {
            clearAllSlotSelections();
            document.getElementById('slotFormPlaceholder').classList.remove('hidden');
            document.getElementById('slotForm').classList.add('hidden');
            checkSizeSum();
            validateForm();
        }

        var currentCageId = null;
        var logMode = null; // 'manual' or 'total'

        window.switchLogTab = function(tab) {
            logMode = tab;

            var manualBtn = document.getElementById('logTabManual');
            var totalBtn = document.getElementById('logTabTotal');
            manualBtn.classList.remove('active');
            totalBtn.classList.remove('active');
            manualBtn.style.background = 'transparent';
            manualBtn.style.color = '#6b7280';
            manualBtn.style.boxShadow = 'none';
            totalBtn.style.background = 'transparent';
            totalBtn.style.color = '#6b7280';
            totalBtn.style.boxShadow = 'none';

            document.querySelectorAll('.log-tab-content').forEach(function(c) { c.classList.add('hidden'); });

            if (tab === 'manual') {
                manualBtn.classList.add('active');
                manualBtn.style.background = '#fff';
                manualBtn.style.color = '#1f1f1f';
                manualBtn.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
                document.getElementById('logTabManualContent').classList.remove('hidden');
                if (currentCageId) {
                    var target = document.querySelector('.cage-grid[data-cage-id="' + currentCageId + '"]');
                    if (target) target.classList.remove('hidden');
                    document.getElementById('slotFormPlaceholder').classList.remove('hidden');
                }
            } else {
                totalBtn.classList.add('active');
                totalBtn.style.background = '#fff';
                totalBtn.style.color = '#1f1f1f';
                totalBtn.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
                document.getElementById('logTabTotalContent').classList.remove('hidden');
                document.querySelectorAll('.cage-grid').forEach(g => g.classList.add('hidden'));
                document.getElementById('slotFormPlaceholder').classList.add('hidden');
                document.getElementById('slotForm').classList.add('hidden');
                if (currentCageId) {
                    window.showTotalCageLogForm();
                }
            }
        };

        window.showTotalCageLogForm = function() {
            var cageCard = document.querySelector('.cage-overview-card[data-cage-id="' + currentCageId + '"]');
            if (!cageCard) return;

            var cageCode = cageCard.querySelector('[data-cage-code]')?.dataset.cageCode || '';
            var slots = document.querySelectorAll('.slot-card[data-cage-id="' + currentCageId + '"]');
            var totalHens = 0;
            var breeds = {};
            var slotData = [];

            slots.forEach(function(slot) {
                var hens = parseInt(slot.dataset.hens) || 0;
                var breed = slot.dataset.breed || '—';
                totalHens += hens;
                if (breed !== '—') breeds[breed] = (breeds[breed] || 0) + hens;
                slotData.push({ id: slot.dataset.slotId, hens: hens });
            });

            var mainBreed = Object.keys(breeds).sort(function(a,b) { return breeds[b] - breeds[a]; })[0] || '—';
            var reportingDate = '{{ \App\Services\ReportingDateService::reportingDateString() }}';

            document.getElementById('tcCageCode').textContent = cageCode;
            document.getElementById('tcBreed').textContent = mainBreed;
            document.getElementById('tcTotalHens').textContent = totalHens;
            document.getElementById('tcDate').textContent = reportingDate;
            document.getElementById('tcTotalEggs').value = '';
            document.getElementById('tcTotalEggs').max = totalHens;
            document.getElementById('tcError').classList.add('hidden');
            document.getElementById('tcSaveBtn').disabled = true;

            window._tcSlotData = slotData;
            window._tcTotalHens = totalHens;

            document.getElementById('totalCageLogEmpty').classList.add('hidden');
            document.getElementById('totalCageLogForm').classList.remove('hidden');
        };

        window.clearTotalCageLog = function() {
            document.getElementById('totalCageLogEmpty').classList.remove('hidden');
            document.getElementById('totalCageLogForm').classList.add('hidden');
            document.getElementById('tcTotalEggs').value = '';
            document.getElementById('tcError').classList.add('hidden');
            document.getElementById('tcSaveBtn').disabled = true;
        };

        window.validateTotalCageLog = function() {
            var input = document.getElementById('tcTotalEggs');
            var error = document.getElementById('tcError');
            var saveBtn = document.getElementById('tcSaveBtn');
            var totalHens = window._tcTotalHens || 0;
            var val = parseInt(input.value) || 0;

            if (val > totalHens) {
                error.textContent = 'Total eggs cannot exceed ' + totalHens + ' hens.';
                error.classList.remove('hidden');
                saveBtn.disabled = true;
            } else if (val < 0) {
                error.textContent = 'Cannot be negative.';
                error.classList.remove('hidden');
                saveBtn.disabled = true;
            } else {
                error.classList.add('hidden');
                saveBtn.disabled = val === 0;
            }
        }

        window.submitTotalCageLog = function() {
            var totalEggs = parseInt(document.getElementById('tcTotalEggs').value) || 0;
            if (totalEggs === 0) return;

            var slots = window._tcSlotData || [];
            var totalHens = window._tcTotalHens || 0;
            if (totalHens === 0 || slots.length === 0) return;

            var remaining = totalEggs;
            var distributions = [];

            // Fill each slot up to its hen count, proportionally
            slots.forEach(function(slot, i) {
                if (i === slots.length - 1) {
                    distributions.push({ id: slot.id, eggs: remaining });
                } else {
                    var proportional = Math.round((slot.hens / totalHens) * totalEggs);
                    var capped = Math.min(proportional, slot.hens, remaining);
                    distributions.push({ id: slot.id, eggs: capped });
                    remaining -= capped;
                }
            });

            // Submit each distribution via AJAX
            var date = '{{ \App\Services\ReportingDateService::reportingDateString() }}';
            var promises = distributions.filter(function(d) { return d.eggs > 0; }).map(function(d) {
                var slotInfo = slots.find(function(s) { return s.id == d.id; });
                var henCount = slotInfo ? slotInfo.hens : d.eggs;
                var formData = new FormData();
                formData.append('cage_slot_id', d.id);
                formData.append('log_date', date);
                formData.append('egg_count', d.eggs);
                formData.append('hen_count', henCount);
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                return fetch('{{ route("eggs.logging.store") }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
            });

            Promise.all(promises).then(function() {
                window.clearTotalCageLog();
                location.reload();
            }).catch(function() {
                document.getElementById('tcError').textContent = 'An error occurred. Please try again.';
                document.getElementById('tcError').classList.remove('hidden');
            });
        }

        function clearAllSlotSelections() {
            document.querySelectorAll('.slot-card').forEach(resetSlotStyle);
            document.querySelectorAll('.slot-card').forEach(function(c) {
                c.classList.remove('ring-2', 'ring-[#0075de]', 'ring-offset-1', 'bg-[#0075de]/10');
            });
            selectedSlotIds.clear();
            isMultiSelect = false;
            currentSlotId = null;
            overrideVerified = false;
            document.getElementById('cageSlotIdsInput').value = '';
            document.getElementById('cageSlotId').value = '';
        }

        function toggleSlotSelection(el) {
            if (el.dataset.empty === '1') return;
            var id = el.dataset.slotId;
            if (selectedSlotIds.has(id)) {
                selectedSlotIds.delete(id);
                el.classList.remove('ring-2', 'ring-[#0075de]', 'ring-offset-1', 'bg-[#0075de]/10');
            } else {
                selectedSlotIds.add(id);
                el.classList.add('ring-2', 'ring-[#0075de]', 'ring-offset-1', 'bg-[#0075de]/10');
            }
            document.getElementById('cageSlotIdsInput').value = Array.from(selectedSlotIds).join(',');
        }

        function selectSingleSlot(card) {
            document.querySelectorAll('.slot-card').forEach(resetSlotStyle);
            clearAllSlotSelections();

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
            document.getElementById('selectedSlotBar').classList.remove('hidden');
            document.getElementById('multiSlotBar').classList.add('hidden');
            document.getElementById('multiEggHint').classList.add('hidden');
            document.getElementById('eggCountLabel').textContent = 'Egg Count';
            var hdepEl = document.getElementById('hdepDisplay');
            if (hdepEl) hdepEl.classList.remove('hidden');
            document.getElementById('slotFormPlaceholder').classList.add('hidden');
            document.getElementById('slotForm').classList.remove('hidden');
            computeHdep();
            checkSizeSum();
            validateForm();
        }

        function updateFormForMultiSelect() {
            if (selectedSlotIds.size === 0) {
                clearSlotSelection();
                return;
            }
            if (selectedSlotIds.size === 1) {
                var card = document.querySelector('.slot-card[data-slot-id="' + Array.from(selectedSlotIds)[0] + '"]');
                if (card) { selectSingleSlot(card); return; }
            }
            isMultiSelect = true;
            currentSlotId = null;
            currentHasSensor = false;
            overrideVerified = false;

            document.getElementById('selectedSlotBar').classList.add('hidden');
            document.getElementById('multiSlotBar').classList.remove('hidden');
            document.getElementById('multiSlotCount').textContent = selectedSlotIds.size + ' slots selected';

            document.getElementById('overrideLabel').classList.add('hidden');
            document.getElementById('multiEggHint').classList.remove('hidden');
            document.getElementById('multiEggHintCount').textContent = selectedSlotIds.size;
            document.getElementById('eggCountLabel').textContent = 'Total Egg Count per Cage Slot';
            var totalHens = 0;
            selectedSlotIds.forEach(function(id) {
                var card = document.querySelector('.slot-card[data-slot-id="' + id + '"]');
                if (card) totalHens += parseInt(card.dataset.hens) || 0;
            });
            document.getElementById('henCount').value = totalHens || 1;
            document.getElementById('eggCount').value = 0;
            document.getElementById('eggCount').readOnly = false;
            var hdepEl = document.getElementById('hdepDisplay');
            if (hdepEl) hdepEl.classList.remove('hidden');

            document.getElementById('slotFormPlaceholder').classList.add('hidden');
            document.getElementById('slotForm').classList.remove('hidden');
            computeHdep();
            checkSizeSum();
            validateForm();
        }

        function onSlotMouseDown(e) {
            if (e.button !== 0) return;
            var el = e.currentTarget;
            if (el.dataset.empty === '1') return;
            isDragging = true;
            dragMoved = false;
            clearAllSlotSelections();
            toggleSlotSelection(el);
        }

        function onSlotMouseEnter(e) {
            if (!isDragging) return;
            var el = e.currentTarget;
            if (el.dataset.empty === '1') return;
            dragMoved = true;
            toggleSlotSelection(el);
        }

        function onGlobalMouseUp() {
            if (!isDragging) return;
            isDragging = false;
            if (selectedSlotIds.size === 0) {
                clearSlotSelection();
            } else if (dragMoved) {
                updateFormForMultiSelect();
            } else {
                var card = document.querySelector('.slot-card[data-slot-id="' + Array.from(selectedSlotIds)[0] + '"]');
                if (card) selectSingleSlot(card);
            }
        }

        // ── Touch drag-to-select (mobile) ──
        var touchDragging = false;
        var touchDragMoved = false;
        var touchLastSlotId = null;
        var touchLastSlotEl = null;
        var touchLastTime = 0;
        var TOUCH_THROTTLE_MS = 25;

        function getSlotCardFromTouch(touch) {
            var el = document.elementFromPoint(touch.clientX, touch.clientY);
            if (!el) return null;
            return el.closest('.slot-card');
        }

        // Select all slots between two slots using row/col positions (add only, no toggle)
        function selectSlotsBetween(slotA, slotB) {
            if (!slotA || !slotB) return;
            var aRow = parseInt(slotA.dataset.row), aCol = parseInt(slotA.dataset.col);
            var bRow = parseInt(slotB.dataset.row), bCol = parseInt(slotB.dataset.col);
            var rMin = Math.min(aRow, bRow), rMax = Math.max(aRow, bRow);
            var cMin = Math.min(aCol, bCol), cMax = Math.max(aCol, bCol);
            var cageId = slotA.dataset.cageId;
            document.querySelectorAll('.slot-card[data-cage-id="' + cageId + '"]').forEach(function(el) {
                if (el.dataset.empty === '1') return;
                if (selectedSlotIds.has(el.dataset.slotId)) return;
                var r = parseInt(el.dataset.row), c = parseInt(el.dataset.col);
                if (r >= rMin && r <= rMax && c >= cMin && c <= cMax) {
                    selectedSlotIds.add(el.dataset.slotId);
                    el.classList.add('ring-2', 'ring-[#0075de]', 'ring-offset-1', 'bg-[#0075de]/10');
                }
            });
            document.getElementById('cageSlotIdsInput').value = Array.from(selectedSlotIds).join(',');
        }

        function onSlotTouchStart(e) {
            var el = e.currentTarget;
            if (el.dataset.empty === '1') return;
            touchDragging = true;
            touchDragMoved = false;
            touchLastSlotId = el.dataset.slotId;
            touchLastSlotEl = el;
            touchLastTime = Date.now();
            clearAllSlotSelections();
            toggleSlotSelection(el);
            e.preventDefault();
        }

        function onSlotTouchMove(e) {
            if (!touchDragging) return;
            e.preventDefault();
            var now = Date.now();
            if (now - touchLastTime < TOUCH_THROTTLE_MS) return;
            touchLastTime = now;

            var touch = e.touches[0];
            var card = getSlotCardFromTouch(touch);
            if (!card || card.dataset.empty === '1') return;
            if (card.dataset.slotId === touchLastSlotId) return;

            touchDragMoved = true;
            // Fill in any slots between last position and current
            selectSlotsBetween(touchLastSlotEl, card);
            touchLastSlotId = card.dataset.slotId;
            touchLastSlotEl = card;
        }

        function onSlotTouchEnd() {
            if (!touchDragging) return;
            touchDragging = false;
            touchLastSlotId = null;
            touchLastSlotEl = null;
            if (selectedSlotIds.size === 0) {
                clearSlotSelection();
            } else if (touchDragMoved) {
                updateFormForMultiSelect();
            } else {
                var card = document.querySelector('.slot-card[data-slot-id="' + Array.from(selectedSlotIds)[0] + '"]');
                if (card) selectSingleSlot(card);
            }
        }

        if (!window.__eggLoggingDragBound) {
            window.__eggLoggingDragBound = true;
            document.addEventListener('mouseup', onGlobalMouseUp);
            document.addEventListener('touchend', onSlotTouchEnd);
        }

        function switchCage(cageId) {
            clearSlotSelection();
            currentCageId = cageId;
            logMode = null;

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

            document.querySelectorAll('.cage-grid').forEach(g => {
                g.classList.add('hidden');
            });

            document.getElementById('logTabs').classList.remove('hidden');
            document.getElementById('slotFormPlaceholder').classList.remove('hidden');
            document.getElementById('slotForm').classList.add('hidden');
            document.getElementById('totalCageLogEmpty').classList.remove('hidden');
            document.getElementById('totalCageLogForm').classList.add('hidden');
            document.getElementById('logTabManualContent').classList.add('hidden');
            document.getElementById('logTabTotalContent').classList.remove('hidden');

            // Reset tabs to Total Egg Log
            window.switchLogTab('total');

            checkSizeSum();
            validateForm();
        }

        function computeHdep() {
            const eggs = parseInt(document.getElementById('eggCount').value) || 0;
            const hens = parseInt(document.getElementById('henCount').value) || 1;
            var count = eggs;
            if (isMultiSelect && selectedSlotIds.size > 0) {
                count = eggs * selectedSlotIds.size;
            }
            const hdep = ((count / hens) * 100).toFixed(1);
            const el = document.getElementById('hdepDisplay');
            el.innerHTML = '<span class="relative group">HDEP: ' + hdep + '% <i data-lucide="info" class="inline w-3 h-3 ml-0.5" style="color: #a39e98;"></i></span>';
            el.style.backgroundColor = count > hens ? '#fbe4e6' : '#f6f5f4';
            el.style.borderColor = count > hens ? '#f3cdd0' : '#e6e6e6';
            el.style.color = count > hens ? '#9b1c24' : '#1f1f1f';
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

        function closeConfirmMultiModal() {
            document.getElementById('confirmMultiModal').style.display = 'none';
            pendingFormData = null;
            pendingSaveBtn = null;
        }

        function showConfirmMultiModal() {
            var eggCount = parseInt(document.getElementById('eggCount').value) || 0;
            var totalEggs = eggCount * selectedSlotIds.size;

            var slotLabels = [];
            selectedSlotIds.forEach(function(id) {
                var card = document.querySelector('.slot-card[data-slot-id="' + id + '"]');
                if (card) slotLabels.push(card.dataset.cageCode + ' R' + card.dataset.row + '-C' + card.dataset.col);
            });

            document.getElementById('confirmSlotCount').textContent = selectedSlotIds.size + ' slots';
            document.getElementById('confirmSlotList').textContent = slotLabels.join(', ');
            document.getElementById('confirmEggPerSlot').textContent = eggCount;
            document.getElementById('confirmTotalEggs').textContent = totalEggs.toLocaleString();
            document.getElementById('confirmMultiModal').style.display = 'flex';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        function executeMultiSave() {
            if (!pendingFormData) return;
            document.getElementById('confirmMultiModal').style.display = 'none';

            if (pendingSaveBtn) { pendingSaveBtn.disabled = true; pendingSaveBtn.textContent = 'Saving\u2026'; }

            var form = document.getElementById('eggForm');

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value || '',
                },
                body: pendingFormData,
            })
            .then(function(r) {
                return r.json().then(function(body) {
                    return { status: r.status, body: body, ok: r.ok };
                });
            })
            .then(function(resp) {
                form._submitting = false;
                if (pendingSaveBtn) { pendingSaveBtn.disabled = false; pendingSaveBtn.textContent = pendingOrigText; }
                pendingFormData = null;
                pendingSaveBtn = null;

                if (resp.ok && resp.body.success) {
                    if (typeof showNotification === 'function') {
                        showNotification(resp.body.message || 'Production log saved.', 'success');
                    }

                    // Prompt user to log feed and environment data
                    if (resp.body.reminder && typeof showNotification === 'function') {
                        setTimeout(function() {
                            showNotification('Don\'t forget to log feed and environment data for today.', 'info');
                        }, 1500);
                    }

                    var logs = resp.body.logs || [];
                    var cageDeltas = {};
                    var grandDelta = 0;

                    logs.forEach(function(l) {
                        var slotCard = document.querySelector('.slot-card[data-slot-id="' + l.cage_slot_id + '"]');
                        if (!slotCard) return;

                        var prevEggs = parseInt(slotCard.dataset.todayEggs) || 0;
                        var savedEggs = parseInt(l.egg_count) || 0;
                        var delta = savedEggs - prevEggs;
                        grandDelta += delta;

                        slotCard.dataset.todayEggs = savedEggs;
                        slotCard.style.backgroundColor = '#eaf6ee';

                        var existingCheck = slotCard.querySelector('.logged-check');
                        if (existingCheck) existingCheck.remove();

                        var check = document.createElement('span');
                        check.className = 'logged-check absolute top-0.5 left-0.5 w-3 h-3 rounded-full flex items-center justify-center';
                        check.style.backgroundColor = '#1f6b3a';
                        check.innerHTML = '<svg class="w-2 h-2 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                        slotCard.appendChild(check);

                        var cid = parseInt(slotCard.dataset.cageId);
                        if (!cageDeltas[cid]) cageDeltas[cid] = 0;
                        cageDeltas[cid] += delta;
                    });

                    // Update cage overview cards
                    Object.keys(cageDeltas).forEach(function(cid) {
                        var card = document.querySelector('.cage-overview-card[data-cage-id="' + cid + '"]');
                        if (!card) return;
                        var totalSlots = parseInt(card.dataset.totalSlots) || 0;
                        var loggedCount = document.querySelectorAll('.slot-card[data-cage-id="' + cid + '"] .logged-check').length;
                        if (loggedCount > totalSlots) loggedCount = totalSlots;
                        var loggedEl = document.getElementById('cage-logged-' + cid);
                        var completeEl = document.getElementById('cage-complete-' + cid);
                        if (loggedEl) {
                            loggedEl.textContent = loggedCount + '/' + totalSlots;
                            loggedEl.style.color = loggedCount >= totalSlots ? '#1f6b3a' : '#1f1f1f';
                        }
                        if (completeEl) {
                            completeEl.style.display = loggedCount >= totalSlots ? 'inline' : 'none';
                        }
                        var eggsEl = document.getElementById('cage-eggs-' + cid);
                        if (eggsEl) {
                            var currentEggs = parseInt(eggsEl.textContent.replace(/[^0-9]/g, '')) || 0;
                            eggsEl.textContent = (currentEggs + cageDeltas[cid]).toLocaleString() + ' eggs';
                        }
                    });

                    var totalEl = document.getElementById('todayTotalEggs');
                    if (totalEl) {
                        var currentTotal = parseInt(totalEl.textContent.replace(/[^0-9]/g, '')) || 0;
                        totalEl.textContent = (currentTotal + grandDelta).toLocaleString();
                    }

                    clearSlotSelection();
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
                form._submitting = false;
                if (pendingSaveBtn) { pendingSaveBtn.disabled = false; pendingSaveBtn.textContent = pendingOrigText; }
                pendingFormData = null;
                pendingSaveBtn = null;
                if (typeof showNotification === 'function') {
                    showNotification('Network error — please try again.', 'error');
                }
            });
        }

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

        function editCheckSizeSum() {
            const totalEggs = parseInt(document.getElementById('editEggCount').value) || 0;
            const inputs = document.querySelectorAll('#editSizeBreakdown .size-input');
            let sum = 0;
            inputs.forEach(function(inp) { sum += parseInt(inp.value) || 0; });
            const msg = document.getElementById('editSizeSumMsg');

            if (sum === totalEggs) {
                msg.innerHTML = 'Sum: ' + sum + ' <span style="color: #1f6b3a;">✓</span>';
                msg.style.color = '#1f6b3a';
            } else {
                msg.innerHTML = 'Sum: ' + sum + ' <span style="color: #9b1c24;">(should be ' + totalEggs + ')</span>';
                msg.style.color = '#9b1c24';
            }
        }

        // ── Live SSE egg count ──
        var cageTCode = 'CAGE-T';

        function updateCageTCount(slotId, count) {
            var card = document.querySelector('.slot-card[data-slot-id="' + slotId + '"]');
            if (!card) return;
            var prev = parseInt(card.dataset.todayEggs) || 0;
            if (count !== prev) {
                card.dataset.todayEggs = count;
                card.style.backgroundColor = count > 0 ? '#eaf6ee' : '#ffffff';

                if (count > 0 && prev === 0) {
                    var check = document.createElement('span');
                    check.className = 'logged-check absolute top-0.5 left-0.5 w-3 h-3 rounded-full flex items-center justify-center';
                    check.style.backgroundColor = '#1f6b3a';
                    check.innerHTML = '<svg class="w-2 h-2 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                    card.appendChild(check);
                }

                if (currentSlotId && card.dataset.slotId == currentSlotId) {
                    var eggInput = document.getElementById('eggCount');
                    if (eggInput && eggInput.readOnly) {
                        eggInput.value = count;
                        computeHdep();
                        checkSizeSum();
                        validateForm();
                    }
                }
            }
        }

        window.__eggCountSource = null;

        function connectEggCountSSE() {
            if (!document.getElementById('todayTotalEggs')) return;

            if (window.__eggCountSource) {
                window.__eggCountSource.close();
            }

            var url = '{{ route("eggs.logging.live-count") }}?cage_code=' + cageTCode;
            window.__eggCountSource = new EventSource(url);

            window.__eggCountSource.addEventListener('count', function(e) {
                try {
                    var data = JSON.parse(e.data);
                    if (data.counts) {
                        for (var slotId in data.counts) {
                            if (data.counts.hasOwnProperty(slotId)) {
                                updateCageTCount(slotId, data.counts[slotId].egg_count);
                            }
                        }
                    }
                } catch(err) {}
            });

            window.__eggCountSource.addEventListener('cage_stats', function(e) {
                try {
                    var cageStats = JSON.parse(e.data);
                    var grandTotal = 0;
                    for (var cageId in cageStats) {
                        if (!cageStats.hasOwnProperty(cageId)) continue;
                        var stat = cageStats[cageId];
                        var card = document.querySelector('.cage-overview-card[data-cage-id="' + cageId + '"]');
                        if (!card) continue;
                        var totalSlots = parseInt(card.dataset.totalSlots) || 0;
                        var loggedCount = stat.logged_count || 0;
                        var loggedEl = document.getElementById('cage-logged-' + cageId);
                        var completeEl = document.getElementById('cage-complete-' + cageId);
                        if (loggedEl) {
                            loggedEl.textContent = loggedCount + '/' + totalSlots;
                            loggedEl.style.color = loggedCount >= totalSlots ? '#1f6b3a' : '#1f1f1f';
                        }
                        if (completeEl) {
                            completeEl.style.display = loggedCount >= totalSlots ? 'inline' : 'none';
                        }
                        var eggsEl = document.getElementById('cage-eggs-' + cageId);
                        if (eggsEl) {
                            eggsEl.textContent = (stat.total_eggs || 0).toLocaleString() + ' eggs';
                        }
                        grandTotal += stat.total_eggs || 0;
                    }
                    var totalEl = document.getElementById('todayTotalEggs');
                    if (totalEl) totalEl.textContent = grandTotal.toLocaleString();
                } catch(err) {}
            });

            window.__eggCountSource.onerror = function() {
                if (window.__eggCountSource && window.__eggCountSource.readyState === EventSource.CLOSED) {
                    setTimeout(function() {
                        if (document.getElementById('todayTotalEggs')) {
                            connectEggCountSSE();
                        }
                    }, 3000);
                }
            };
        }

        // ── PER-RENDER INIT: re-runs on every page/frame render ──
        window.__eggLoggingInit = function () {
            // SSE — reconnect if not already active
            if (!window.__eggCountSource || window.__eggCountSource.readyState === EventSource.CLOSED) {
                connectEggCountSSE();
            }

            // Drag-to-select event wiring on slot cards
            document.querySelectorAll('.slot-card').forEach(function(el) {
                if (!el.__dragWired) {
                    el.__dragWired = true;
                    el.addEventListener('mousedown', onSlotMouseDown);
                    el.addEventListener('mouseenter', onSlotMouseEnter);
                    el.addEventListener('touchstart', onSlotTouchStart, { passive: false });
                    el.addEventListener('touchmove', onSlotTouchMove, { passive: false });
                }
            });

            // Form submit — bind to the current #eggForm element
            var form = document.getElementById('eggForm');
            if (form && !form.__submitBound) {
                form.__submitBound = true;
                form.addEventListener('submit', function(e) {
                    if (form._submitting) { e.preventDefault(); return; }

                    var saveBtn = document.getElementById('saveBtn');
                    var origText = saveBtn ? saveBtn.innerHTML : '';

                    var eggCount = parseInt(document.getElementById('eggCount').value) || 0;
                    var hens = parseInt(document.getElementById('henCount').value) || 0;
                    if (!isMultiSelect && eggCount > hens) {
                        e.preventDefault();
                        return;
                    }

                    e.preventDefault();
                    form._submitting = true;

                    var eggCount = parseInt(document.getElementById('eggCount').value) || 0;

                    // Build form data: always send cage_slot_ids[] array
                    var fd = new FormData(form);
                    fd.delete('cage_slot_ids');

                    if (isMultiSelect) {
                        selectedSlotIds.forEach(function(id) { fd.append('cage_slot_ids[]', id); });
                    } else if (currentSlotId) {
                        fd.append('cage_slot_ids[]', currentSlotId);
                    }

                    if (isMultiSelect) {
                        // Show confirmation modal instead of saving directly
                        if (saveBtn) { saveBtn.disabled = false; }
                        form._submitting = false;
                        pendingFormData = fd;
                        pendingSaveBtn = saveBtn;
                        pendingOrigText = origText;
                        showConfirmMultiModal();
                        return;
                    }

                    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving\u2026'; }

                    fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value || '',
                        },
                        body: fd,
                    })
                    .then(function(r) {
                        return r.json().then(function(body) {
                            return { status: r.status, body: body, ok: r.ok };
                        });
                    })
                    .then(function(resp) {
                        form._submitting = false;
                        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = origText; }

                        if (resp.ok && resp.body.success) {
                            if (typeof showNotification === 'function') {
                                showNotification(resp.body.message || 'Production log saved.', 'success');
                            }

                            // Prompt user to log feed and environment data
                            if (resp.body.reminder && typeof showNotification === 'function') {
                                setTimeout(function() {
                                    showNotification('Don\'t forget to log feed and environment data for today.', 'info');
                                }, 1500);
                            }

                            if (resp.body.logs) {
                                // Multi-save: clear selection, let SSE refresh counts
                                clearSlotSelection();
                                return;
                            }

                            // Single-save: update the slot card inline
                            var slotCard = document.querySelector('.slot-card[data-slot-id="' + currentSlotId + '"]');
                            var savedEggs = parseInt(resp.body.log.egg_count) || 0;
                            var cageId = null;
                            var eggDelta = savedEggs;
                            if (slotCard) {
                                var prevEggs = parseInt(slotCard.dataset.todayEggs) || 0;
                                eggDelta = savedEggs - prevEggs;
                                slotCard.dataset.todayEggs = savedEggs;
                                slotCard.style.backgroundColor = '#eaf6ee';
                                cageId = parseInt(slotCard.dataset.cageId);

                                var existingCheck = slotCard.querySelector('.logged-check');
                                if (existingCheck) existingCheck.remove();

                                var check = document.createElement('span');
                                check.className = 'logged-check absolute top-0.5 left-0.5 w-3 h-3 rounded-full flex items-center justify-center';
                                check.style.backgroundColor = '#1f6b3a';
                                check.innerHTML = '<svg class="w-2 h-2 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                                slotCard.appendChild(check);
                            }

                            if (cageId) {
                                var card = document.querySelector('.cage-overview-card[data-cage-id="' + cageId + '"]');
                                if (card) {
                                    var totalSlots = parseInt(card.dataset.totalSlots) || 0;
                                    var loggedCount = document.querySelectorAll('.slot-card[data-cage-id="' + cageId + '"] .logged-check').length;
                                    if (loggedCount > totalSlots) loggedCount = totalSlots;
                                    var loggedEl = document.getElementById('cage-logged-' + cageId);
                                    var completeEl = document.getElementById('cage-complete-' + cageId);
                                    if (loggedEl) {
                                        loggedEl.textContent = loggedCount + '/' + totalSlots;
                                        loggedEl.style.color = loggedCount >= totalSlots ? '#1f6b3a' : '#1f1f1f';
                                    }
                                    if (completeEl) {
                                        completeEl.style.display = loggedCount >= totalSlots ? 'inline' : 'none';
                                    }
                                    var eggsEl = document.getElementById('cage-eggs-' + cageId);
                                    if (eggsEl) {
                                        var currentEggs = parseInt(eggsEl.textContent.replace(/[^0-9]/g, '')) || 0;
                                        eggsEl.textContent = (currentEggs + eggDelta).toLocaleString() + ' eggs';
                                    }
                                }
                                var totalEl = document.getElementById('todayTotalEggs');
                                if (totalEl) {
                                    var currentTotal = parseInt(totalEl.textContent.replace(/[^0-9]/g, '')) || 0;
                                    totalEl.textContent = (currentTotal + eggDelta).toLocaleString();
                                }
                            }

                            document.querySelectorAll('#sizeBreakdown .size-input').forEach(function(inp) {
                                inp.value = '0';
                            });
                            var notesEl = form.querySelector('textarea[name="notes"]');
                            if (notesEl) notesEl.value = '';

                            var eggInput = document.getElementById('eggCount');
                            if (eggInput) {
                                eggInput.value = resp.body.log.egg_count;
                                if (currentHasSensor) {
                                    eggInput.readOnly = true;
                                }
                                overrideVerified = false;
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
                        form._submitting = false;
                        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = origText; }
                        if (typeof showNotification === 'function') {
                            showNotification('Network error — please try again.', 'error');
                        }
                    });
                });
            }
        };

        // Expose functions to global scope
        window.selectSlot = selectSingleSlot;
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
        window.closeConfirmMultiModal = closeConfirmMultiModal;
        window.executeMultiSave = executeMultiSave;

        // ── One-time document-level listeners ──
        if (!window.__eggLoggingEscapeBound) {
            window.__eggLoggingEscapeBound = true;
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (typeof closeOverrideModal === 'function') closeOverrideModal();
                    if (typeof closeConfirmMultiModal === 'function') closeConfirmMultiModal();
                    if (typeof closeEditLogModal === 'function') closeEditLogModal();
                }
            });
        }

        if (!window.__eggLoggingSseGuard) {
            window.__eggLoggingSseGuard = true;
            document.addEventListener('turbo:before-frame-render', function(e) {
                if (!e.target || e.target.id !== 'egg-content') return;
                if (window.__eggCountSource) {
                    window.__eggCountSource.close();
                    window.__eggCountSource = null;
                }
            });
        }

        if (!window.__eggLoggingNavGuard) {
            window.__eggLoggingNavGuard = true;
            document.addEventListener('turbo:before-render', function() {
                if (window.__eggCountSource) {
                    window.__eggCountSource.close();
                    window.__eggCountSource = null;
                }
            });
        }

        // ── Frame-load re-init (fires on every Turbo frame render) ──
        document.addEventListener('turbo:frame-load', function(e) {
            if (!e.target || e.target.id !== 'egg-content') return;
            if (window.__eggLoggingInit) window.__eggLoggingInit();
        });
    }

    // ── PER-RENDER (always runs on every script execution) ──
    if (window.__eggLoggingInit) window.__eggLoggingInit();

    @if($cageFilter)
    if (window.switchCage) switchCage('{{ $cageFilter }}');
    @endif
})();
</script>
</turbo-frame>
</div>
@endsection

@push('scripts')
{{-- Guided Walkthrough #3 — Log daily egg production (User Manual Step 3) --}}
<script>
(function() {
    var STARTED = (new URLSearchParams(window.location.search)).get('walkthrough3') === '1';
    var inProgress = sessionStorage.getItem('wt3_active') === '1';
    if (!STARTED && !inProgress) return;

    var startAt = (inProgress && sessionStorage.getItem('wt3_step'))
        ? parseInt(sessionStorage.getItem('wt3_step'), 10) : 0;
    sessionStorage.setItem('wt3_active', '1');
    sessionStorage.setItem('wt3_step', String(startAt));

    // ── Overlay DOM ──
    if (!document.getElementById('wt3Overlay')) {
        var ov = document.createElement('div');
        ov.id = 'wt3Overlay';
        ov.style.cssText = 'position:fixed;inset:0;z-index:90;display:none;pointer-events:none;';
        ov.innerHTML =
            '<div id="wt3Spotlight" style="position:fixed;border-radius:12px;border:3px solid #0075de;background:transparent;transition:all .2s ease;pointer-events:none;z-index:91;"></div>'
            + '<div id="wt3DimT" style="position:fixed;left:0;top:0;right:0;background:rgba(15,20,35,0.55);pointer-events:auto;z-index:90;display:none;"></div>'
            + '<div id="wt3DimB" style="position:fixed;left:0;bottom:0;right:0;background:rgba(15,20,35,0.55);pointer-events:auto;z-index:90;display:none;"></div>'
            + '<div id="wt3DimL" style="position:fixed;left:0;top:0;background:rgba(15,20,35,0.55);pointer-events:auto;z-index:90;display:none;"></div>'
            + '<div id="wt3DimR" style="position:fixed;right:0;top:0;background:rgba(15,20,35,0.55);pointer-events:auto;z-index:90;display:none;"></div>'
            + '<div id="wt3Tooltip" style="position:fixed;max-width:340px;background:#fff;border:1px solid #e6e6e6;border-radius:14px;padding:16px 18px;box-shadow:0 20px 50px rgba(0,0,0,0.3);z-index:92;pointer-events:none;">'
            + '<div id="wt3StepLabel" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#0075de;margin-bottom:6px;"></div>'
            + '<div id="wt3StepText" style="font-size:14px;line-height:1.5;color:#1f1f1f;"></div>'
            + '<button id="wt3Next" style="display:none;margin-top:12px;padding:8px 16px;font-size:12px;font-weight:600;color:#fff;background:#002D5E;border:0;border-radius:8px;cursor:pointer;pointer-events:auto;">Next</button>'
            + '</div>'
            + '<div id="wt3Done" style="display:none;position:fixed;inset:0;z-index:95;background:rgba(15,20,35,0.72);align-items:center;justify-content:center;pointer-events:auto;">'
            + '<div style="max-width:380px;width:calc(100% - 2rem);background:#fff;border-radius:20px;padding:32px 28px;text-align:center;box-shadow:0 30px 70px rgba(0,0,0,0.45);">'
            + '<div style="width:64px;height:64px;margin:0 auto 18px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e8f5ec;color:#1f6b3a;"><i data-lucide="check" style="width:34px;height:34px;"></i></div>'
            + '<div style="font-size:20px;font-weight:700;color:#1f1f1f;">Egg record saved!</div>'
            + '<div style="font-size:14px;color:#6B7280;margin-top:8px;">Your daily egg production for this cage slot is logged.</div>'
            + '<button id="wt3DoneBtn" style="margin-top:24px;padding:11px 28px;font-size:14px;font-weight:600;color:#fff;background:#002D5E;border:0;border-radius:10px;cursor:pointer;">Done</button>'
            + '</div>'
            + '</div>'
            + '<div id="wt3Skip" style="position:fixed;top:16px;right:16px;z-index:93;background:#fff;border:1px solid #e6e6e6;color:#615d59;font-size:13px;padding:8px 14px;border-radius:999px;cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,0.15);pointer-events:auto;">End tutorial</div>';
        document.body.appendChild(ov);
    }

    var spotlight = document.getElementById('wt3Spotlight');
    var tooltip = document.getElementById('wt3Tooltip');
    var skipBtn = document.getElementById('wt3Skip');
    var overlay = document.getElementById('wt3Overlay');
    var stepLabel = document.getElementById('wt3StepLabel');
    var stepText = document.getElementById('wt3StepText');
    var nextBtn = document.getElementById('wt3Next');
    var doneOverlay = document.getElementById('wt3Done');
    var doneBtn = document.getElementById('wt3DoneBtn');
    var dimT = document.getElementById('wt3DimT');
    var dimB = document.getElementById('wt3DimB');
    var dimL = document.getElementById('wt3DimL');
    var dimR = document.getElementById('wt3DimR');
    var dims = [dimT, dimB, dimL, dimR];

    // ── Steps ── manual:true = informational (Next button); else auto-advance.
    // interactive:true keeps the target clickable (4-bar dim with a hole).
    var steps = [
        {
            text: '<strong>Choose a cage.</strong><br>Click a <em>cage card</em> above (e.g. a cage with hens) to open its slot grid.',
            target: function() { return document.querySelector('.cage-overview-card'); },
            done: function() {
                return document.querySelector('.cage-grid:not(.hidden)') !== null;
            },
            interactive: true
        },
        {
            text: '<strong>Select the slot(s) to log.</strong><br>Click any cage <em>slot</em> to select it, or <em>click-and-drag</em> across several slots to select multiple at once. The same egg count will apply to all selected slots.<br><br>When you are done selecting, tap <em>Next</em>.',
            target: function() {
                // Highlight the whole currently-open cage grid so every slot is
                // visible and draggable for one-at-a-time or drag-multi select.
                return document.querySelector('.cage-grid:not(.hidden)');
            },
            done: function() { return false; },
            canProceed: function() {
                // At least one slot selected (selected cards get .ring-2).
                return document.querySelectorAll('.slot-card.ring-2').length > 0;
            },
            manual: true,
            interactive: true
        },
        {
            text: '<strong>Understand the form fields.</strong><br>'
                + '<em>Date</em> — the day the eggs were recorded (defaults to today).<br>'
                + '<em>Egg Count</em> — how many eggs were laid in the selected slot(s).<br>'
                + '<em>Hen Count</em> — hens present in the slot (read from the slot).<br>'
                + '<strong>HDEP</strong> — Hen-Day Egg Production = (eggs ÷ hens) × 100%, a measure of how many eggs each hen laid that day.<br>'
                + '<em>Notes</em> — optional comments (e.g. broken eggs).<br>'
                + '<em>Size Breakdown</em> (optional) — split the eggs by Small / Medium / Large / Jumbo; leave blank to skip.<br><br>Tap <em>Next</em> to continue.',
            target: function() {
                // Highlight only the field area (Date through Size Breakdown),
                // excluding the Save Record / Cancel buttons below.
                return document.getElementById('logEntryFields') || document.getElementById('eggCount');
            },
            done: function() { return false; },
            manual: true,
            interactive: true
        },
        {
            text: '<strong>Enter the egg count.</strong><br>Type the number of eggs laid for today.',
            target: function() { return document.getElementById('eggCount'); },
            done: function() {
                var e = document.getElementById('eggCount');
                var v = e ? parseInt(e.value, 10) : 0;
                return v > 0;
            },
            interactive: true
        },
        {
            text: '<strong>Save the record.</strong><br>If you picked one slot, click <em>Save Record</em>. If you picked multiple, a confirm popup will appear.',
            target: function() { return document.getElementById('saveBtn'); },
            done: function() {
                // Multi-slot: advances when the confirm modal opens.
                var m = document.getElementById('confirmMultiModal');
                if (m && m.style.display === 'flex') return true;
                // Single-slot: advances when the record is saved.
                return window.__wt3Saved === true;
            }
        },
        {
            text: '<strong>Confirm the save.</strong><br>Review the totals and click <em>Confirm Save</em> to record the eggs for the selected slot(s).',
            target: function() {
                var m = document.getElementById('confirmMultiModal');
                if (!m || m.style.display !== 'flex') return null;
                return m.querySelector('.relative') || m;
            },
            skipIf: function() {
                // Only relevant when a confirmation modal appears (multi-slot);
                // skip when saving a single slot (no popup).
                var m = document.getElementById('confirmMultiModal');
                return !(m && m.style.display === 'flex');
            },
            done: function() {
                return window.__wt3Confirmed === true;
            }
        }
    ];

    var idx = Math.min(startAt, steps.length - 1);
    var pollTimer = null;

    function positionOverlay() {
        var s = steps[idx];
        if (!s) return;
        if (typeof s.skipIf === 'function' && s.skipIf()) {
            idx++;
            if (idx >= steps.length) { outro(); return; }
            sessionStorage.setItem('wt3_step', String(idx));
            positionOverlay();
            return;
        }
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

        if (s.manual && !s.interactive) {
            dimT.style.display = 'block';
            dimT.style.left = '0px'; dimT.style.right = '0px'; dimT.style.top = '0px'; dimT.style.height = window.innerHeight + 'px';
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
        if (s.manual && typeof s.canProceed === 'function') {
            nextBtn.disabled = !s.canProceed();
            nextBtn.style.opacity = nextBtn.disabled ? '0.5' : '1';
            nextBtn.style.cursor = nextBtn.disabled ? 'not-allowed' : 'pointer';
        } else {
            nextBtn.disabled = false;
            nextBtn.style.opacity = '1';
            nextBtn.style.cursor = 'pointer';
        }
        var ttH = tooltip.offsetHeight;
        var ttTop = bottom + 14;
        if (ttTop + ttH > window.innerHeight - 20) { ttTop = Math.max(12, top - ttH - 14); }
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
            sessionStorage.setItem('wt3_step', String(idx));
            positionOverlay();
        }
    }

    function outro() {
        stopPolling();
        overlay.querySelectorAll('#wt3Spotlight, #wt3Tooltip, #wt3Skip').forEach(function(n) { n.style.display = 'none'; });
        try { window.lucide.createIcons(); } catch (e) {}
        doneOverlay.style.display = 'flex';
        // Tutorial completed naturally — clear session so a refresh doesn't
        // resume a finished walkthrough.
        sessionStorage.removeItem('wt3_active');
        sessionStorage.removeItem('wt3_step');
    }

    function finish() {
        stopPolling();
        overlay.style.display = 'none';
        skipBtn.style.display = 'none';
        doneOverlay.style.display = 'none';
        sessionStorage.removeItem('wt3_active');
        sessionStorage.removeItem('wt3_step');
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

    // Forward wheel scrolling so the user can scroll during the tutorial.
    document.addEventListener('wheel', function(e) {
        if (sessionStorage.getItem('wt3_active') !== '1') return;
        var sc = document.querySelector('.page-wrapper') || document.scrollingElement || document.documentElement;
        if (!sc) return;
        sc.scrollTop = Math.max(0, Math.min(sc.scrollHeight, sc.scrollTop + e.deltaY));
        e.preventDefault();
    }, { passive: false });

    // Detect save attempt. For a single slot this is the direct save; for a
    // multi-slot selection this fires when the confirmation popup is shown.
    // The tutorial session is cleared only at completion (outro/finish).
    var eggForm = document.getElementById('eggForm');
    if (eggForm && !eggForm.__wt3Bound) {
        eggForm.__wt3Bound = true;
        eggForm.addEventListener('submit', function() {
            window.__wt3Saved = true;
        });
    }

    // Multi-slot confirms via #confirmMultiModal → #confirmMultiSaveBtn →
    // executeMultiSave(). Hook that to mark the tutorial complete.
    var confirmMultiSave = document.getElementById('confirmMultiSaveBtn');
    if (confirmMultiSave && !confirmMultiSave.__wt3Bound) {
        confirmMultiSave.__wt3Bound = true;
        confirmMultiSave.addEventListener('click', function() {
            window.__wt3Confirmed = true;
            sessionStorage.removeItem('wt3_active');
            sessionStorage.removeItem('wt3_step');
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
