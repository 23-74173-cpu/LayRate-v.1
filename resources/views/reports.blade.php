@extends('layouts.app')
@section('title', 'Reports')

@push('head')
<style>
/* Chrome (and most Chromium-based browsers) reserve page-margin space for
   their own default print header/footer (date, page title/URL, page number)
   — that text isn't part of this document and can't be targeted with a
   selector to hide it. Zeroing the @page margin removes the space Chrome
   renders it into, which suppresses it; #report-doc's own 20mm padding below
   supplies the actual visual margin instead. If a browser still shows it
   regardless, that's the "Headers and footers" checkbox under the print
   dialog's "More settings" — a browser preference outside what CSS can force. */
@page { margin: 0; }
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
    /* type=all: each report-type section (Production, Feed, Environment,
       Mortality, Egg Stock) starts on its own printed page instead of
       flowing straight into the next section's heading. Only non-first
       sections get this class (see the sections loop below in this same
       file) — the first section already starts at the top of page 1. */
    .report-section-break { page-break-before: always; break-before: page; }
}
.no-screen { display: none; }
.report-chart-img { display: none; }
</style>
@endpush

@section('content')
<div class="space-y-5">

    @php $cageColorMap = \App\Models\Cage::getColorMap(); @endphp

    <div class="no-print">
        <x-page-header title="Reports" subtitle="Generate and export production, feed, environment, mortality, and egg stock reports" />
    </div>

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
                <x-button variant="secondary" :href="route('reports', request()->except('full'))" data-turbo="false">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Preview
                </x-button>
                <x-button variant="secondary" type="button" onclick="printReport()">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                </x-button>
                @endif
            </form>
        </div>
    </div>

    @if($full)
    {{-- ── Report Document (full-page printable) ── --}}
    <div id="report-doc" class="bg-white rounded-lg border border-[#D9D9D9] p-8 max-w-5xl mx-auto shadow-sm">

        {{-- Reports export loading overlay --}}
        <div id="reportsExportLoadingOverlay" class="fixed inset-0 min-h-screen min-h-[100dvh] bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
            <div class="bg-white rounded-xl shadow-xl p-8 max-w-sm w-full mx-4 text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[#102A4C]/10 mb-4">
                    <svg class="animate-spin h-6 w-6 text-[#102A4C]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-[#333333] mb-1">Exporting report</h3>
                <p class="text-sm text-[#6B7280] mb-4">Generating your file...</p>
                <div class="w-full bg-[#F0F0F0] rounded-full h-2 overflow-hidden">
                    <div id="reportsProgressBar" class="bg-[#102A4C] h-full rounded-full" style="width: 0%;"></div>
                </div>
            </div>
        </div>

        @include('reports._letterhead', ['type' => $type, 'from' => $from, 'to' => $to])
        @php $recordCount = $type === 'all' ? collect($sections)->sum(fn($s) => $s['rows']->count()) : $rows->count(); @endphp
        @include('reports._meta-strip', ['cageId' => $cageId, 'recordCount' => $recordCount])

        @if($type === 'all')
        {{-- One labeled section per report type — own heading, pills, chart, table --}}
        @foreach($sections as $section)
        <div class="mb-10 {{ !$loop->first ? 'pt-6 border-t border-[#D9D9D9] report-section-break' : '' }}">
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
            @include('reports._report-table', ['rows' => $section['rows'], 'cageColorMap' => $cageColorMap, 'tableKey' => $section['type']])
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

        @include('reports._report-table', ['rows' => $rows, 'cageColorMap' => $cageColorMap, 'tableKey' => $type])
        <div class="no-print flex items-center justify-center gap-3 mt-3 text-xs text-[#6B7280]" data-report-pager="{{ $type }}">
            <button type="button" data-page-prev class="px-2.5 py-1 rounded border border-[#D9D9D9] hover:bg-black/5 disabled:opacity-40 disabled:cursor-not-allowed" disabled>‹ Prev</button>
            <span data-page-label>Page 1 of 1</span>
            <button type="button" data-page-next class="px-2.5 py-1 rounded border border-[#D9D9D9] hover:bg-black/5 disabled:opacity-40 disabled:cursor-not-allowed" disabled>Next ›</button>
        </div>
        @endif

        @include('reports._signature-block')
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
// Captured once at server-render time; re-read by both the page-load
// initializer and the print/export "render fresh" calls below instead of each
// re-embedding its own copy of the JSON-encoded payload.
var REPORT_CHARTS_PAYLOAD = @json($chartsPayload ?? []);
var _chartsReady = false;
var _chartsReadyCallbacks = [];

