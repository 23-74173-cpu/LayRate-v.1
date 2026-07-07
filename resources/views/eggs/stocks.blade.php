@extends('layouts.app')
@section('title', 'Egg Stocks')

@section('content')
<div class="space-y-5">

    <x-page-header title="Egg Stocks" subtitle="Track harvested egg inventory by size and freshness" />

    @include('eggs._tabs', ['activeTab' => 'stocks'])

    <turbo-frame id="eggs-stocks-live-data" src="{{ route('eggs.stocks.live-data') }}" loading="lazy" target="_top">
        @include('eggs.stocks._live-data-skeleton')
    </turbo-frame>

    {{-- ── Header ── --}}
    <div class="flex items-center justify-between">
        <button onclick="document.getElementById('addStockModal').classList.remove('hidden')"
                class="flex items-center gap-2 bg-[#002D5E] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#001F42] transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Add Stock
        </button>
    </div>

</div>

{{-- Add Stock Modal --}}
<div id="addStockModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeAddStockModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Add Stock Batch</h2>
            <button onclick="closeAddStockModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>

        <form id="addStockForm">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">EGG SIZE</label>
                    <select name="egg_size" required
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">Select size…</option>
                        <option value="small">Small</option>
                        <option value="medium">Medium</option>
                        <option value="large">Large</option>
                        <option value="jumbo">Jumbo</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">EGG COUNT</label>
                    <input type="number" name="count" min="1" required
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">HARVESTED DATE</label>
                    <input type="date" name="harvested_date" value="{{ now()->toDateString() }}" required
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">SOURCE CAGE</label>
                    <select name="cage_id" id="stockCageSelect" required
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">Select cage…</option>
                        @foreach($cages as $cage)
                        <option value="{{ $cage->id }}">{{ $cage->cage_code }} — {{ $cage->formatted_location }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">PRODUCTION LOG <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span></label>
                    <select name="source_production_log_id" id="stockLogSelect"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">Select log…</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeAddStockModal()"
                        class="flex-1 border border-[#D9D9D9] text-[#6B7280] py-2.5 rounded-lg text-sm">Cancel</button>
                <button type="submit" id="addStockBtn"
                        class="flex-1 bg-[#002D5E] text-white py-2.5 rounded-lg text-sm">Add Stock</button>
            </div>
        </form>
    </div>
</div>
{{-- Edit Stock Modal --}}
<div id="editStockModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEditStockModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Edit Stock Batch</h2>
            <button onclick="closeEditStockModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>

        <form id="editStockForm" method="POST" onsubmit="loadingButton(this.querySelector('button[type=submit]'))">
            @csrf @method('PUT')

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">EGG SIZE</label>
                    <select name="egg_size" id="editEggSize" required
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="small">Small</option>
                        <option value="medium">Medium</option>
                        <option value="large">Large</option>
                        <option value="jumbo">Jumbo</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">EGG COUNT</label>
                    <input type="number" name="count" id="editEggCount" min="1" required
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">HARVESTED DATE</label>
                    <input type="date" name="harvested_date" id="editHarvestedDate" required
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeEditStockModal()"
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
@endsection

@push('scripts')
<script>
(function() {
    @php
        $cageLogsJson = $productionLogs->map(function($logs) {
            return $logs->map(function($log) {
                return [
                    'id' => $log->id,
                    'date' => $log->log_date->toDateString(),
                    'cage_code' => $log->cageSlot?->cage?->cage_code,
                ];
            })->values();
        });
    @endphp
    var cageLogs = @json($cageLogsJson);

    function closeAddStockModal() {
        document.getElementById('addStockModal').classList.add('hidden');
    }

    function openEditStock(id, eggSize, count, harvestedDate) {
        document.getElementById('editStockForm').action = '/eggs/stocks/' + id;
        document.getElementById('editEggSize').value = eggSize;
        document.getElementById('editEggCount').value = count;
        document.getElementById('editHarvestedDate').value = harvestedDate;
        document.getElementById('editStockModal').classList.remove('hidden');
        lucide.createIcons();
    }

    function closeEditStockModal() {
        document.getElementById('editStockModal').classList.add('hidden');
    }

    function updateLogSelect(cageId) {
        var select = document.getElementById('stockLogSelect');
        select.innerHTML = '<option value="">Select log…</option>';
        if (!cageId || !cageLogs[cageId]) return;
        cageLogs[cageId].forEach(function(log) {
            var opt = document.createElement('option');
            opt.value = log.id;
            opt.textContent = log.date;
            select.appendChild(opt);
        });
    }

    document.addEventListener('turbo:load', function() {
        var cageSelect = document.getElementById('stockCageSelect');
        if (cageSelect) {
            cageSelect.addEventListener('change', function() {
                updateLogSelect(this.value);
            });
        }

        var form = document.getElementById('addStockForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                var btn = document.getElementById('addStockBtn');
                var originalLabel = btn.textContent;
                loadingButton(btn, 'Adding\u2026');

                var csrf = document.querySelector('meta[name="csrf-token"]').content;
                var formData = new FormData(form);
                var body = {};
                formData.forEach(function(val, key) { body[key] = val; });

                fetch('/eggs/stocks', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(body),
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        closeAddStockModal();
                        form.reset();
                        Turbo.visit(window.location.href, { action: 'replace' });
                        return;
                    }
                    btn.disabled = false;
                    btn.textContent = originalLabel;
                })
                .catch(function() {
                    btn.disabled = false;
                    btn.textContent = originalLabel;
                });
            });
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditStockModal();
        }
    });
})();
</script>
@endpush
