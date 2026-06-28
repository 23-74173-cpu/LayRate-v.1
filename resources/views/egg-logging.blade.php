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
                                data-breed="{{ $cage->hens->first()?->breed ?? '—' }}">
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
                    <tr><td colspan="8" class="px-6 py-8 text-center text-sm text-[#6B7280]">No logs yet. Save the first record above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</main>
@endsection

@push('scripts')
<script>
lucide.createIcons();

// Populate cage info bar on load
window.addEventListener('DOMContentLoaded', () => updateCageInfo(document.getElementById('cageSelect')));

function updateCageInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('cageAge').textContent  = opt.dataset.age  || '—';
    document.getElementById('cageHdep').textContent = (opt.dataset.hdep || '—') + '%';
    document.getElementById('cageHens').textContent = opt.dataset.hens || '—';
    document.getElementById('henCount').value = opt.dataset.hens || 120;
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
</script>
@endpush
