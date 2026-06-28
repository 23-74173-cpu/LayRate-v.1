@extends('layouts.app')
@section('title', 'Egg Logging')

@section('content')
<main class="p-5 space-y-5">

    {{-- ── Log Entry Form ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-6">
        <h2 class="text-base font-medium text-[#333333] mb-5">Log Entry Form</h2>
        <form method="POST" action="{{ route('egg-logging.store') }}" id="eggForm">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
                {{-- Date --}}
                <div class="mb-4">
                    <label class="block text-sm text-[#333333] mb-1.5">Date</label>
                    <input type="date" name="log_date" value="{{ now()->toDateString() }}" required
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>

                {{-- Cage --}}
                <div class="mb-4">
                    <label class="block text-sm text-[#333333] mb-1.5">Cage</label>
                    <select name="cage_id" id="cageSelect" onchange="updateCageInfo(this)"
                            class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                        @foreach($cages as $cage)
                        <option value="{{ $cage->id }}"
                                data-hens="{{ $cage->capacity }}"
                                data-hdep="{{ number_format($cage->latestProduction?->hdep ?? 0, 1) }}"
                                data-age="{{ $cage->hens->first()?->current_age_weeks ?? 0 }} weeks"
                                data-breed="{{ $cage->hens->first()?->breed ?? '—' }}"
                                data-has-sensor="{{ $cage->has_sensor ? 1 : 0 }}"
                                data-today-egg-count="{{ $cage->today_egg_count }}">
                            {{ $cage->cage_code }} — {{ $cage->hens->first()?->breed ?? '—' }}
                        </option>
                        @endforeach
                    </select>
                    {{-- Cage info bar --}}
                    <div id="cageInfoBar" class="mt-2 flex flex-wrap gap-4 text-xs text-[#6B7280] bg-[#F5F6F8] border border-[#D9D9D9] rounded-lg px-4 py-2">
                        <span>Flock age: <span id="cageAge" class="text-[#333333]">—</span></span>
                        <span>Last HDEP: <span id="cageHdep" class="text-[#333333]">—</span></span>
                        <span>Hens: <span id="cageHens" class="text-[#333333]">—</span></span>
                    </div>
                </div>

                {{-- Egg Count --}}
                <div class="mb-4">
                    <label class="block text-sm text-[#333333] mb-1.5">Egg Count (IR sensor auto-count or manual entry)</label>
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
                    <input type="number" name="hen_count" id="henCount" min="1" value="120" required
                           oninput="computeHdep()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                    <div id="hdepDisplay" class="mt-2 inline-block bg-[#F5F6F8] border border-[#D9D9D9] rounded-lg px-4 py-2 text-sm font-mono text-[#333333]">
                        HDEP: &nbsp;—
                    </div>
                </div>

                {{-- Logged By --}}
                <div class="mb-4">
                    <label class="block text-sm text-[#333333] mb-1.5">Logged By</label>
                    <input type="text" name="logged_by" value="Farm Operator"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>

                {{-- Notes --}}
                <div class="mb-5">
                    <label class="block text-sm text-[#333333] mb-1.5">Notes
                        <span class="text-[#6B7280] text-xs">(optional)</span>
                    </label>
                    <textarea name="notes" rows="3" placeholder="e.g. 2 broken eggs, 1 hen showing signs of illness in slot B3"
                              class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E] resize-y"></textarea>
                </div>
            </div>

            <button type="submit" class="bg-[#002D5E] text-white px-6 py-2.5 rounded-lg text-sm hover:bg-[#001F42] transition-colors">
                Save Record
            </button>
        </form>
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
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Cage</th>
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
                    @php
                        $cColor = match($log->cage->cage_code) {
                            'CAGE-A'=>'#2D7D46','CAGE-B'=>'#1D4E8F','CAGE-C'=>'#C2703E','CAGE-D'=>'#6B4C8A',default=>'#6B7280'
                        };
                    @endphp
                    <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
                        <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ $log->log_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3 text-sm font-medium font-mono" style="color:{{ $cColor }}">{{ $log->cage->cage_code }}</td>
                        <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ $log->egg_count }}</td>
                        <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ $log->hen_count }}</td>
                        <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ number_format($log->hdep,1) }}%</td>
                        <td class="px-6 py-3 text-sm text-[#333333]">Farm Operator</td>
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
                            <form method="POST" action="{{ route('egg-logging.destroy', $log) }}"
                                  onsubmit="return confirm('Delete this log?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-600">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-6 py-8 text-center text-sm text-[#6B7280]">No logs yet. Save the first record above.</td></tr>
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

let currentSensorCageId = null;

window.addEventListener('DOMContentLoaded', () => updateCageInfo(document.getElementById('cageSelect')));

function updateCageInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('cageAge').textContent  = opt.dataset.age  || '—';
    document.getElementById('cageHdep').textContent = (opt.dataset.hdep || '—') + '%';
    document.getElementById('cageHens').textContent = opt.dataset.hens || '—';
    document.getElementById('henCount').value = opt.dataset.hens || 120;

    const eggInput      = document.getElementById('eggCount');
    const overrideLabel = document.getElementById('overrideLabel');
    const hasSensor      = opt.dataset.hasSensor === '1';

    if (hasSensor) {
        eggInput.value    = opt.dataset.todayEggCount || 0;
        eggInput.readOnly = true;
        overrideLabel.classList.remove('hidden');
        currentSensorCageId = opt.value;
    } else {
        eggInput.readOnly = false;
        overrideLabel.classList.add('hidden');
        currentSensorCageId = null;
    }

    computeHdep();
}

function computeHdep() {
    const eggs = parseInt(document.getElementById('eggCount').value) || 0;
    const hens = parseInt(document.getElementById('henCount').value) || 1;
    const hdep = ((eggs / hens) * 100).toFixed(1);
    const el   = document.getElementById('hdepDisplay');
    el.textContent = 'HDEP:  ' + hdep + '%';
    el.className = 'mt-2 inline-block border rounded-lg px-4 py-2 text-sm font-mono '
        + (eggs > hens ? 'bg-red-50 border-red-200 text-red-700' : 'bg-[#F5F6F8] border-[#D9D9D9] text-[#333333]');
}

function openOverrideModal() {
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
        body: JSON.stringify({ cage_id: currentSensorCageId, pin: pin, password: password }),
    })
    .then(r => r.json().then(body => ({ status: r.status, body })))
    .then(({ status, body }) => {
        if (status === 200 && body.ok) {
            document.getElementById('eggCount').readOnly = false;
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
