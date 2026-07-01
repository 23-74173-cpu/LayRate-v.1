@extends('layouts.app')
@section('title', 'Egg Logging')

@section('content')
<div class="space-y-5">

    <x-page-header title="Egg Logging" subtitle="Log daily egg production per cage slot" />

    @include('eggs._tabs', ['activeTab' => 'logging'])

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div class="flex items-center gap-4">
            <span class="text-sm font-medium" style="color: #31302e;">
                Today: <strong style="color: #1f1f1f;">{{ number_format($todayTotal) }}</strong> eggs
            </span>
            <div class="flex items-center gap-2">
                <label class="text-xs" style="color: #615d59;">Cage:</label>
                <select onchange="window.location.href = this.value ? '?cage_id=' + this.value : '?'"
                        class="border rounded-lg px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                        style="border-color: #e6e6e6; color: #1f1f1f;">
                    <option value="">All Cages</option>
                    @foreach($cages as $c)
                    <option value="{{ $c->id }}" {{ $cageFilter == $c->id ? 'selected' : '' }}>
                        {{ $c->cage_code }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- ── 2-Column Layout: Slot Grid + Sticky Form ── --}}
    <div class="flex flex-col lg:flex-row gap-6">

        {{-- ── LEFT: Slot Grid (~55%, scrollable on desktop) ── --}}
        <div class="lg:w-[55%]">
            <h2 class="text-[22px] font-bold leading-[1.27] tracking-[-0.25px] mb-4" style="color: #1f1f1f;">Select a Slot to Log</h2>

            @if($cageSlots->isEmpty())
            <div class="rounded-xl border p-10 text-center text-sm" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
                No slots found for the selected filter.
            </div>
            @else
            <div class="rounded-xl border overflow-hidden" style="background-color: #ffffff; border-color: #e6e6e6;">
                <div class="overflow-y-auto" style="max-height: 320px; box-shadow: inset 0 -12px 12px -6px rgba(0,0,0,0.06);">
                    <div class="space-y-3 p-3">
                    @foreach($cageSlots->groupBy(fn($s) => $s->cage->cage_code) as $cageCode => $slotsInCage)
                    @php
                        $cage = $slotsInCage->first()->cage;
                    @endphp
                    <div class="rounded-lg border overflow-hidden" style="background-color: #ffffff; border-color: #e6e6e6;">
                        {{-- Cage header (collapsible) --}}
                        <button type="button"
                                class="flex items-center justify-between w-full px-4 py-3 text-left transition-colors"
                                style="background-color: {{ $cage->colorSoft }};"
                                onclick="toggleEggCage(this)">
                            <div class="flex items-center gap-3">
                                <x-cage-color :cage="$cage" />
                                <span class="text-xs" style="color: #615d59;">{{ $cage->location ?: 'No location' }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: {{ $cage->colorSoft }}; color: {{ $cage->color }};">
                                    {{ $slotsInCage->count() }} slot{{ $slotsInCage->count() !== 1 ? 's' : '' }}
                                </span>
                            </div>
                            <i data-lucide="chevron-down" class="w-4 h-4 egg-cage-chevron transition-transform" style="color: #615d59;"></i>
                        </button>

                        {{-- Slots grid --}}
                        <div class="egg-cage-slots hidden p-3">
                            <div class="grid grid-cols-4 sm:grid-cols-5 gap-2">
                            @foreach($slotsInCage as $slot)
                            @php
                                $primaryHen = $slot->primaryHen();
                                $isSensor = $slot->hasBreakbeam();
                                $isSelected = isset($selectedSlotId) && $selectedSlotId == $slot->id;
                            @endphp
                            <button type="button"
                                    class="slot-card flex flex-col items-center justify-center w-full aspect-square rounded-xl border transition-all relative cursor-pointer hover:scale-[1.02]"
                                    style="background-color: {{ $isSelected ? $cage->colorSoft : '#ffffff' }}; border-color: {{ $isSelected ? $cage->color : '#e6e6e6' }}; {{ $isSelected ? 'border-width: 2px;' : '' }}"
                                    data-slot-id="{{ $slot->id }}"
                                    data-cage-id="{{ $cage->id }}"
                                    data-cage-code="{{ $cage->cage_code }}"
                                    data-slot-number="{{ $slot->slot_number }}"
                                    data-row="{{ $slot->row_number }}"
                                    data-col="{{ $slot->column_number }}"
                                    data-hens="{{ $slot->current_occupancy }}"
                                    data-breed="{{ $primaryHen?->breed ?? '—' }}"
                                    data-age="{{ $primaryHen?->current_age_weeks ?? 0 }}"
                                    data-has-sensor="{{ $isSensor ? 1 : 0 }}"
                                    data-today-eggs="{{ $slot->today_egg_count }}"
                                    onclick="selectSlot(this)"
                                    aria-label="{{ $cage->cage_code }} slot {{ $slot->row_number }}-{{ $slot->column_number }}, {{ $slot->current_occupancy }} hens"
                                    tabindex="0"
                                    onkeydown="if(event.key==='Enter'||event.key===' ') selectSlot(this)">

                                @if($isSensor)
                                <span class="absolute top-1 right-1 w-2 h-2 rounded-full" style="background-color: #0075de;"></span>
                                @endif

                                @if($slot->current_occupancy === 0)
                                <span class="text-xs" style="color: #a39e98;">—</span>
                                @else
                                <span class="text-sm font-semibold" style="color: {{ $slot->current_occupancy >= $cage->max_chickens_per_slot ? '#9b1c24' : '#1f1f1f' }}">
                                    {{ $slot->current_occupancy }}
                                </span>
                                @endif
                            </button>
                            @endforeach
                            </div>
                        </div>
                    </div>
                    @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- ── RIGHT: Log Entry Form (~45%, sticky on desktop) ── --}}
        <div class="lg:w-[45%]">
            <div class="lg:sticky lg:top-6 rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
                <h3 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px] mb-4" style="color: #1f1f1f;">Log Entry</h3>

                {{-- Empty state --}}
                <div id="slotFormPlaceholder" class="text-center py-10 text-sm" style="color: #a39e98;">
                    <i data-lucide="mouse-pointer-2" class="w-6 h-6 mx-auto mb-2" style="color: #d1d5db;"></i>
                    Click a slot card to start logging.
                </div>

                {{-- Active form --}}
                <div id="slotForm" class="hidden">
                    <form method="POST" action="{{ route('eggs.logging.store') }}" id="eggForm" onsubmit="loadingButton(this.querySelector('button[type=submit]'))">
                        @csrf

                        {{-- Selected slot info bar --}}
                        <div id="selectedSlotBar" class="mb-4 p-3 rounded-lg flex items-center flex-wrap gap-3 text-xs" style="background-color: #f6f5f4;">
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
                                       oninput="computeHdep()"
                                       class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                       style="border-color: #e6e6e6; color: #1f1f1f;">
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
                                <div id="hdepDisplay" class="mt-2 inline-block border rounded-lg px-3 py-1.5 text-sm font-mono" style="background-color: #f6f5f4; border-color: #e6e6e6; color: #1f1f1f;">
                                    HDEP: —
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

                        <div class="flex items-center gap-3 mt-5">
                            <button type="button" onclick="clearSlotSelection()"
                                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                                    style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                                    onmouseover="this.style.backgroundColor='#f6f5f4'"
                                    onmouseout="this.style.backgroundColor='transparent'">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-2 text-sm font-medium rounded-full text-white transition-opacity"
                                    style="background-color: #0075de;"
                                    onmouseover="this.style.opacity='0.85'"
                                    onmouseout="this.style.opacity='1'">
                                Save Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Recent Logs (collapsed by default) ── --}}
    <div class="rounded-xl border" style="background-color: #ffffff; border-color: #e6e6e6;">
        <button type="button"
                onclick="document.getElementById('recent-logs-body').classList.toggle('hidden'); this.querySelector('.chevron').classList.toggle('rotate-180');"
                class="flex items-center justify-between w-full p-6 text-left transition-colors"
                onmouseover="this.style.backgroundColor='#f6f5f4'"
                onmouseout="this.style.backgroundColor='transparent'">
            <h3 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Recent Logs</h3>
            <i data-lucide="chevron-down" class="w-5 h-5 chevron transition-transform" style="color: #615d59;"></i>
        </button>
        <div id="recent-logs-body" class="hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="background-color: #f6f5f4; border-color: #e6e6e6;">
                            <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Date</th>
                            <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Cage</th>
                            <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Slot</th>
                            <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Eggs</th>
                            <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Hens</th>
                            <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">HDEP</th>
                            <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Logged By</th>
                            <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Notes</th>
                            <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Override</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr class="border-b hover:bg-black/[0.02] transition-colors" style="border-color: #e6e6e6;">
                            <td class="px-6 py-3 text-sm font-mono" style="color: #1f1f1f;">{{ $log->log_date->format('Y-m-d') }}</td>
                            <td class="px-6 py-3 text-sm font-semibold font-mono" style="color: {{ $log->cageSlot?->cage?->color ?? '#6B7280' }}">{{ $log->cageSlot?->cage?->cage_code ?? '—' }}</td>
                            <td class="px-6 py-3 text-xs font-mono" style="color: #615d59;">
                                @if($log->cageSlot){{ $log->cageSlot->row_number }}-{{ $log->cageSlot->column_number }}@else — @endif
                            </td>
                            <td class="px-6 py-3 text-sm font-mono" style="color: #1f1f1f;">{{ $log->egg_count }}</td>
                            <td class="px-6 py-3 text-sm font-mono" style="color: #1f1f1f;">{{ $log->hen_count }}</td>
                            <td class="px-6 py-3 text-sm font-mono" style="color: #1f1f1f;">{{ number_format($log->hdep,1) }}%</td>
                            <td class="px-6 py-3 text-sm" style="color: #31302e;">{{ $log->recorder?->name ?? 'Farm Operator' }}</td>
                            <td class="px-6 py-3 text-sm max-w-[200px] truncate" style="color: #615d59;">{{ $log->notes ?? '—' }}</td>
                            <td class="px-6 py-3">
                                @if($log->overriddenBy)
                                <x-status-badge status="Watch" type="general" />
                                @else
                                <span class="text-xs" style="color: #a39e98;">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-1">
                                    <button onclick="openEditLog({{ $log->id }}, '{{ $log->log_date->format('Y-m-d') }}', {{ $log->egg_count }}, {{ $log->hen_count }}, '{{ addslashes($log->notes ?? '') }}', {{ $log->cage_slot_id }})"
                                            class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #a39e98;" aria-label="Edit log">
                                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                    </button>
                                    @if(auth()->user()->role === 'admin')
                                    <form method="POST" action="{{ route('eggs.logging.destroy', $log) }}"
                                          data-confirm="Delete this log?" data-confirm-action="Delete">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-full hover:bg-red-50 transition-colors" style="color: #a39e98;" aria-label="Delete log">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="10" class="px-6 py-10 text-center text-sm" style="color: #a39e98;">No logs yet. Select a slot and save the first record.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-paginator :paginator="$logs" />
        </div>
    </div>

    {{-- ── Edit Log Modal ── --}}
    <div id="editLogModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" role="dialog" aria-modal="true">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEditLogModal()"></div>
        <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Edit Production Log</h2>
                <button onclick="closeEditLogModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>

            <form id="editLogForm" method="POST" onsubmit="loadingButton(this.querySelector('button[type=submit]'))">
                @csrf @method('PUT')

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Date</label>
                        <input type="date" name="log_date" id="editLogDate" required
                               class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                               style="border-color: #e6e6e6; color: #1f1f1f;">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Egg Count</label>
                        <input type="number" name="egg_count" id="editEggCount" min="0" required
                               oninput="editComputeHdep()"
                               class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                               style="border-color: #e6e6e6; color: #1f1f1f;">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Hen Count <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(edit if the original was wrong)</span></label>
                        <input type="number" name="hen_count" id="editHenCount" min="1" required
                               oninput="editComputeHdep()"
                               class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                               style="border-color: #e6e6e6; color: #1f1f1f;">
                        <div id="editHdepDisplay" class="mt-2 inline-block border rounded-lg px-3 py-1.5 text-sm font-mono" style="background-color: #f6f5f4; border-color: #e6e6e6; color: #1f1f1f;">
                            HDEP: —
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Notes <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span></label>
                        <textarea name="notes" id="editNotes" rows="2"
                                  class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1 resize-y"
                                  style="border-color: #e6e6e6; color: #1f1f1f;"></textarea>
                    </div>
                </div>

                <div class="flex gap-3 mt-5">
                    <button type="button" onclick="closeEditLogModal()"
                            class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                            style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                            onmouseover="this.style.backgroundColor='#f6f5f4'"
                            onmouseout="this.style.backgroundColor='transparent'">
                        Cancel
                    </button>
                    <button type="submit"
                            class="flex-1 py-2.5 text-sm font-medium rounded-full text-white transition-opacity"
                            style="background-color: #0075de;"
                            onmouseover="this.style.opacity='0.85'"
                            onmouseout="this.style.opacity='1'">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Sensor Override Modal ── --}}
    <div id="overrideModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" role="dialog" aria-modal="true">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeOverrideModal()"></div>
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
            <button type="button" onclick="submitOverride()"
                    class="w-full py-2.5 text-sm font-medium rounded-full text-white transition-opacity"
                    style="background-color: #0075de;"
                    onmouseover="this.style.opacity='0.85'"
                    onmouseout="this.style.opacity='1'">
                Unlock Field
            </button>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