function onChartsReady(fn) {
    if (_chartsReady) { fn(); return; }
    _chartsReadyCallbacks.push(fn);
}

function chartHasData(chart) {
    return !!chart && Array.isArray(chart.labels) && chart.labels.length > 0;
}

// Chart.js loads via a <script src="/js/chart.min.js"> that layouts/app.blade.php
// places right before @stack('scripts') specifically so inline scripts here can
// assume `Chart` is already defined. That ordering guarantee holds for a real
// full page load, but this page is reached via a Turbo Drive link click ("View
// Printable Report") as often as a hard navigation — Turbo replaces <body> and
// re-inserts/re-executes its <script> tags itself, and that replay does not
// reliably preserve the same synchronous, in-order execution a real HTML parse
// guarantees. When it doesn't, `new Chart(...)` throws "Chart is not defined"
// inside LayRateChart.create()'s try/catch (silently logged to console only),
// leaving the canvas's already-reserved wrapper height blank forever — chart
// missing on the printable page and in anything captured/exported from it, even
// though the exact same data renders fine via the AJAX preview path (which only
// ever runs well after the page — and Chart.js — has finished loading). Wait for
// `Chart` to actually exist before the first render, instead of trusting script
// tag order.
function waitForChartJs(fn, deadline) {
    deadline = deadline || (Date.now() + 3000);
    // Both Chart (the library global) and window.LayRateChart (this app's
    // lifecycle wrapper, defined in the same at-risk script region in
    // layouts/app.blade.php) need to exist — renderReportCharts() silently
    // no-ops per-chart when either is missing, same blank-canvas symptom.
    if (typeof Chart !== 'undefined' && window.LayRateChart) { fn(); return; }
    if (Date.now() >= deadline) { fn(); return; } // give up quietly; failure is now visible via console as before
    requestAnimationFrame(function() { waitForChartJs(fn, deadline); });
}

// Renders/destroys each report chart canvas present on the page. Safe to call
// with a partial or empty payload — types without a matching canvas (charts
// off, or a single-type page missing the other four) are skipped.
window.renderReportCharts = function(charts) {
    charts = charts || {};
    _chartsReady = false;
    var barChartIds = [];
    var createdIds = [];
    var paintedIds = {};
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
            if (config && window.LayRateChart) {
                var chartId = 'chart-' + type;
                config.options = config.options || {};
                // Disabled so the full chart exists on the first real paint
                // instead of animating in over ~1000ms — matters because
                // printReport() / exportReportWithCharts() capture the canvas
                // via toDataURL() right after render, not after the user has
                // had time to watch it finish drawing like on screen.
                config.options.animation = false;
                // Chart.js still schedules that one paint via its own internal
                // requestAnimationFrame regardless of the animation setting —
                // disabling animation skips the interpolation, not the RAF
                // scheduling. afterRender is Chart.js's own "this chart has
                // now actually painted" lifecycle hook; used below (with a
                // deadline fallback) instead of guessing how many frames that
                // takes, which is what left captures grabbing a canvas with
                // zero paints yet.
                config.plugins = (config.plugins || []).concat([{
                    id: 'reportsPaintSignal-' + chartId,
                    afterRender: function() {
                        paintedIds[chartId] = true;
                        // Eager sync (see syncChartPrintImage below) — the
                        // print-ready image is already correct the instant
                        // this chart paints, regardless of how print later
                        // gets triggered.
                        if (typeof syncChartPrintImage === 'function') syncChartPrintImage(chartId);
                    }
                }]);
                window.LayRateChart.create(chartId, config);
                createdIds.push(chartId);
                if (config.type === 'bar') barChartIds.push(chartId);
            }
        } else {
            if (wrap) wrap.style.display = 'none';
            if (empty) empty.style.display = 'flex';
            if (window.LayRateChart) window.LayRateChart.destroy('chart-' + type);
        }
    });
    // Bar charts can additionally, intermittently fail their first paint even
    // once afterRender has fired — see LayRateChart's own self-heal in
    // layouts/app.blade.php, which detects exactly this (a dataset element
    // with no valid pixel geometry) and can take up to ~1100ms to rebuild.
    // Poll that same stuck-paint check too, re-reading LayRateChart._instances
    // on every attempt so a mid-flight self-heal rebuild (which replaces the
    // canvas/instance) is picked up automatically.
    var deadline = Date.now() + 1300;
    (function waitForPaint() {
        var allPainted = createdIds.every(function(id) { return paintedIds[id]; });
        var barsHealthy = barChartIds.every(function(id) {
            var inst = window.LayRateChart && window.LayRateChart._instances[id];
            if (!inst) return true;
            try {
                var meta = inst.getDatasetMeta(0);
                return !(meta.data.length > 0 && meta.data.every(function(el) { return el.base == null || !isFinite(el.base); }));
            } catch (e) {
                return true;
            }
        });
        if ((allPainted && barsHealthy) || Date.now() >= deadline) {
            _chartsReady = true;
            _chartsReadyCallbacks.splice(0).forEach(function(fn) { fn(); });
        } else {
            requestAnimationFrame(waitForPaint);
        }
    })();
};

