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
    tbody tr { page-break-inside: avoid; }
    tfoot, .signature-block { page-break-inside: avoid; }
    .no-screen { display: block !important; }
}
.no-screen { display: none; }
</style>
@endpush

@section('content')
<div class="space-y-5">

    @php $cageColorMap = \App\Models\Cage::getColorMap(); @endphp

    <x-page-header title="Reports" subtitle="Generate and export production, feed, environment, mortality, and egg stock reports" />

    {{-- ── Filters ── --}}
    <div class="no-print">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <form method="GET" action="{{ route('reports') }}" class="flex flex-wrap items-end gap-4" id="reportForm"
                  data-loading="Gathering records for the selected filters..."
                  data-loading-title="Generating Report">
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">REPORT TYPE</label>
                    <select name="type" id="reportType"
                            class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C] min-w-[180px]">
                        <option value="production" {{ $type === 'production' ? 'selected' : '' }}>Production Report</option>
                        <option value="feed"        {{ $type === 'feed'       ? 'selected' : '' }}>Feed Report</option>
                        <option value="environment" {{ $type === 'environment'? 'selected' : '' }}>Environment Report</option>
                        <option value="mortality"   {{ $type === 'mortality'  ? 'selected' : '' }}>Mortality Report</option>
                        <option value="egg_stock"   {{ $type === 'egg_stock'  ? 'selected' : '' }}>Egg Stock Report</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">FROM</label>
                    <input type="date" name="from" value="{{ $from }}"
                           class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">TO</label>
                    <input type="date" name="to" value="{{ $to }}"
                           class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CAGE</label>
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
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">REASON</label>
                    <select name="reason"
                            class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        <option value="all" {{ $reason === 'all' ? 'selected' : '' }}>All Reasons</option>
                        @foreach(\App\Models\MortalityLog::REASONS as $r)
                        <option value="{{ $r }}" {{ $reason === $r ? 'selected' : '' }}>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>

                <x-button type="submit">
                    Generate Report
                </x-button>
                @if($rows->isNotEmpty())
                <a href="{{ route('reports.csv', request()->query()) }}"
                   class="flex items-center gap-1.5 border border-[#D9D9D9] text-[#6B7280] px-4 py-2 rounded-lg text-sm hover:bg-[#F5F6F8] transition-colors">
                    <i data-lucide="download" class="w-4 h-4"></i> Export CSV
                </a>
                @if($full)
                <a href="{{ route('reports', request()->except('full')) }}"
                   class="flex items-center gap-1.5 border border-[#D9D9D9] text-[#6B7280] px-4 py-2 rounded-lg text-sm hover:bg-[#F5F6F8] transition-colors">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Preview
                </a>
                <button type="button" onclick="window.print()"
                        class="flex items-center gap-1.5 border border-[#D9D9D9] text-[#6B7280] px-3 py-2 rounded-lg text-sm hover:bg-[#F5F6F8] transition-colors">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                </button>
                @endif
                @endif
            </form>
        </div>
    </div>

    @if($rows->isNotEmpty())
    @if(!$full)
    {{-- ── Preview table (item #84) — the printable document is an explicit second step ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9]">
        <div class="px-5 py-4 border-b border-[#D9D9D9] flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-[#333333]">Report Preview</h2>
                <p class="text-xs text-[#6B7280] mt-0.5">
                    {{ ucfirst(str_replace('_', ' ', $type)) }} &middot; {{ $from && $to ? "{$from} — {$to}" : 'All time' }} &middot; {{ $cageId === 'all' ? 'All Cages' : $cageId }} &middot; {{ $rows->count() }} record(s)
                </p>
            </div>
            <a href="{{ route('reports', array_merge(request()->query(), ['full' => 1])) }}"
               class="inline-flex items-center gap-2 bg-[#002D5E] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#001F42] transition-colors">
                <i data-lucide="file-text" class="w-4 h-4"></i> View Printable Report
            </a>
        </div>
        <div class="p-5">
            @include('reports._summary-pills')
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-[#e6e6e6] bg-[#f9f9f7]">
                            @foreach(array_keys((array) $rows->first()) as $col)
                            <th class="px-5 py-3 text-left text-xs tracking-wider text-[#6B7280] uppercase font-medium whitespace-nowrap">
                                {{ strtoupper(str_replace('_', ' ', $col)) }}
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                        <tr class="border-b border-[#F0F0F0]">
                            @foreach((array) $row as $key => $val)
                            <td class="px-5 py-3 text-sm text-[#333333] {{ in_array($key, ['date','datetime']) ? 'font-mono' : '' }}">
                                {{ $val }}
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @else
    {{-- ── Report Document ── --}}
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
                <div class="text-xs text-[#6B7280] mt-0.5">{{ $from && $to ? "{$from} — {$to}" : 'All time' }}</div>
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

        {{-- 3. Summary pills (shared with the preview via partial) --}}
        @include('reports._summary-pills')

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
                        <th class="px-5 py-3 text-left text-xs tracking-widest uppercase font-medium whitespace-nowrap">
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
                            $cageColor   = $key === 'cage' ? ($cageColorMap[$val] ?? null) : null;
                            $reasonColor = $key === 'reason' ? ($reasonColors[$val] ?? null) : null;
                            $style = $cageColor ? "color:{$cageColor};font-weight:600"
                                   : ($reasonColor ? "color:{$reasonColor}" : '');
                        @endphp
                        <td class="px-5 py-3.5 text-sm {{ in_array($key, ['date','datetime']) ? 'font-mono' : '' }}"
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
    @endif
    @elseif($rows->isEmpty())
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-10 text-center text-sm text-[#6B7280]">
        No data found for the selected filters.
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('turbo:load', function() {
    var el = document.getElementById('reportType');
    if (el) {
        el.addEventListener('change', function() {
            document.getElementById('reasonFilter').classList.toggle('hidden', this.value !== 'mortality');
        });
    }
});
</script>
@endpush