let currentSlotId = null;
let currentHasSensor = false;
let overrideVerified = false;

function selectSlot(card) {
    document.querySelectorAll('.slot-card').forEach(c => {
        c.style.borderColor = '#e6e6e6';
        c.style.borderWidth = '1px';
        c.style.backgroundColor = '#ffffff';
    });

    const cageColor = card.dataset.cageCode;
    const cageColors = { 'CAGE-A': '#2D7D46', 'CAGE-B': '#1D4E8F', 'CAGE-C': '#C2703E', 'CAGE-D': '#6B4C8A' };
    const softColors = { 'CAGE-A': '#d6f0e3', 'CAGE-B': '#dcebfa', 'CAGE-C': '#fae3d0', 'CAGE-D': '#e9e0f5' };
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
    document.getElementById('eggCount').value = currentHasSensor ? (card.dataset.todayEggs || 0) : '';

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
}

function clearSlotSelection() {
    document.querySelectorAll('.slot-card').forEach(c => {
        c.style.borderColor = '#e6e6e6';
        c.style.borderWidth = '1px';
        c.style.backgroundColor = '#ffffff';
    });
    currentSlotId = null;
    overrideVerified = false;
    document.getElementById('slotFormPlaceholder').classList.remove('hidden');
    document.getElementById('slotForm').classList.add('hidden');
}

