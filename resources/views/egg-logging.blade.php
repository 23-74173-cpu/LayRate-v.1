@extends('layouts.app')
@section('title', 'Egg Logging')

@section('content')
<main class="p-5 space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-medium text-[#333333]">Egg Logging</h1>
        <div class="flex items-center gap-2">
            <label class="text-xs text-[#6B7280]">Cage filter:</label>
            <select onchange="window.location.href = this.value ? '?cage_id=' + this.value : '?'"
                    class="border border-[#D9D9D9] rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                <option value="">All Cages</option>
                @foreach($cages as $c)
                <option value="{{ $c->id }}" {{ $cageFilter == $c->id ? 'selected' : '' }}>
                    {{ $c->cage_code }} — {{ $c->location ?: 'No location' }}
                </option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Today's Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4 text-center">
            <div class="text-2xl font-bold text-[#002D5E]">{{ $todayTotal }}</div>
            <div class="text-xs text-[#6B7280] mt-1">Eggs Today</div>
        </div>
        @foreach($cages as $c)
        @php $eggs = $todayByCage->get($c->cage_code, 0); @endphp
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4 text-center">
            <div class="text-2xl font-bold" style="color: {{ $c->color }}">{{ $eggs }}</div>
            <div class="text-xs text-[#6B7280] mt-1">{{ $c->cage_code }}</div>
        </div>
        @endforeach
    </div>

    {{-- Slot Cards Grid --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-medium text-[#333]">Select a Slot to Log</h2>
            <span class="text-xs text-[#9CA3AF]">{{ $cageSlots->count() }} slot{{ $cageSlots->count() !== 1 ? 's' : '' }}</span>
        </div>

        @if($cageSlots->isEmpty())
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-10 text-center text-sm text-[#9CA3AF]">
            No slots found for the selected filter.
        </div>
        @else
        <div class="space-y-2">
        @foreach($cageSlots->groupBy(fn($s) => $s->cage->cage_code) as $cageCode => $slotsInCage)
        @php
            $cage = $slotsInCage->first()->cage;
        @endphp
        <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
            {{-- Cage dropdown header --}}
            <div class="flex items-center justify-between px-4 py-2.5 cursor-pointer"
                 style="background: {{ $cage->color }}10; border-bottom: 1px solid {{ $cage->color }}30"
                 onclick="toggleEggCage(this)">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-semibold" style="color: {{ $cage->color }}">{{ $cage->cage_code }}</span>
                    <span class="text-xs text-[#6B7280]">{{ $cage->location ?: 'No location' }}</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded-full" style="background: {{ $cage->color }}15; color: {{ $cage->color }}">
                        {{ $slotsInCage->count() }} slot{{ $slotsInCage->count() !== 1 ? 's' : '' }}
                    </span>
                </div>
                <i data-lucide="chevron-down" class="w-4 h-4 text-[#6B7280] egg-cage-chevron transition-transform"></i>
            </div>

            {{-- Slots grid (collapsible) --}}
            <div class="egg-cage-slots hidden p-3">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                @foreach($slotsInCage as $slot)
                @php
                    $primaryHen = $slot->primaryHen();
                    $isSensor = $slot->has_sensor;
                @endphp
                <div class="slot-card bg-white rounded-lg border border-[#D9D9D9] p-4 cursor-pointer hover:ring-1 hover:ring-[#002D5E] transition-all relative"
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
                     onclick="selectSlot(this)">

                    @if($isSensor)
                    <div class="absolute top-2 right-2 flex items-center gap-0.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    </div>
                    @endif

                    <div class="text-xs font-semibold mb-1" style="color: {{ $cage->color }}">
                        Slot {{ $slot->row_number }}-{{ $slot->column_number }}
                    </div>

                    <div class="text-[10px] text-[#6B7280] mb-1">
                        {{ $slot->current_occupancy }}/{{ $cage->max_chickens_per_slot }} hens
                    </div>

                    @if($primaryHen)
                    <div class="text-[10px] text-[#333] mb-1">{{ $primaryHen->breed }}</div>
                    <div class="text-[10px] text-[#9CA3AF] mb-1">{{ $primaryHen->current_age_weeks }}w old</div>
                    @else
                    <div class="text-[10px] text-[#9CA3AF] mb-1">No hens</div>
                    @endif

                    <div class="mt-1.5 pt-1.5 border-t border-[#F0F0F0]">
                        <div class="text-[10px] text-[#9CA3AF]">Today: <span class="font-medium text-[#333]" id="slot-eggs-{{ $slot->id }}">{{ $slot->today_egg_count }}</span> eggs</div>
                    </div>

                    <div class="selected-indicator hidden absolute inset-0 rounded-lg border-2 border-[#002D5E] pointer-events-none"></div>
                </div>
                @endforeach
                </div>
            </div>
        </div>
        @endforeach
        </div>
        @endif
    </div>

    {{-- Log Entry Form --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-6">
        <h2 class="text-base font-medium text-[#333333] mb-5">Log Entry</h2>

        <div id="slotFormPlaceholder" class="text-center py-8 text-sm text-[#9CA3AF]">
            Click a slot card above to start logging.
        </div>

        <div id="slotForm" class="hidden">
            <form method="POST" action="{{ route('egg-logging.store') }}" id="eggForm">
                @csrf

                {{-- Selected slot info --}}
                <div id="selectedSlotBar" class="mb-4 p-3 rounded-lg border border-[#002D5E]/20 bg-[#002D5E]/5 flex items-center gap-4 text-xs">
                    <div>
                        <span class="text-[#9CA3AF]">Slot:</span>
                        <span id="formSlotLabel" class="font-semibold text-[#002D5E]"></span>
                    </div>
                    <div>
                        <span class="text-[#9CA3AF]">Breed:</span>
                        <span id="formBreed" class="text-[#333]"></span>
                    </div>
                    <div>
                        <span class="text-[#9CA3AF]">Age:</span>
                        <span id="formAge" class="text-[#333]"></span>
                    </div>
                    <div>
                        <span class="text-[#9CA3AF]"> Hens:</span>
                        <span id="formHenCount" class="text-[#333]"></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
                    {{-- Date --}}
                    <div class="mb-4">
                        <label class="block text-sm text-[#333333] mb-1.5">Date</label>
                        <input type="date" name="log_date" value="{{ now()->toDateString() }}" required
                               class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                    </div>

                    {{-- Slot (hidden field) --}}
                    <input type="hidden" name="cage_slot_id" id="cageSlotId" value="">

                    {{-- Egg Count --}}
                    <div class="mb-4">
                        <label class="block text-sm text-[#333333] mb-1.5">Egg Count</label>
                        <input type="number" name="egg_count" id="eggCount" min="0" required
                               oninput="computeHdep()"
                               class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                        <button type="button" id="overrideLabel" onclick="openOverrideModal()"
                                class="hidden mt-1.5 text-xs text-amber-700 flex items-center gap-1">
                            🔒 Sensor reading — click to override
                        </button>
                    </div>

                    {{-- Hen Count --}}
                    <div class="mb-4">
                        <label class="block text-sm text-[#333333] mb-1.5">Hen Count</label>
                        <input type="number" name="hen_count" id="henCount" min="1" value="1" required
                               oninput="computeHdep()"
                               class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                        <div id="hdepDisplay" class="mt-2 inline-block bg-[#F5F6F8] border border-[#D9D9D9] rounded-lg px-4 py-2 text-sm font-mono text-[#333333]">
                            HDEP: &nbsp;—
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div class="mb-5">
                        <label class="block text-sm text-[#333333] mb-1.5">Notes <span class="text-[#6B7280] text-xs">(optional)</span></label>
                        <textarea name="notes" rows="2" placeholder="e.g. 2 broken eggs, irregular lay in slot A2-3"
                                  class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E] resize-y"></textarea>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" onclick="clearSlotSelection()"
                            class="px-4 py-2 text-sm border border-[#D9D9D9] rounded-lg hover:bg-[#F5F6F8]">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-6 py-2.5 bg-[#002D5E] text-white rounded-lg text-sm hover:bg-[#001F42] transition-colors">
                        Save Record
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Recent Logs --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <div class="px-6 py-4 border-b border-[#D9D9D9]">
            <h2 class="text-base font-medium text-[#333333]">Recent Logs</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Date</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Cage</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Slot</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Eggs</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Hens</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">HDEP</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Logged By</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Notes</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Override</th>
                        @if(auth()->user()->role === 'admin')<th class="px-6 py-3"></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    @php
                        $cColor = match($log->cageSlot?->cage?->cage_code) {
                            'CAGE-A'=>'#2D7D46','CAGE-B'=>'#1D4E8F','CAGE-C'=>'#C2703E','CAGE-D'=>'#6B4C8A',default=>'#6B7280'
                        };
                    @endphp
                    <tr class="border-b border-[#F0F0F0] hover:bg-[#F5F6F8]">
                        <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ $log->log_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3 text-sm font-medium font-mono" style="color:{{ $cColor }}">{{ $log->cageSlot?->cage?->cage_code ?? '—' }}</td>
                        <td class="px-6 py-3 text-xs font-mono text-[#6B7280]">
                            @if($log->cageSlot)
                            {{ $log->cageSlot->row_number }}-{{ $log->cageSlot->column_number }}
                            @else — @endif
                        </td>
                        <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ $log->egg_count }}</td>
                        <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ $log->hen_count }}</td>
                        <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ number_format($log->hdep,1) }}%</td>
                        <td class="px-6 py-3 text-sm text-[#333333]">{{ $log->recorder?->name ?? 'Farm Operator' }}</td>
                        <td class="px-6 py-3 text-sm text-[#6B7280] max-w-[200px] truncate">{{ $log->notes ?? '—' }}</td>
                        <td class="px-6 py-3">
                            @if($log->overriddenBy)
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                                Manually overridden by {{ $log->overriddenBy->name }}
                            </span>
                            @else
                            <span class="text-xs text-[#9CA3AF]">—</span>
                            @endif
                        </td>
                        @if(auth()->user()->role === 'admin')
                        <td class="px-6 py-3">
                            <form method="POST" action="{{ route('egg-logging.destroy', $log) }}"
                                  onsubmit="return confirm('Delete this log?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-600">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="{{ auth()->user()->role === 'admin' ? 10 : 9 }}" class="px-6 py-8 text-center text-sm text-[#6B7280]">No logs yet. Select a slot and save the first record.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($logs->hasPages())
            <div class="px-6 py-3 border-t border-[#F0F0F0] flex items-center justify-between text-xs text-[#6B7280]">
                <span>Showing {{ $logs->firstItem() }}-{{ $logs->lastItem() }} of {{ $logs->total() }}</span>
                <div class="flex items-center gap-1">
                    @if($logs->onFirstPage())
                    <span class="px-2 py-1 text-[#9CA3AF]">‹ Prev</span>
                    @else
                    <a href="{{ $logs->previousPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">‹ Prev</a>
                    @endif
                    @foreach($logs->getUrlRange(1, $logs->lastPage()) as $page => $url)
                        @if($page == $logs->currentPage())
                        <span class="px-2 py-1 font-medium text-[#002D5E]">{{ $page }}</span>
                        @elseif($page >= $logs->currentPage() - 1 && $page <= $logs->currentPage() + 1)
                        <a href="{{ $url }}" class="px-2 py-1 hover:text-[#002D5E]">{{ $page }}</a>
                        @endif
                    @endforeach
                    @if($logs->hasMorePages())
                    <a href="{{ $logs->nextPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">Next ›</a>
                    @else
                    <span class="px-2 py-1 text-[#9CA3AF]">Next ›</span>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Sensor Override Modal --}}
    <div id="overrideModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/30">
        <div class="bg-white rounded-xl border border-[#D9D9D9] shadow-xl w-full max-w-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-medium">Override Sensor Reading</h2>
                <button onclick="closeOverrideModal()" class="text-[#6B7280] hover:text-[#333333]">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div id="overridePinSection">
                <label class="block text-sm text-[#333333] mb-1.5">Enter your Override PIN</label>
                <input type="text" id="overridePinInput" inputmode="numeric" maxlength="6"
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-2 focus:outline-none focus:border-[#002D5E]">
            </div>
            <div id="overridePasswordSection" class="hidden">
                <p class="text-xs text-[#6B7280] mb-2">No override PIN set — verify with your login password instead.</p>
                <input type="password" id="overridePasswordInput"
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-2 focus:outline-none focus:border-[#002D5E]">
            </div>
            <p id="overrideError" class="hidden text-[11px] text-red-500 mb-3"></p>
            <button type="button" onclick="submitOverride()" class="w-full bg-[#002D5E] text-white py-2.5 rounded-lg text-sm hover:bg-[#001F42]">
                Unlock Field
            </button>
        </div>
    </div>

</main>
@endsection

@push('scripts')
<script>
let currentSlotId = null;
let currentHasSensor = false;
let overrideVerified = false;

function selectSlot(card) {
    document.querySelectorAll('.slot-card').forEach(c => {
        c.classList.remove('ring-2', 'ring-[#002D5E]', 'ring-offset-1');
        c.querySelector('.selected-indicator')?.classList.add('hidden');
    });

    card.classList.add('ring-2', 'ring-[#002D5E]', 'ring-offset-1');
    card.querySelector('.selected-indicator')?.classList.remove('hidden');

    currentSlotId = parseInt(card.dataset.slotId);
    currentHasSensor = card.dataset.hasSensor === '1';

    document.getElementById('cageSlotId').value = currentSlotId;
    document.getElementById('formSlotLabel').textContent =
        card.dataset.cageCode + ' · Slot ' + card.dataset.row + '-' + card.dataset.col + ' (#' + card.dataset.slotNumber + ')';
    document.getElementById('formBreed').textContent = card.dataset.breed || '—';
    document.getElementById('formAge').textContent = card.dataset.age + 'w';
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
        c.classList.remove('ring-2', 'ring-[#002D5E]', 'ring-offset-1');
        c.querySelector('.selected-indicator')?.classList.add('hidden');
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
    el.className = 'mt-2 inline-block border rounded-lg px-4 py-2 text-sm font-mono '
        + (eggs > hens ? 'bg-red-50 border-red-200 text-red-700' : 'bg-[#F5F6F8] border-[#D9D9D9] text-[#333333]');
}

function openOverrideModal() {
    if (!currentSlotId) return;
    document.getElementById('overrideError').classList.add('hidden');
    document.getElementById('overridePinInput').value = '';
    document.getElementById('overrideModal').classList.remove('hidden');
}

function closeOverrideModal() {
    document.getElementById('overrideModal').classList.add('hidden');
}

function submitOverride() {
    const pin = document.getElementById('overridePinInput').value;
    const password = document.getElementById('overridePasswordInput').value;

    fetch('{{ route("egg-logging.verify-override") }}', {
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

function toggleEggCage(header) {
    const panel = header.closest('.bg-white').querySelector('.egg-cage-slots');
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
</script>
@endpush
