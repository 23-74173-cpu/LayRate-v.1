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
            <form method="GET" action="{{ route('reports') }}" class="flex flex-wrap items-end gap-4" id="reportForm" data-turbo="false">
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
                <x-button variant="secondary" :href="route('reports.csv', request()->query())" id="reportCsvBtn">
                    <i data-lucide="download" class="w-4 h-4"></i> Export CSV
                </x-button>
                @if($full)
                <x-button variant="secondary" :href="route('reports', request()->except('full'))">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Preview
                </x-button>
                <x-button variant="secondary" type="button" onclick="window.print()">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                </x-button>
                @endif
            </form>
        </div>
    </div>

    @if($full)
    {{-- ── Report Document (full-page printable) ── --}}
    <div id="report-doc" class="bg-white rounded-lg border border-[#D9D9D9] p-8 max-w-5xl mx-auto shadow-sm">

        {{-- 1. Letterhead --}}
        <div class="flex items-start justify-between mb-1">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-[#102A4C] rounded-lg flex items-center justify-center shrink-0">
                    <i data-lucide="feather" class="w-5 h-5 text-white"></i>
                </div>
                <div>
                    <div class="font-bold text-[#102A4C] leading-tight">LayRate Poultry Farm</div>
                    <div class="text-xs text-[#6B7280]">Farm Monitor System</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm font-bold text-[#102A4C] uppercase tracking-widest">{{ ucfirst($type) }} Report</div>
                <div class="text-xs text-[#6B7280] mt-0.5">{{ $from && $to ? "{$from} — {$to}" : 'All time' }}</div>
            </div>
        </div>
        <hr style="border:none;border-top:3px solid #102A4C;margin:12px 0">

        {{-- 2. Metadata strip --}}
        <div class="grid grid-cols-4 gap-4 mb-6 text-xs text-[#6B7280]">
            <div><span class="font-medium text-[#333333]">Cage:</span> {{ $cageId === 'all' ? 'All Cages' : $cageId }}</div>
            <div><span class="font-medium text-[#333333]">Generated:</span> {{ now()->format('F j, Y  H:i') }}</div>
            <div><span class="font-medium text-[#333333]">Prepared by:</span> {{ auth()->user()->name }}</div>
            <div><span class="font-medium text-[#333333]">Records:</span> {{ $rows->count() }}</div>
        </div>

        {{-- 3. Summary pills --}}
        @include('reports._summary-pills')

        {{-- 4. Data table --}}
        @php
        $reasonColors = ['Disease' => '#721C24', 'Heat Stress' => '#856404', 'Injury' => '#856404', 'Predator' => '#721C24'];
        @endphp
        <div class="overflow-x-auto mb-2">
            <table class="w-full" style="border-collapse:collapse">
                <thead>
                    <tr style="background:#102A4C;color:#ffffff;">
                        @foreach(array_keys((array) $rows->first()) as $col)
                        <th class="px-5 py-3 text-left text-xs tracking-widest uppercase font-medium whitespace-nowrap">{{ strtoupper(str_replace('_', ' ', $col)) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    @php $arr = (array) $row; @endphp
                    <tr class="{{ $loop->even ? 'bg-[#F9F9F7]' : 'bg-white' }}">
                        @foreach($arr as $key => $val)
                        @php
                            $cC = $key === 'cage' ? ($cageColorMap[$val] ?? null) : null;
                            $rC = $key === 'reason' ? ($reasonColors[$val] ?? null) : null;
                            $style = $cC ? "color:{$cC};font-weight:600" : ($rC ? "color:{$rC}" : '');
                        @endphp
                        <td class="px-5 py-3.5 text-sm {{ in_array($key, ['date','datetime']) ? 'font-mono' : '' }}"
                            style="{{ $style }}">{{ $val }}</td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- 5. Signature block --}}
        <div class="signature-block mt-12 pt-6 border-t border-[#D9D9D9] grid grid-cols-2 gap-16">
            <div><div class="text-xs text-[#6B7280] mb-8">Prepared by:</div><div class="border-b border-[#333333] mb-1.5"></div><div class="text-xs text-[#6B7280]">{{ auth()->user()->name }}</div><div class="text-xs text-[#6B7280]">Signature / Date</div></div>
            <div><div class="text-xs text-[#6B7280] mb-8">Noted by:</div><div class="border-b border-[#333333] mb-1.5"></div><div class="text-xs text-[#6B7280]">Name / Position</div><div class="text-xs text-[#6B7280]">Signature / Date</div></div>
        </div>
    </div>
    @else
    {{-- ── Preview (AJAX-updatable) ── --}}
    <div id="report-preview-container">
        @include('reports._preview')
    </div>
    @endif

</div>
@endsection

@push('scripts')
<style>
#report-preview-container.loading { position: relative; }
#report-preview-container.loading::after {
    content: ''; position: absolute; inset: 0; z-index: 10;
    background: rgba(255,255,255,0.6);
    pointer-events: none;
}
</style>
<script>
// ── Build query string from filter form ──
function reportQuery(page) {
    var f = document.getElementById('reportForm');
    if (!f) return '';
    var params = new URLSearchParams();
    ['type','from','to','cage','reason'].forEach(function(k) {
        var el = f.elements[k];
        if (el && el.value) params.set(k, el.value);
    });
    if (page) params.set('page', page);
    return params.toString();
}

// ── Update CSV export button href ──
function updateCsvHref() {
    var btn = document.getElementById('reportCsvBtn');
    if (btn) {
        btn.href = '/reports/csv?' + reportQuery();
    }
}

// ── Render report results ──
window.renderReportResults = function(data) {
    var container = document.getElementById('report-preview-container');
    if (!container) return;
    container.innerHTML = data.html;
    // Re-run lucide icons on new content
    if (window.lucide) lucide.createIcons();
    updateCsvHref();
    // Update URL to reflect current filter state (for history/bookmark)
    var qs = reportQuery();
    var url = qs ? '/reports?' + qs : '/reports';
    if (window._reportPopstate) {
        history.replaceState({}, '', url);
        window._reportPopstate = false;
    } else {
        history.pushState({}, '', url);
    }
};

window._reportPopstate = false;

// ── Fetch report data ──
function reportFetch(page) {
    var container = document.getElementById('report-preview-container');
    if (container) container.classList.add('loading');

    fetch('/reports/data?' + reportQuery(page || null))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            window.renderReportResults(data);
            if (container) container.classList.remove('loading');
        })
        .catch(function(err) {
            console.error('Report update failed:', err);
            if (container) container.classList.remove('loading');
        });
}