function computeHdep() {
    const eggs = parseInt(document.getElementById('eggCount').value) || 0;
    const hens = parseInt(document.getElementById('henCount').value) || 1;
    const hdep = ((eggs / hens) * 100).toFixed(1);
    const el = document.getElementById('hdepDisplay');
    el.textContent = 'HDEP:  ' + hdep + '%';
    el.style.backgroundColor = eggs > hens ? '#fbe4e6' : '#f6f5f4';
    el.style.borderColor = eggs > hens ? '#f3cdd0' : '#e6e6e6';
    el.style.color = eggs > hens ? '#9b1c24' : '#1f1f1f';
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
                alert('No override PIN set yet — please set one in Account Settings.');
            }
        } else {
            const errEl = document.getElementById('overrideError');
            errEl.textContent = body.error || 'Verification failed.';
            errEl.classList.remove('hidden');
            const noPinYet = (body.error || '').includes('password');
            document.getElementById('overridePinSection').classList.toggle('hidden', noPinYet);
            document.getElementById('overridePasswordSection').classList.toggle('hidden', !noPinYet);
        }
    });
}

// ── Edit Log Modal ──────────────────────────────────────
function openEditLog(id, date, eggCount, henCount, notes, cageSlotId) {
    document.getElementById('editLogForm').action = '/eggs/logging/' + id;
    document.getElementById('editLogDate').value = date;
    document.getElementById('editEggCount').value = eggCount;
    document.getElementById('editHenCount').value = henCount;
    document.getElementById('editNotes').value = notes || '';
    document.getElementById('editLogModal').style.display = 'flex';
    editComputeHdep();
    lucide.createIcons();
}