// ── Force a fresh chart render pass and wait for it to actually paint ──
// Used by both printReport() and exportReportWithCharts() so neither one
// depends on some earlier initialization having already run on this page
// view. Re-rendering is safe/idempotent (LayRateChart.create() destroys and
// recreates), so this works whether charts already painted moments ago or
// never got the chance to at all — e.g. right after a Turbo Drive navigation
// (see initReportCharts() below), which this app's other chart pages
// (Analytics, Environment) already handle by binding to turbo:load instead of
// trusting a bare top-level script to have already fired by the time the user
// acts.
function ensureReportChartsRendered(fn) {
    waitForChartJs(function() {
        window.renderReportCharts(REPORT_CHARTS_PAYLOAD);
        onChartsReady(fn);
    });
}

// ── Print guard: ensure charts are rendered before opening print dialog ──
function printReport() {
    ensureReportChartsRendered(function() {
        window.print();
    });
}

// ── Print-safety: keep each chart's static print image in sync with its canvas ──
// Called eagerly from the afterRender hook in renderReportCharts() — right
// after a chart genuinely paints, not lazily at print time — plus again here
// on beforeprint as a refresh/fallback. That's deliberate: printReport()'s
// "render fresh, then wait, then print" sequence only runs when the user
// clicks this page's own print icon. The browser's native print (Ctrl+P,
// right-click → Print, the address-bar/menu print entry) skips all of that
// entirely and just fires beforeprint on whatever's already on the canvas —
// so if the swap only happened lazily inside that listener, native print
// could still grab a blank/incomplete canvas depending on exactly when the
// user triggered it relative to the chart's own paint. Syncing the image the
// moment each chart actually paints means it's already correct by the time
// ANY print path fires, in-app button or native.
function syncChartPrintImage(canvasId) {
    var canvas = document.getElementById(canvasId);
    var img = document.getElementById(canvasId + '-img');
    if (!canvas || !img) return;
    try {
        img.src = canvas.toDataURL('image/png');
        img.classList.add('has-src');
        // Setting .src is synchronous; actually decoding those bytes into
        // paintable pixels is not — even for a data: URI, the browser decodes
        // asynchronously and the image stays blank until that finishes. This
        // runs eagerly at chart-paint time (see afterRender above), well
        // before a human could plausibly react and trigger print, so decode
        // should already be done by then regardless — but calling decode()
        // explicitly (rather than just trusting it'll finish in time) means
        // there's nothing left implicit about it.
        if (typeof img.decode === 'function') {
            img.decode().catch(function(e) {
                console.error('Report chart print image failed to decode for ' + canvasId + ':', e);
            });
        }
    } catch (e) {
        console.error('Report chart print image failed for ' + canvasId + ':', e);
    }
}

window.addEventListener('beforeprint', function() {
    document.querySelectorAll('.report-chart-canvas').forEach(function(canvas) {
        syncChartPrintImage(canvas.id);
    });
});

