@extends('layouts.app')
@section('title', 'Egg Stocks')

@section('content')
<div class="space-y-5">

    <x-page-header title="Egg Stocks" subtitle="Track harvested egg inventory by size and freshness" />

    @include('eggs._tabs', ['activeTab' => 'stocks'])

    {{-- ── Summary Cards ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" id="summaryCards">
        @foreach($sizes as $size)
        @php
            $total = $totals[$size] ?? 0;
            $trays = $trayTotals[$size] ?? 0;
            $label = ucfirst($size);
            $colors = [
                'small'  => ['#2D7D46', '#d6f0e3'],
                'medium' => ['#1D4E8F', '#dcebfa'],
                'large'  => ['#C2703E', '#fae3d0'],
                'jumbo'  => ['#6B4C8A', '#e9e0f5'],
            ];
            [$color, $soft] = $colors[$size];
        @endphp
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4" data-size="{{ $size }}">
            <div class="text-xs tracking-wider mb-2" style="color: {{ $color }}">{{ $label }}</div>
            <div class="text-3xl font-semibold tracking-tight" style="color: #333333">{{ number_format($total) }}</div>
            <div class="text-xs mt-1" style="color: #6B7280">{{ $trays }} {{ $trays === 1 ? 'tray' : 'trays' }}</div>
        </div>
        @endforeach
    </div>

    {{-- ── Header ── --}}
    <div class="flex items-center justify-between">
        <button onclick="document.getElementById('addStockModal').classList.remove('hidden')"
                class="flex items-center gap-2 bg-[#002D5E] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#001F42] transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Add Stock
        </button>
    </div>

    {{-- ── Batch Table ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">SIZE</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">COUNT</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">TRAYS</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">HARVESTED</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">SOURCE</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">FRESHNESS</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">ACTIONS</th>
                </tr>
            </thead>
            <tbody id="batchTableBody">
                @forelse($batches as $batch)
                @php
                    $freshnessColors = [
                        'fresh' => ['#e8f5ec', '#1f6b3a', '#cfe8d6'],
                        'aging' => ['#fdf3e0', '#8a5a00', '#f3e3bf'],
                        'old'   => ['#fbe4e6', '#9b1c24', '#f3cdd0'],
                    ];
                    [$fBg, $fTxt, $fBorder] = $freshnessColors[$batch->freshness_status];
                    $cageCode = $batch->cage?->cage_code ?? '—';
                    $cageColor = $batch->cage?->color ?? '#6B7280';
                    $cageSoft = $batch->cage?->color_soft ?? '#f0f0f0';
                    $sizeColors = [
                        'small'  => ['#2D7D46', '#d6f0e3', '#b8e0cc'],
                        'medium' => ['#1D4E8F', '#dcebfa', '#b3d4fc'],
                        'large'  => ['#C2703E', '#fae3d0', '#f3c9a8'],
                        'jumbo'  => ['#6B4C8A', '#e9e0f5', '#d4c5e8'],
                    ];
                    [$sBg, $sTxt, $sBorder] = $sizeColors[$batch->egg_size];
                @endphp
                <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]" data-batch-id="{{ $batch->id }}">
                    <td class="px-5 py-3.5">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold" style="background:{{ $sBg }};color:{{ $sTxt }};border:1px solid {{ $sBorder }}">
                            {{ ucfirst($batch->egg_size) }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-sm font-medium text-[#333333]">{{ number_format($batch->count) }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7280]">{{ (int) ceil($batch->count / 30) }}</td>
                    <td class="px-5 py-3.5 text-sm font-mono text-[#333333]">{{ $batch->harvested_date->format('Y-m-d') }}</td>
                    <td class="px-5 py-3.5 text-sm font-medium" style="color:{{ $cageColor }}">{{ $cageCode }}</td>
                    <td class="px-5 py-3.5">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold" style="background:{{ $fBg }};color:{{ $fTxt }};border:1px solid {{ $fBorder }}">
                            {{ ucfirst($batch->freshness_status) }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('eggs.stocks.qr', $batch) }}" target="_blank"
                               class="flex items-center gap-1 text-xs border border-[#D9D9D9] px-2.5 py-1.5 rounded hover:bg-[#F5F6F8] text-[#6B7280]">
                                <i data-lucide="qr-code" class="w-3 h-3"></i> QR
                            </a>
                            <form method="POST" action="{{ route('eggs.stocks.destroy', $batch) }}"
                                  data-confirm="Delete this stock batch?" data-confirm-action="Delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="flex items-center gap-1 text-xs border border-[#D9D9D9] px-2.5 py-1.5 rounded hover:bg-[#F5F6F8] text-red-400 hover:text-red-600">
                                    <i data-lucide="trash-2" class="w-3 h-3"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-[#6B7280]">No stock batches yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

{{-- Add Stock Modal --}}
<div id="addStockModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" role="dialog" aria-modal="true">
    <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeAddStockModal()"></div>
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
                        <option value="{{ $cage->id }}">{{ $cage->cage_code }} — {{ $cage->location ?: 'No location' }}</option>
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
                <button type="submit"
                        class="flex-1 bg-[#002D5E] text-white py-2.5 rounded-lg text-sm">Add Stock</button>
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
                        location.reload();
                    }
                });
            });
        }
    });
})();
</script>
@endpush