function closeEditLogModal() {
    document.getElementById('editLogModal').style.display = 'none';
}

function editComputeHdep() {
    const eggs = parseInt(document.getElementById('editEggCount').value) || 0;
    const hens = parseInt(document.getElementById('editHenCount').value) || 1;
    const hdep = ((eggs / hens) * 100).toFixed(1);
    const el = document.getElementById('editHdepDisplay');
    el.textContent = 'HDEP:  ' + hdep + '%';
    el.style.backgroundColor = eggs > hens ? '#fbe4e6' : '#f6f5f4';
    el.style.borderColor = eggs > hens ? '#f3cdd0' : '#e6e6e6';
    el.style.color = eggs > hens ? '#9b1c24' : '#1f1f1f';
}

function toggleEggCage(header) {
    const parent = header.closest('.rounded-xl');
    const panel = parent.querySelector('.egg-cage-slots');
    const chevron = header.querySelector('.egg-cage-chevron');
    if (panel.classList.contains('hidden')) {
        panel.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
    } else {
        panel.classList.add('hidden');
        chevron.style.transform = '';
    }
    lucide.createIcons();
}

// Keyboard support for slot cards (bind once)
(function() {
    var bound = false;
    document.addEventListener('turbo:load', function() {
        if (!bound) {
            bound = true;
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeOverrideModal();
                    closeEditLogModal();
                }
            });
        }
    });
})();
</script>
@endpush
