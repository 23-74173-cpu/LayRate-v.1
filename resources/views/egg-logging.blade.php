@extends('layouts.app')
@section('title', 'Egg Logging')

@section('content')
<main class="p-5 space-y-5">

    {{-- ── Date / Cage Filter ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
        <form method="GET" action="{{ route('egg-logging') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">DATE</label>
                <input type="date" name="date" value="{{ $date }}"
                       class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
            </div>
            <div>
                <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">CAGE</label>
                <select name="cage" class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#002D5E] min-w-40">
                    <option value="all" {{ $cageFilter === 'all' ? 'selected' : '' }}>All Cages</option>
                    @foreach($cages as $c)
                    <option value="{{ $c->cage_code }}" {{ $cageFilter === $c->cage_code ? 'selected' : '' }}>{{ $c->cage_code }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-[#002D5E] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#001F42]">Apply</button>
        </form>
    </div>

    {{-- ── Per-Slot Cards ── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($slots as $slot)
        @php $hen = $slot->hens->first(); @endphp
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium" style="color:{{ $slot->cage->color }}">{{ $slot->cage->cage_code }} · {{ $slot->label }}</span>
                @if($slot->has_sensor)
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">Sensor</span>
                @endif
            </div>
            <div class="text-xs text-[#6B7280] mb-3">{{ $hen?->breed ?? '—' }} · {{ $hen?->current_age_weeks ?? 0 }} wks · {{ $slot->current_occupancy }} hens</div>

            <form method="POST" action="{{ route('egg-logging.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="log_date" value="{{ $date }}">
                <input type="hidden" name="cage_slot_id" value="{{ $slot->id }}">
                <input type="hidden" name="hen_count" value="{{ $slot->current_occupancy }}">

                <div>
                    <label class="block text-xs text-[#333333] mb-1">Egg Count</label>
                    <input type="number" name="egg_count" id="eggCount-{{ $slot->id }}" min="0" required
                           value="{{ $slot->has_sensor && $date === now()->toDateString() ? $slot->today_egg_count : old('egg_count', '') }}"
                           {{ $slot->has_sensor && $date === now()->toDateString() ? 'readonly' : '' }}
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                    @if($slot->has_sensor && $date === now()->toDateString())
                    <button type="button" onclick="openOverrideModal({{ $slot->id }})" class="mt-1 text-xs text-amber-700 flex items-center gap-1">
                        🔒 Sensor reading — click to override
                    </button>
                    @endif
                </div>

                <div>
                    <label class="block text-xs text-[#333333] mb-1">Notes (optional)</label>
                    <input type="text" name="notes" class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>

                <button type="submit" class="w-full bg-[#002D5E] text-white py-2 rounded-lg text-sm hover:bg-[#001F42]">Save</button>
            </form>
        </div>
        @empty
        <div class="md:col-span-2 lg:col-span-3 bg-white rounded-lg border border-[#D9D9D9] p-8 text-center text-sm text-[#6B7280]">
            No occupied slots match this filter. Use Bulk Add Chickens from Cage Management to assign flocks to slots first.
        </div>
        @endforelse
    </div>

    {{-- ── Recent Logs ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <div class="px-6 py-4 border-b border-[#D9D9D9]">
            <h2 class="text-base font-medium text-[#333333]">Recent Logs</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Date</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Slot</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Eggs</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Hens</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">HDEP</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Logged By</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Notes</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Override</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
                        <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ $log->log_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3 text-sm font-medium font-mono" style="color:{{ $log->cageSlot->cage->color }}">{{ $log->cageSlot->cage->cage_code }} · {{ $log->cageSlot->label }}</td>
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
                            <span class="text-xs text-[#6B7280]">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            <form method="POST" action="{{ route('egg-logging.destroy', $log) }}" onsubmit="return confirm('Delete this log?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-600">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-6 py-8 text-center text-sm text-[#6B7280]">No logs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
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
lucide.createIcons();

let currentSensorSlotId = null;

function openOverrideModal(slotId) {
    currentSensorSlotId = slotId;
    document.getElementById('overrideError').classList.add('hidden');
    document.getElementById('overridePinInput').value = '';
    document.getElementById('overridePinSection').classList.remove('hidden');
    document.getElementById('overridePasswordSection').classList.add('hidden');
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
        body: JSON.stringify({ slot_id: currentSensorSlotId, pin: pin, password: password }),
    })
    .then(r => r.json().then(body => ({ status: r.status, body })))
    .then(({ status, body }) => {
        if (status === 200 && body.ok) {
            document.getElementById('eggCount-' + currentSensorSlotId).readOnly = false;
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
</script>
@endpush
