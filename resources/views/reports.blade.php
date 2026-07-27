@extends('layouts.app')
@section('title', 'Reports')

@push('head')
<style>
@media print {
    aside, header, .no-print { display: none !important; }
    body { display: block !important; overflow: visible !important; }
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
    /* On screen, the printable doc is paginated client-side (see script block);
       printing must always show every row regardless of the current page. */
    #report-doc tbody tr { display: table-row !important; }
    /* Canvas rendering is unreliable across browsers' print pipelines — the
       print-time swap (see beforeprint listener) replaces each canvas with a
       static image, which is what actually prints. */
    .report-chart-canvas { display: none !important; }
    .report-chart-img.has-src { display: block !important; width: 100% !important; height: auto !important; }
}
.no-screen { display: none; }
.report-chart-img { display: none; }
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
                        <option value="all"         {{ $type === 'all'       ? 'selected' : '' }}>All Reports</option>
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

                {{-- Reason filter — only relevant when mortality data is shown (single mortality report, or the mortality section within "All Reports") --}}
                <div id="reasonFilter" class="{{ in_array($type, ['mortality', 'all']) ? '' : 'hidden' }}">
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">REASON</label>
                    <select name="reason"
                            class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        <option value="all" {{ $reason === 'all' ? 'selected' : '' }}>All Reasons</option>
                        @foreach(\App\Models\MortalityLog::REASONS as $r)
                        <option value="{{ $r }}" {{ $reason === $r ? 'selected' : '' }}>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2 pb-2">
                    <input type="checkbox" name="charts" id="reportCharts" value="1" {{ ($charts ?? false) ? 'checked' : '' }}
                           class="rounded border-[#D9D9D9] text-[#102A4C] focus:ring-[#102A4C]/30">
                    <label for="reportCharts" class="text-xs tracking-wider text-[#6B7280] select-none cursor-pointer">INCLUDE GRAPHS</label>
                </div>

                {{-- Export dropdown — CSV / Excel / PDF, all respecting the current filters --}}
                <div class="relative" id="exportDropdownWrap">
                    <x-button variant="secondary" type="button" id="exportDropdownBtn" aria-haspopup="true" aria-expanded="false">
                        <i data-lucide="download" class="w-4 h-4"></i> Export <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                    </x-button>
                    <div id="exportDropdownMenu" class="hidden absolute left-0 top-full mt-2 w-36 rounded-lg border border-[#D9D9D9] bg-white shadow-lg py-1 z-50">
                        <a href="{{ route('reports.csv', request()->query()) }}" id="exportCsvLink" class="flex items-center gap-2 px-3 py-2 text-sm text-[#333333] hover:bg-black/5 transition-colors">
                            <i data-lucide="file-text" class="w-4 h-4"></i> CSV
                        </a>
                        <a href="{{ route('reports.excel', request()->query()) }}" id="exportExcelLink" class="flex items-center gap-2 px-3 py-2 text-sm text-[#333333] hover:bg-black/5 transition-colors">
                            <i data-lucide="table" class="w-4 h-4"></i> Excel
                        </a>
                        <a href="{{ route('reports.pdf', request()->query()) }}" id="exportPdfLink" class="flex items-center gap-2 px-3 py-2 text-sm text-[#333333] hover:bg-black/5 transition-colors">
                            <i data-lucide="file" class="w-4 h-4"></i> PDF
                        </a>
                    </div>
                </div>

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
                <div class="text-sm font-bold text-[#102A4C] uppercase tracking-widest">{{ $type === 'all' ? 'All Reports' : ucfirst($type) . ' Report' }}</div>
                <div class="text-xs text-[#6B7280] mt-0.5">{{ $from && $to ? "{$from} — {$to}" : 'All time' }}</div>
            </div>
        </div>
        <hr style="border:none;border-top:3px solid #102A4C;margin:12px 0">

        {{-- 2. Metadata strip --}}
        <div class="grid grid-cols-4 gap-4 mb-6 text-xs text-[#6B7280]">
            <div><span class="font-medium text-[#333333]">Cage:</span> {{ $cageId === 'all' ? 'All Cages' : $cageId }}</div>
            <div><span class="font-medium text-[#333333]">Generated:</span> {{ now()->format('F j, Y  H:i') }}</div>
            <div><span class="font-medium text-[#333333]">Prepared by:</span> {{ auth()->user()->name }}</div>
            <div><span class="font-medium text-[#333333]">Records:</span> {{ $type === 'all' ? collect($sections)->sum(fn($s) => $s['rows']->count()) : $rows->count() }}</div>
        </div>

        @php
        $reasonColors = ['Disease' => '#721C24', 'Heat Stress' => '#856404', 'Injury' => '#856404', 'Predator' => '#721C24'];
        @endphp

        @if($type === 'all')
        {{-- 3-4. One labeled section per report type — own heading, pills, chart, table --}}
        @foreach($sections as $section)
        <div class="mb-10 {{ !$loop->first ? 'pt-6 border-t border-[#D9D9D9]' : '' }}">
            <h2 class="text-sm font-bold text-[#102A4C] uppercase tracking-wide mb-4">{{ $section['label'] }}</h2>

            @include('reports._summary-pills', ['type' => $section['type'], 'summary' => $section['summary']])

            @if($charts)
            <div class="mb-6">
                <div id="chart-{{ $section['type'] }}-wrap" class="relative w-full h-[220px]">
                    <canvas id="chart-{{ $section['type'] }}" class="report-chart-canvas"></canvas>
                    <img id="chart-{{ $section['type'] }}-img" class="report-chart-img">
                </div>
                <div id="chart-{{ $section['type'] }}-empty" class="h-[80px] hidden items-center justify-center text-sm text-[#6B7280]">No chart data for this selection.</div>
            </div>
            @endif

            @if($section['rows']->isNotEmpty())
            <div class="overflow-x-auto mb-2">
                <table class="w-full" style="border-collapse:collapse" data-report-table="{{ $section['type'] }}">
                    <thead>
                        <tr style="background:#102A4C;color:#ffffff;">
                            @foreach(array_keys((array) $section['rows']->first()) as $col)
                            <th class="px-5 py-3 text-left text-xs tracking-widest uppercase font-medium whitespace-nowrap">{{ strtoupper(str_replace('_', ' ', $col)) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['rows'] as $row)
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
            <div class="no-print flex items-center justify-center gap-3 mt-3 text-xs text-[#6B7280]" data-report-pager="{{ $section['type'] }}">
                <button type="button" data-page-prev class="px-2.5 py-1 rounded border border-[#D9D9D9] hover:bg-black/5 disabled:opacity-40 disabled:cursor-not-allowed" disabled>‹ Prev</button>
                <span data-page-label>Page 1 of 1</span>
                <button type="button" data-page-next class="px-2.5 py-1 rounded border border-[#D9D9D9] hover:bg-black/5 disabled:opacity-40 disabled:cursor-not-allowed" disabled>Next ›</button>
            </div>
            @else
            <div class="text-center text-sm text-[#6B7280] py-6">No data found for the selected filters.</div>
            @endif
        </div>
        @endforeach
        @else
        {{-- 3. Summary pills --}}
        @include('reports._summary-pills')

        @if($charts ?? false)
        <div class="mb-6">
            <div id="chart-{{ $type }}-wrap" class="relative w-full h-[260px]">
                <canvas id="chart-{{ $type }}" class="report-chart-canvas"></canvas>
                <img id="chart-{{ $type }}-img" class="report-chart-img">
            </div>
            <div id="chart-{{ $type }}-empty" class="h-[100px] hidden items-center justify-center text-sm text-[#6B7280]">No chart data for this selection.</div>
        </div>
        @endif

        {{-- 4. Data table --}}
        <div class="overflow-x-auto mb-2">
            <table class="w-full" style="border-collapse:collapse" data-report-table="{{ $type }}">
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
        <div class="no-print flex items-center justify-center gap-3 mt-3 text-xs text-[#6B7280]" data-report-pager="{{ $type }}">
            <button type="button" data-page-prev class="px-2.5 py-1 rounded border border-[#D9D9D9] hover:bg-black/5 disabled:opacity-40 disabled:cursor-not-allowed" disabled>‹ Prev</button>
            <span data-page-label>Page 1 of 1</span>
            <button type="button" data-page-next class="px-2.5 py-1 rounded border border-[#D9D9D9] hover:bg-black/5 disabled:opacity-40 disabled:cursor-not-allowed" disabled>Next ›</button>
        </div>
        @endif

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
// pageParams (optional): an object of { page: n } or { page_<type>: n } used
// when navigating a "All Reports" section's own pagination — carries forward
// every OTHER section's current page from the URL so paging one section
// doesn't reset the rest back to page 1. A plain filter change omits this
// entirely, which correctly resets every section back to page 1.
function reportQuery(pageParams) {
    var f = document.getElementById('reportForm');
    if (!f) return '';
    var params = new URLSearchParams();
    ['type','from','to','cage','reason'].forEach(function(k) {
        var el = f.elements[k];
        if (el && el.value) params.set(k, el.value);
    });
    var chartsEl = f.elements['charts'];
    if (chartsEl && chartsEl.checked) params.set('charts', '1');
    if (pageParams) {
        var current = new URLSearchParams(window.location.search);
        current.forEach(function(v, k) {
            if (k === 'page' || k.indexOf('page_') === 0) params.set(k, v);
        });
        Object.keys(pageParams).forEach(function(k) { params.set(k, pageParams[k]); });
    }
    return params.toString();
}

// ── Update export dropdown links (CSV / Excel / PDF) ──
function updateExportHrefs() {
    var f = document.getElementById('reportForm');
    if (!f) return;
    var params = new URLSearchParams();
    ['type','from','to','cage','reason'].forEach(function(k) {
        var el = f.elements[k];
        if (el && el.value) params.set(k, el.value);
    });
    var qs = params.toString();
    var csv = document.getElementById('exportCsvLink');
    var xls = document.getElementById('exportExcelLink');
    var pdf = document.getElementById('exportPdfLink');
    if (csv) csv.href = '/reports/csv?' + qs;
    if (xls) xls.href = '/reports/excel?' + qs;
    if (pdf) pdf.href = '/reports/pdf?' + qs;
}

// ── Chart configs (kind → Chart.js config), reusing the shared LayRateChart helper ──
function reportChartConfig(chart) {
    switch (chart.kind) {
        case 'production':
            return {
                type: 'line',
                data: {
                    labels: chart.labels,
                    datasets: [
                        { label: 'Eggs', data: chart.eggs, borderColor: '#102A4C', backgroundColor: '#102A4C22', tension: 0.3, fill: true, yAxisID: 'y' },
                        { label: 'HDEP %', data: chart.hdep, borderColor: '#C99A3C', backgroundColor: '#C99A3C22', tension: 0.3, yAxisID: 'y1' }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        y:  { position: 'left', title: { display: true, text: 'Eggs', font: { size: 10 } } },
                        y1: { position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'HDEP %', font: { size: 10 } } }
                    }
                }
            };
        case 'feed':
            return {
                type: 'bar',
                data: { labels: chart.labels, datasets: [{ label: 'kg consumed', data: chart.kg, backgroundColor: '#102A4C' }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            };
        case 'environment':
            return {
                type: 'line',
                data: {
                    labels: chart.labels,
                    datasets: [
                        { label: 'Temp °C', data: chart.temp, borderColor: '#c0392b', tension: 0.3 },
                        { label: 'Humidity %', data: chart.humidity, borderColor: '#2980b9', tension: 0.3 }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false }
            };
        case 'mortality':
            return {
                type: 'bar',
                data: { labels: chart.labels, datasets: [{ label: 'Deaths', data: chart.counts, backgroundColor: '#9b1c24' }] },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } } }
            };
        case 'egg_stock':
            return {
                type: 'bar',
                data: { labels: chart.labels, datasets: [{ label: 'Count', data: chart.counts, backgroundColor: '#2d6a4f' }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            };
    }
    return null;
}

var REPORT_CHART_TYPES = ['production', 'feed', 'environment', 'mortality', 'egg_stock'];

function chartHasData(chart) {
    return !!chart && Array.isArray(chart.labels) && chart.labels.length > 0;
}

// Renders/destroys each report chart canvas present on the page. Safe to call
// with a partial or empty payload — types without a matching canvas (charts
// off, or a single-type page missing the other four) are skipped.
window.renderReportCharts = function(charts) {
    charts = charts || {};
    REPORT_CHART_TYPES.forEach(function(type) {
        var canvas = document.getElementById('chart-' + type);
        if (!canvas) return;
        var wrap  = document.getElementById('chart-' + type + '-wrap');
        var empty = document.getElementById('chart-' + type + '-empty');
        var chart = charts[type];
        if (chartHasData(chart)) {
            if (wrap) wrap.style.display = '';
            if (empty) empty.style.display = 'none';
            var config = reportChartConfig(chart);
            if (config && window.LayRateChart) window.LayRateChart.create('chart-' + type, config);
        } else {
            if (wrap) wrap.style.display = 'none';
            if (empty) empty.style.display = 'flex';
            if (window.LayRateChart) window.LayRateChart.destroy('chart-' + type);
        }
    });
};

// ── Print-safety: swap live canvases for static images right before printing ──
window.addEventListener('beforeprint', function() {
    document.querySelectorAll('.report-chart-canvas').forEach(function(canvas) {
        var img = document.getElementById(canvas.id + '-img');
        if (!img) return;
        try {
            img.src = canvas.toDataURL('image/png');
            img.classList.add('has-src');
        } catch (e) {
            console.error('Report chart print image failed for ' + canvas.id + ':', e);
        }
    });
});

// ── Render report results ──
window.renderReportResults = function(data) {
    var container = document.getElementById('report-preview-container');
    if (!container) return;
    // Destroy all live chart instances BEFORE replacing innerHTML. If we replace
    // first, the old canvases are detached and Chart.js's internal ResizeObserver
    // fires on them asynchronously — calling chart.resize() → chart.fit() on a
    // canvas with no parent layout → "Cannot convert object to primitive value"
    // error. That error escapes the destroy() try/catch because it originates from
    // the async ResizeObserver callback, producing infinite repeating errors. The
    // stale PointElement (canvas gone, options undefined) then also throws on every
    // inRange/hitRadius check. Destroying while canvases are still in the DOM avoids
    // both errors entirely.
    if (window.LayRateChart) {
        REPORT_CHART_TYPES.forEach(function(type) {
            LayRateChart.destroy('chart-' + type);
        });
    }
    container.innerHTML = data.html;
    // Re-run lucide icons on new content
    if (window.lucide) lucide.createIcons();
    updateExportHrefs();
    window.renderReportCharts(data.charts || {});
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
function reportFetch(pageParams) {
    var container = document.getElementById('report-preview-container');
    if (container) container.classList.add('loading');

    fetch('/reports/data?' + reportQuery(pageParams || null))
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
    // Reason filter visibility — relevant for a single mortality report or the
    // mortality section inside "All Reports"
    if (e.target.name === 'type') {
        document.getElementById('reasonFilter').classList.toggle('hidden', !['mortality', 'all'].includes(e.target.value));
    }
    reportFetch();
});

// ── Pagination click handler (single-type page=, or an "All Reports" section's own page_<type>=) ──
document.addEventListener('click', function(e) {
    var link = e.target.closest('#report-preview-container a[href]');
    if (!link) return;
    var href = link.getAttribute('href');
    if (!href) return;
    var url = new URL(href, window.location.origin);
    var pageParams = {};
    url.searchParams.forEach(function(v, k) {
        if (k === 'page' || k.indexOf('page_') === 0) pageParams[k] = v;
    });
    if (Object.keys(pageParams).length === 0) return;
    e.preventDefault();
    reportFetch(pageParams);
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
        var chartsEl = f.elements['charts'];
        if (chartsEl) chartsEl.checked = params.get('charts') === '1';
        var rt = document.getElementById('reasonFilter');
        if (rt) {
            rt.classList.toggle('hidden', !['mortality', 'all'].includes(f.elements['type'].value));
        }
    }
    reportFetch();
});

// ── Export dropdown toggle ──
(function() {
    var btn = document.getElementById('exportDropdownBtn');
    var menu = document.getElementById('exportDropdownMenu');
    if (!btn || !menu) return;
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        var isOpen = !menu.classList.contains('hidden');
        menu.classList.toggle('hidden', isOpen);
        btn.setAttribute('aria-expanded', String(!isOpen));
    });
    document.body.addEventListener('click', function(e) {
        if (!e.target.closest('#exportDropdownMenu') && !e.target.closest('#exportDropdownBtn')) {
            menu.classList.add('hidden');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            menu.classList.add('hidden');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
})();

// ── On-screen pagination for the printable (?full=1) document ──
// Purely client-side, over rows already rendered in the DOM: printing must
// still show every row (see the @media print rule forcing tbody tr back to
// visible), this only limits what's visible while composing on screen.
(function() {
    var PAGE_SIZE = 25;
    document.querySelectorAll('table[data-report-table]').forEach(function(table) {
        var tbody = table.querySelector('tbody');
        if (!tbody) return;
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        var totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
        var key = table.getAttribute('data-report-table');
        var pager = document.querySelector('[data-report-pager="' + key + '"]');
        var current = 1;

        function render() {
            rows.forEach(function(row, i) {
                row.style.display = (Math.floor(i / PAGE_SIZE) + 1 === current) ? '' : 'none';
            });
            if (!pager) return;
            var label = pager.querySelector('[data-page-label]');
            if (label) label.textContent = 'Page ' + current + ' of ' + totalPages;
            var prev = pager.querySelector('[data-page-prev]');
            var next = pager.querySelector('[data-page-next]');
            if (prev) prev.disabled = current <= 1;
            if (next) next.disabled = current >= totalPages;
            pager.style.display = totalPages > 1 ? '' : 'none';
        }

        if (pager) {
            var prevBtn = pager.querySelector('[data-page-prev]');
            var nextBtn = pager.querySelector('[data-page-next]');
            if (prevBtn) prevBtn.addEventListener('click', function() { if (current > 1) { current--; render(); } });
            if (nextBtn) nextBtn.addEventListener('click', function() { if (current < totalPages) { current++; render(); } });
        }

        render();
    });

    // Initial chart render on a real page load (full doc or first preview
    // load) — subsequent AJAX filter changes render via renderReportResults.
    window.renderReportCharts(@json($chartsPayload ?? []));
})();
</script>
@endpush
