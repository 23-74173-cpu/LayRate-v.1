@extends('layouts.app')
@section('title', 'Reports')

@push('head')
<style>
@media print {
    aside, header, .no-print { display: none !important; }
    body, .flex.h-screen { display: block !important; overflow: visible !important; }
    .overflow-y-auto, .overflow-x-auto { overflow: visible !important; }
    main { padding: 0 !important; }
    #report-doc {
        box-shadow: none !important;
        border-radius: 0 !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 20mm !important;
    }
    body { font-family: Georgia, 'Times New Roman', serif !important; background: white !important; }
    thead { display: table-header-group; }
    tfoot, .signature-block { page-break-inside: avoid; }
    .no-screen { display: block !important; }
}
.no-screen { display: none; }
</style>
@endpush

@section('content')
<main class="p-5 space-y-5">

    {{-- ── Filters ── --}}
    <div class="no-print">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <form method="GET" action="{{ route('reports') }}" class="flex flex-wrap items-end gap-4" id="reportForm">
                <div>
                    <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">REPORT TYPE</label>
                    <select name="type" id="reportType"
                            class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C] min-w-[180px]">
                        <option value="production" {{ $type === 'production' ? 'selected' : '' }}>Production Report</option>
                        <option value="feed"        {{ $type === 'feed'       ? 'selected' : '' }}>Feed Report</option>
                        <option value="environment" {{ $type === 'environment'? 'selected' : '' }}>Environment Report</option>
                        <option value="mortality"   {{ $type === 'mortality'  ? 'selected' : '' }}>Mortality Report</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">FROM</label>
                    <input type="date" name="from" value="{{ $from }}"
                           class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <div>
                    <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">TO</label>
                    <input type="date" name="to" value="{{ $to }}"
                           class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <div>
                    <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">CAGE</label>
                    <select name="cage"
                            class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        <option value="all" {{ $cageId === 'all' ? 'selected' : '' }}>All Cages</option>
                        @foreach($allCages as $c)
                        <option value="{{ $c->cage_code }}" {{ $cageId === $c->cage_code ? 'selected' : '' }}>{{ $c->cage_code }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Reason filter — only visible for mortality report --}}
                <div id="reasonFilter" class="{{ $type === 'mortality' ? '' : 'hidden' }}">
                    <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">REASON</label>
                    <select name="reason"
                            class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        <option value="all" {{ $reason === 'all' ? 'selected' : '' }}>All Reasons</option>
                        @foreach(\App\Models\MortalityLog::REASONS as $r)
                        <option value="{{ $r }}" {{ $reason === $r ? 'selected' : '' }}>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit"
                        class="bg-[#102A4C] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#1D4E8F] transition-colors">
                    Generate Report
                </button>
                @if($rows->isNotEmpty())
                <a href="{{ route('reports.csv', request()->query()) }}"
                   class="flex items-center gap-1.5 border border-[#D9D9D9] text-[#6B7280] px-4 py-2 rounded-lg text-sm hover:bg-[#F5F6F8] transition-colors">
                    <i data-lucide="download" class="w-4 h-4"></i> Export CSV
                </a>
                <button type="button" onclick="window.print()"
                        class="flex items-center gap-1.5 border border-[#D9D9D9] text-[#6B7280] px-3 py-2 rounded-lg text-sm hover:bg-[#F5F6F8] transition-colors">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                </button>
                @endif
            </form>
        </div>
    </div>

    {{-- ── Report Document ── --}}
    @if($from && $to && $rows->isNotEmpty())
    <div id="report-doc" class="bg-white rounded-lg border border-[#D9D9D9] p-8 max-w-5xl mx-auto shadow-sm">

        {{-- 1. Letterhead --}}
        <div class="flex items-start justify-between mb-1">
            {{-- Left: brand --}}
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-[#102A4C] rounded-lg flex items-center justify-center shrink-0">
                    <i data-lucide="feather" class="w-5 h-5 text-white"></i>
                </div>
                <div>
                    <div class="font-bold text-[#102A4C] leading-tight">LayRate Poultry Farm</div>
                    <div class="text-xs text-[#6B7280]">Farm Monitor System</div>
                </div>
            </div>
            {{-- Right: report title + date range --}}
            <div class="text-right">
                <div class="text-sm font-bold text-[#102A4C] uppercase tracking-widest">{{ ucfirst($type) }} Report</div>
                <div class="text-xs text-[#6B7280] mt-0.5">{{ $from }} &mdash; {{ $to }}</div>
            </div>
        </div>
        <hr style="border:none;border-top:3px solid #102A4C;margin:12px 0">

        {{-- 2. Metadata strip --}}
        <div class="grid grid-cols-4 gap-4 mb-6 text-xs text-[#6B7280]">
            <div>
                <span class="font-medium text-[#333333]">Cage:</span>
                {{ $cageId === 'all' ? 'All Cages' : $cageId }}
            </div>
            <div>
                <span class="font-medium text-[#333333]">Generated:</span>
                {{ now()->format('F j, Y  H:i') }}
            </div>
            <div>
                <span class="font-medium text-[#333333]">Prepared by:</span>
                {{ auth()->user()->name }}
            </div>
            <div>
                <span class="font-medium text-[#333333]">Records:</span>
                {{ $rows->count() }}
            </div>
        </div>

        {{-- 3. Summary pills --}}
        @if($summary !== null)
        <div class="flex gap-4 mb-6">
            @if($type === 'production')
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Total Eggs</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->total_eggs }}</div>
            </div>
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Avg HDEP</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->avg_hdep }}</div>
            </div>
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Total Hens</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->total_hens }}</div>
            </div>
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Days Covered</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->days }}</div>
            </div>
            @elseif($type === 'feed')
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Total Consumed</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->total_kg }} kg</div>
            </div>
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Avg per Day</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->avg_per_day }} kg</div>
            </div>
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Batches Used</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->batches }}</div>
            </div>
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Days Covered</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->days }}</div>
            </div>
            @elseif($type === 'environment')
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Avg Temperature</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->avg_temp }}</div>
            </div>
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Avg Humidity</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->avg_hum }}</div>
            </div>
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Total Readings</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->readings }}</div>
            </div>
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Alert Readings</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->alerts }}</div>
            </div>
            @elseif($type === 'mortality')
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Total Deaths</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->total_deaths }}</div>
            </div>
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Top Cause</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->top_cause }}</div>
            </div>
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Most Affected</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->most_affected }}</div>
            </div>
            <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
                <div class="text-[10px] tracking-wider text-[#6B7280] uppercase">Days Covered</div>
                <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->days }}</div>
            </div>
            @endif
        </div>
        @endif

        {{-- 4. Data table --}}
        @php
        $reasonColors = [
            'Disease'     => '#721C24',
            'Heat Stress' => '#856404',
            'Injury'      => '#856404',
            'Predator'    => '#721C24',
        ];
        @endphp
        <div class="overflow-x-auto mb-2">
            <table class="w-full" style="border-collapse:collapse">
                <thead>
                    <tr style="background:#102A4C;color:#ffffff;">
                        @foreach(array_keys((array) $rows->first()) as $col)
                        <th class="px-4 py-3 text-left text-[10px] tracking-widest uppercase font-medium whitespace-nowrap">
                            {{ strtoupper(str_replace('_', ' ', $col)) }}
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    @php $arr = (array) $row; @endphp
                    <tr class="{{ $loop->even ? 'bg-[#F9F9F7]' : 'bg-white' }}">
                        @foreach($arr as $key => $val)
                        @php
                            $cageColor   = $key === 'cage' ? match($val){'CAGE-A'=>'#2D7D46','CAGE-B'=>'#1D4E8F','CAGE-C'=>'#C2703E','CAGE-D'=>'#6B4C8A',default=>null} : null;
                            $reasonColor = $key === 'reason' ? ($reasonColors[$val] ?? null) : null;
                            $style = $cageColor ? "color:{$cageColor};font-weight:600"
                                   : ($reasonColor ? "color:{$reasonColor}" : '');
                        @endphp
                        <td class="px-4 py-2.5 text-sm {{ in_array($key, ['date','datetime']) ? 'font-mono' : '' }}"
                            style="{{ $style }}">
                            {{ $val }}
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- 5. Signature block --}}
        <div class="signature-block mt-12 pt-6 border-t border-[#D9D9D9] grid grid-cols-2 gap-16">
            <div>
                <div class="text-xs text-[#6B7280] mb-8">Prepared by:</div>
                <div class="border-b border-[#333333] mb-1.5"></div>
                <div class="text-xs text-[#6B7280]">{{ auth()->user()->name }}</div>
                <div class="text-xs text-[#6B7280]">Signature / Date</div>
            </div>
            <div>
                <div class="text-xs text-[#6B7280] mb-8">Noted by:</div>
                <div class="border-b border-[#333333] mb-1.5"></div>
                <div class="text-xs text-[#6B7280]">Name / Position</div>
                <div class="text-xs text-[#6B7280]">Signature / Date</div>
            </div>
        </div>

    </div>
    @elseif($from && $to && $rows->isEmpty())
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-10 text-center text-sm text-[#6B7280]">
        No data found for the selected filters.
    </div>
    @endif

</main>
@endsection

@push('scripts')
<script>
document.getElementById('reportType').addEventListener('change', function () {
    document.getElementById('reasonFilter').classList.toggle('hidden', this.value !== 'mortality');
});
</script>
@endpush