// ── Export with chart images (PDF / Excel) ──
function exportReportWithCharts(format) {
    ensureReportChartsRendered(function() {
        var params = {};
        var f = document.getElementById('reportForm');
        if (f) {
            ['type','from','to','cage','reason'].forEach(function(k) {
                var el = f.elements[k];
                if (el && el.value) params[k] = el.value;
            });
        }

        var chartImages = {};
        var chartsCheckbox = f && f.elements['charts'];
        var hasCharts = chartsCheckbox && chartsCheckbox.checked;
        if (hasCharts) {
            REPORT_CHART_TYPES.forEach(function(type) {
                var canvas = document.getElementById('chart-' + type);
                if (!canvas) return;
                var wrap = document.getElementById('chart-' + type + '-wrap');
                if (wrap && wrap.style.display === 'none') return;
                try {
                    chartImages[type] = canvas.toDataURL('image/png');
                } catch (e) {
                    console.warn('Failed to capture chart image for ' + type + ':', e);
                }
            });
        }

        if (Object.keys(chartImages).length > 0) {
            params.chart_images = chartImages;
        }

        var token = document.querySelector('meta[name="csrf-token"]');
        var overlay = document.getElementById('reportsExportLoadingOverlay');
        var progressBar = document.getElementById('reportsProgressBar');
        if (overlay) overlay.style.display = 'flex';
        if (progressBar) progressBar.style.width = '0%';

        var EXPORT_DURATION = 3000;
        var progressStart = Date.now();
        var progressRaf = null;
        function stepProgress() {
            var elapsed = Date.now() - progressStart;
            var ratio = Math.min(elapsed / EXPORT_DURATION, 1);
            var eased = 1 - Math.pow(1 - ratio, 3);
            var pct = eased * 100;
            if (progressBar) progressBar.style.width = pct + '%';
            if (ratio < 1) {
                progressRaf = requestAnimationFrame(stepProgress);
            }
        }
        progressRaf = requestAnimationFrame(stepProgress);

        fetch('/reports/' + format, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                'Accept': 'application/octet-stream'
            },
            body: JSON.stringify(params)
        })
        .then(function(r) {
            if (!r.ok) throw new Error('Export failed (' + r.status + ')');
            var disposition = r.headers.get('Content-Disposition') || '';
            var match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
            var filename = match ? match[1].replace(/['"]/g, '') : 'layrate_export.' + format;
            return r.blob().then(function(blob) { return { blob: blob, filename: filename }; });
        })
        .then(function(result) {
            var a = document.createElement('a');
            a.href = URL.createObjectURL(result.blob);
            a.download = decodeURIComponent(result.filename);
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        })
        .catch(function(err) {
            console.error('Export failed:', err);
            alert('Export failed. Please try again.');
        })
        .finally(function() {
            if (progressRaf) cancelAnimationFrame(progressRaf);
            if (overlay) overlay.style.display = 'none';
        });
    });
}

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
    // Keep the module-level payload in sync with AJAX updates so that
    // exportReportWithCharts() captures the correct charts even when the
    // user hasn't navigated to the printable (?full=1) view first.
    REPORT_CHARTS_PAYLOAD = data.charts || {};
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

// ── Export link interceptors: PDF and Excel capture chart images before navigating ──
document.addEventListener('click', function(e) {
    var exportLink = e.target.closest('#exportExcelLink, #exportPdfLink');
    if (!exportLink) return;
    e.preventDefault();
    var format = exportLink.id === 'exportExcelLink' ? 'excel' : 'pdf';
    exportReportWithCharts(format);
});

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

})();

// ── Initial chart render ──
// Same pattern as environment/_live-data.blade.php's initEnvCharts and
// analytics.blade.php: bind to turbo:load (fires on every Turbo Drive
// navigation, including this page's own <script> tag being re-evaluated when
// reached via a Turbo-driven link elsewhere on this page) rather than
// relying on a bare top-level call — plain top-level execution only reliably
// covers the very first hard load. Also call immediately when the DOM is
// already parsed (covers this same script re-running mid-session) or on
// DOMContentLoaded otherwise. Subsequent AJAX filter changes render via
// renderReportResults() instead, which always runs well after Chart.js has
// long since loaded.
function initReportCharts() {
    waitForChartJs(function() {
        window.renderReportCharts(REPORT_CHARTS_PAYLOAD);
    });
}

if (!window.__reportChartsLifecycleBound) {
    window.__reportChartsLifecycleBound = true;
    document.addEventListener('turbo:load', initReportCharts);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReportCharts);
} else {
    initReportCharts();
}
</script>
@endpush