// ── Form submit handler (override full-page GET) ──
document.addEventListener('submit', function(e) {
    if (e.target.id !== 'reportForm') return;
    e.preventDefault();
    reportFetch();
});

// ── Filter change handlers ──
document.addEventListener('change', function(e) {
    var form = e.target.closest('#reportForm');
    if (!form) return;
    // Reason filter visibility
    if (e.target.name === 'type') {
        document.getElementById('reasonFilter').classList.toggle('hidden', e.target.value !== 'mortality');
    }
    reportFetch();
});

// ── Pagination click handler ──
document.addEventListener('click', function(e) {
    var link = e.target.closest('#report-preview-container a[href]');
    if (!link) return;
    var href = link.getAttribute('href');
    if (!href) return;
    // Only intercept paginator links (contain 'page=' param)
    var url = new URL(href, window.location.origin);
    var page = url.searchParams.get('page');
    if (!page) return;
    e.preventDefault();
    reportFetch(page);
});

// ── Back/forward navigation ──
window.addEventListener('popstate', function() {
    window._reportPopstate = true;
    var params = new URLSearchParams(window.location.search);
    var f = document.getElementById('reportForm');
    if (f) {
        ['type','from','to','cage','reason'].forEach(function(k) {
            var el = f.elements[k];
            if (el && params.has(k)) el.value = params.get(k);
        });
        var rt = document.getElementById('reasonFilter');
        if (rt) {
            rt.classList.toggle('hidden', f.elements['type'].value !== 'mortality');
        }
    }
    reportFetch();
});
</script>
@endpush
