@extends('layouts.app')
@section('title', 'Analytics')

@section('content')
<div class="space-y-5">

    {{-- ── Page Header ── --}}
    <x-page-header title="Analytics" subtitle="HDEP trends, egg production, and feed correlation charts" />

    {{-- ── Cage Selector ── --}}
    <div class="flex items-center gap-0 border-b overflow-x-auto scrollbar-thin" style="border-color: #e6e6e6;">
        @php $isPerfTab = $isPerformance; @endphp
        <a href="{{ route('analytics', ['cage'=>'performance','period'=>$period]) }}"
           data-cage-tab="performance"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap shrink-0"
           style="border-bottom-color: {{ $isPerfTab ? '#002D5E' : 'transparent' }}; color: {{ $isPerfTab ? '#1f1f1f' : '#6B7280' }};">
            <i data-lucide="gauge" class="w-3.5 h-3.5 inline-block mr-1.5"></i>
            Performance
        </a>
        <a href="{{ route('analytics', ['cage'=>'all','period'=>$period]) }}"
           data-cage-tab="all"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap shrink-0"
           style="border-bottom-color: {{ $isAll ? '#333333' : 'transparent' }}; color: {{ $isAll ? '#1f1f1f' : '#6B7280' }};">
            <i data-lucide="layers" class="w-3.5 h-3.5 inline-block mr-1.5"></i>
            All
        </a>
        @foreach($allCages as $c)
        @php $isActive = $c->cage_code === $cageCode; $cColor = $c->color; @endphp
        <a href="{{ route('analytics', ['cage'=>$c->cage_code,'period'=>$period]) }}"
           data-cage-tab="{{ $c->cage_code }}"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap shrink-0"
           style="border-bottom-color: {{ $isActive ? $cColor : 'transparent' }}; color: {{ $isActive ? '#1f1f1f' : '#6B7280' }};">
            <span class="inline-block w-2 h-2 rounded-full mr-1.5" style="background-color: {{ $cColor }};"></span>
            {{ $c->cage_code }}
        </a>
        @endforeach
    </div>

    {{-- ── Summary KPI Cards (hidden on the Performance tab) ── --}}
    @if(!$isPerformance)
    @php $kpiColor = $isAll ? '#333333' : $cage->color; @endphp
    <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Cage</div>
            <div id="kpi-cage" class="text-2xl font-bold leading-none tracking-[-0.5px]" style="color:{{ $kpiColor }}">{{ $isAll ? 'All Cages' : $cageCode }}</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Breed</div>
            <div id="kpi-breed" class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ $isAll ? 'Mixed' : ($cage->hens->first()?->breed ?? '—') }}</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Avg HDEP</div>
            <div id="kpi-avg-hdep" class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">@if($avgHdep === '-'){{ '-' }}@else{{ $avgHdep }}%@endif</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Best Day</div>
            <div id="kpi-best-day" class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">@if($bestDay === '-'){{ '-' }}@else{{ $bestDay }}%@endif</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Worst Day</div>
            <div id="kpi-worst-day" class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">@if($worstDay === '-'){{ '-' }}@else{{ $worstDay }}%@endif</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Flock Age</div>
            <div id="kpi-flock-age" class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ $isAll ? '—' : ($cage->hens->first() ? $cage->hens->first()->current_age_weeks . ' wks' : '—') }}</div>
        </div>
    </div>
    @endif

    {{-- ── Period Selector ── --}}
    <div class="flex items-center gap-0 border-b overflow-x-auto scrollbar-thin" style="border-color: #e6e6e6;">
        @foreach(['week'=>'Week','month'=>'Month','3months'=>'3 Months'] as $key => $label)
        @php
            $isP = $period === $key;
            $icon = match($key) { 'week' => 'calendar-days', 'month' => 'calendar', '3months' => 'calendar-range', default => 'calendar' };
        @endphp
        <a href="{{ route('analytics', ['cage'=>$cageCode,'period'=>$key]) }}"
           data-period-tab="{{ $key }}"
           class="period-tab px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap shrink-0"
           style="border-bottom-color: {{ $isP ? '#002D5E' : 'transparent' }}; color: {{ $isP ? '#1f1f1f' : '#6B7280' }};">
            <i data-lucide="{{ $icon }}" class="w-3.5 h-3.5 inline-block mr-1.5"></i>
            {{ $label }}
        </a>
        @endforeach
    </div>

    {{-- ── Charts + Summary (lazy) ── --}}
    <div id="analytics-charts-container">
        <turbo-frame id="analytics-charts" src="{{ route('analytics.charts', ['cage' => $cageCode, 'period' => $period]) }}" loading="lazy">
            @include('analytics._charts-skeleton')
        </turbo-frame>
    </div>

</div>

@push('scripts')
<style>
#analytics-charts-container.loading { position: relative; }
#analytics-charts-container.loading::after {
    content: ''; position: absolute; inset: 0; z-index: 10;
    background: rgba(255,255,255,0.6);
    pointer-events: none;
}
#analytics-charts-container.loading .period-tab-active {
    cursor: wait;
}
</style>
<script>
// ── Shared analytics chart renderer ──
window.renderAnalyticsCharts = function(logs, feedLogs, cageColor, isAll, cageCode) {
    var labels, hdeps, eggs;
    var byDate = {};
    logs.forEach(function(l) {
        if (!byDate[l.date]) byDate[l.date] = { eggs: 0, hdepSum: 0, hdepCount: 0 };
        byDate[l.date].eggs += l.eggs;
        byDate[l.date].hdepSum += l.hdep;
        byDate[l.date].hdepCount += 1;
    });
    var sortedDates = Object.keys(byDate).sort();
    labels = sortedDates.map(function(d) { return d.slice(5); });
    hdeps = sortedDates.map(function(d) { return byDate[d].hdepCount > 0 ? Math.round(byDate[d].hdepSum / byDate[d].hdepCount * 10) / 10 : 0; });
    eggs = sortedDates.map(function(d) { return byDate[d].eggs; });

    var hasLogs = logs.length > 0;
    var hasFeedOverlap = hasLogs && feedLogs.some(function(f) { return logs.some(function(l) { return l.date === f.date; }); });

    var hdepWrap = document.getElementById('hdepChartWrap');
    var hdepEmp = document.getElementById('hdepChartEmpty');
    if (hdepWrap && hdepEmp) {
        hdepWrap.style.display = hasLogs ? '' : 'none';
        hdepEmp.style.display = hasLogs ? 'none' : '';
        if (hasLogs) {
            LayRateChart.create('hdepChart', {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{ data: hdeps, borderColor: cageColor, backgroundColor: cageColor+'22', tension: 0.3, pointRadius: 4, fill: true, borderWidth: 2 }]
                },
                options: (function() {
                    var gridColor = '#F0F0EC';
                    return {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { color: gridColor }, ticks: { font: { size: 10 }, autoSkip: true, autoSkipPadding: 12, maxRotation: 45, minRotation: 0 } },
                            y: { grid: { color: gridColor }, ticks: { font: { size: 10 } }, suggestedMin: 0 },
                        }
                    };
                })()
            });
        } else {
            LayRateChart.destroy('hdepChart');
        }
    }

    var eggsWrap = document.getElementById('eggsChartWrap');
    var eggsEmp = document.getElementById('eggsChartEmpty');
    if (eggsWrap && eggsEmp) {
        eggsWrap.style.display = hasLogs ? '' : 'none';
        eggsEmp.style.display = hasLogs ? 'none' : '';
        if (hasLogs) {
            LayRateChart.create('eggsChart', {
                type: 'bar',
                data: { labels: labels, datasets: [{ data: eggs, backgroundColor: cageColor, borderRadius: 3 }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 4, bottom: 4, left: 4, right: 4 } },
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 }, autoSkip: true, autoSkipPadding: 12, maxRotation: 45, minRotation: 0 } },
                        y: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } } },
                    }
                }
            });
        } else {
            LayRateChart.destroy('eggsChart');
        }
    }

    var scatWrap = document.getElementById('feedHdepChartWrap');
    var scatEmp = document.getElementById('feedHdepChartEmpty');
    if (scatWrap && scatEmp) {
        scatWrap.style.display = hasFeedOverlap ? '' : 'none';
        scatEmp.style.display = hasFeedOverlap ? 'none' : '';
        if (hasFeedOverlap) {
            var feedMap = {};
            feedLogs.forEach(function(f) { feedMap[f.date] = f.kg; });
            var scatter = [];
            sortedDates.forEach(function(d) {
                if (feedMap[d] !== undefined) {
                    scatter.push({ x: feedMap[d], y: Math.round(byDate[d].hdepSum / byDate[d].hdepCount * 10) / 10 });
                }
            });
            var maxHdep = scatter.length > 0 ? Math.max(...scatter.map(function(p) { return p.y; })) : 0;
            LayRateChart.create('feedHdepChart', {
                type: 'scatter',
                data: { datasets: [{ data: scatter, backgroundColor: cageColor, pointRadius: 6 }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 4, bottom: 4, left: 4, right: 4 } },
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { type: 'linear', grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } }, title: { display: true, text: 'kg', font: { size: 10 } } },
                        y: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } }, title: { display: true, text: '%', font: { size: 10 } }, suggestedMin: 0, suggestedMax: Math.ceil(maxHdep / 10) * 10 + 10 },
                    }
                }
            });
        } else {
            LayRateChart.destroy('feedHdepChart');
        }
    }
};

// ── Shared fetch-and-render helper ──
// Guards against out-of-order responses: rapidly clicking multiple tabs fires
// multiple concurrent fetches, and without sequencing, whichever happens to
// resolve *last* wins the DOM update even if it's not the most recently
// requested tab — producing charts/labels that don't match the active tab
// (or a destroyed chart, if the stale response's period has no data).
var __analyticsRequestSeq = 0;
function analyticsFetch(fetchUrl, url, afterUpdate) {
    var container = document.getElementById('analytics-charts-container');
    if (container) container.classList.add('loading');

    var seq = ++__analyticsRequestSeq;

    fetch(fetchUrl)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (seq !== __analyticsRequestSeq) return; // a newer request superseded this one

            // Update KPI cards
            var el = document.getElementById('kpi-avg-hdep');
            if (el) el.textContent = data.kpi.avgHdep === '-' ? '-' : data.kpi.avgHdep + '%';
            el = document.getElementById('kpi-best-day');
            if (el) el.textContent = data.kpi.bestDay === '-' ? '-' : data.kpi.bestDay + '%';
            el = document.getElementById('kpi-worst-day');
            if (el) el.textContent = data.kpi.worstDay === '-' ? '-' : data.kpi.worstDay + '%';
            el = document.getElementById('kpi-breed');
            if (el) el.textContent = data.kpi.breed;
            el = document.getElementById('kpi-flock-age');
            if (el) el.textContent = data.kpi.flockAge;

            // Update charts — every switch rebuilds from a clean Chart.js module first,
            // not just when a chart is detected broken. Requested directly: refresh on
            // every tab change (cage, period), not a conditional self-heal.
            var finishRender = function() {
                if (seq !== __analyticsRequestSeq) return; // superseded while we were reloading
                window.renderAnalyticsCharts(data.charts.logs, data.charts.feedLogs, data.cageColor, data.isAll, data.cageCode);

                // Tab-specific updates
                if (typeof afterUpdate === 'function') afterUpdate(data);

                // Update URL
                history.replaceState({}, '', url);

                // Remove loading overlay
                if (container) container.classList.remove('loading');
            };
            if (window.LayRateChart && typeof window.LayRateChart.prepareForRender === 'function') {
                window.LayRateChart.prepareForRender().then(finishRender);
            } else {
                finishRender();
            }
        })
        .catch(function(err) {
            if (seq !== __analyticsRequestSeq) return;
            console.error('Analytics update failed:', err);
            if (container) container.classList.remove('loading');
        });
}

// ── Wait for the lazy charts frame's first load before firing an AJAX update ──
// Tab clicks used to gate on document.getElementById('hdepChart') existing as a
// proxy for "charts frame has loaded" — if a tab was clicked before that lazy
// frame finished its initial fetch (very easy to do: click a tab the instant
// you land on the page), the canvas didn't exist yet, the guard bailed out
// *before* calling e.preventDefault(), and the click fell through to the tab's
// raw href — a full page reload instead of the intended instant AJAX swap.
// Waiting for the frame's own 'complete' state (or its turbo:frame-load event
// if it's still loading) fixes the race without ever giving up preventDefault.
function whenAnalyticsFrameReady(cb) {
    var frame = document.querySelector('turbo-frame#analytics-charts');
    if (frame && frame.hasAttribute('complete')) { cb(); return; }
    document.addEventListener('turbo:frame-load', function handler(e) {
        if (e.target && e.target.id === 'analytics-charts') {
            document.removeEventListener('turbo:frame-load', handler);
            cb();
        }
    });
}

// ── Period tab switcher (AJAX) ──
// __analyticsPeriod/__analyticsCage are the source of truth for "what's currently
// selected" — updated synchronously the instant a tab is clicked. Reading
// window.location.search instead (as this used to) is a real, live-confirmed bug:
// the URL is only updated via history.replaceState() *after* analyticsFetch's
// response comes back, so clicking a second tab before the first request resolves
// (very easy to hit — this dev server processes requests one at a time, so a
// single request can take seconds) reads the *stale* pre-click URL and silently
// falls back to the 'all'/'week' defaults instead of the just-selected tab. Root
// cause of "clicked CAGE-A then Month, ended up rendering All Cages' data".
var __analyticsPeriod = (new URLSearchParams(window.location.search)).get('period') || 'week';
var __analyticsCage = (new URLSearchParams(window.location.search)).get('cage') || 'performance';

// Lets LayRateChart's self-heal (layouts/app.blade.php) recover a chart that failed
// to paint by re-running the exact same path a manual tab click would. Live-tested
// two versions of this: calling analyticsFetch() directly did NOT reliably clear the
// stuck-paint state, but dispatching a real .click() on the tab element — going
// through the actual handler, tab-styling DOM updates included — did, consistently.
// Bypasses the "already active" guard with a throwaway sentinel value so the click
// isn't a no-op just because the cage/period didn't change.
// Re-registered every script run (not just once) so it always closes over the
// current __analyticsCage, not a stale one from a prior render.
if (window.LayRateChart) {
    window.LayRateChart.registerRecoveryHook(function() {
        if (__analyticsCage === 'performance') return; // no charts to heal in Performance mode
        var cageTab = document.querySelector('[data-cage-tab="' + __analyticsCage + '"]');
        if (!cageTab) return;
        __analyticsCage = '__layratechart_recovery__';
        cageTab.click();
    });
}

// This whole script re-executes on every full Turbo Drive visit to /analytics
// (sidebar nav away-and-back, browser back/forward) — confirmed live: without this
// guard, document.addEventListener() below stacks a fresh listener on every visit,
// so a single tab click fires one AJAX request per stacked listener (observed 2x
// after just one sidebar round-trip). Same one-time-bind convention already used
// throughout this codebase (see eggs/stocks.blade.php's __eggStocksListenersRegistered).
if (!window.__analyticsListenersBound) {
    window.__analyticsListenersBound = true;

    document.addEventListener('click', function(e) {
        var tab = e.target.closest('[data-period-tab]');
        if (!tab) return;
        // Performance mode uses full-page navigation (HTML links carry ?cage=performance)
        // rather than the per-cage AJAX charts.
        if (__analyticsCage === 'performance') return;
        e.preventDefault();
        var period = tab.dataset.periodTab;
        if (period === __analyticsPeriod) return;
        __analyticsPeriod = period;
        var cage = __analyticsCage;
        // Build URL dynamically — tab href is stale after AJAX cage switches
        var url = window.location.pathname + '?cage=' + encodeURIComponent(cage) + '&period=' + encodeURIComponent(period);

        // Update active tab styling immediately
        document.querySelectorAll('[data-period-tab]').forEach(function(t) {
            var act = t.dataset.periodTab === period;
            t.style.borderBottomColor = act ? '#002D5E' : 'transparent';
            t.style.color = act ? '#1f1f1f' : '#6B7280';
        });

        whenAnalyticsFrameReady(function() {
            analyticsFetch('/analytics/data?cage=' + encodeURIComponent(cage) + '&period=' + encodeURIComponent(period), url, function(data) {
                var dayCount = period === 'week' ? '7' : (period === 'month' ? '30' : '90');
                var titleEl = document.getElementById('chart-title-period');
                if (titleEl) titleEl.textContent = dayCount;
            });
        });
    });

    // ── Reconcile frame src with URL on page restore (back/forward navigation) ──
    document.addEventListener('turbo:load', function() {
        var frame = document.querySelector('turbo-frame#analytics-charts');
        if (!frame) return;
        var frameSrc = frame.getAttribute('src');
        if (!frameSrc) return;
        var params = new URLSearchParams(window.location.search);
        var urlCage = params.get('cage') || 'performance';
        var urlPeriod = params.get('period') || 'week';
        var frameUrl = new URL(frameSrc, window.location.origin);
        if (frameUrl.searchParams.get('cage') !== urlCage || frameUrl.searchParams.get('period') !== urlPeriod) {
            frameUrl.searchParams.set('cage', urlCage);
            frameUrl.searchParams.set('period', urlPeriod);
            frame.setAttribute('src', frameUrl.pathname + frameUrl.search);
        }
    });

    // ── Cage scope tab switcher (AJAX) ──
    document.addEventListener('click', function(e) {
        var tab = e.target.closest('[data-cage-tab]');
        if (!tab) return;
        // Performance mode uses full-page navigation (its links carry ?cage=performance)
        // rather than the per-cage AJAX charts; also let period links inside the
        // Performance view navigate normally.
        if (tab.dataset.cageTab === 'performance' || __analyticsCage === 'performance') return;
        e.preventDefault();
        if (tab.dataset.cageTab === __analyticsCage) return;

        var cage = tab.dataset.cageTab;
        __analyticsCage = cage;
        var period = __analyticsPeriod;
        var cageColors = window.CAGE_COLORS || {};

        // Build URL preserving current period
        var url = window.location.pathname + '?cage=' + encodeURIComponent(cage) + '&period=' + encodeURIComponent(period);

        // Update cage tab styling immediately
        document.querySelectorAll('[data-cage-tab]').forEach(function(t) {
            var act = t.dataset.cageTab === cage;
            var color = t.dataset.cageTab === 'all' ? '#333333' : (cageColors[t.dataset.cageTab] || '#6B7280');
            t.style.borderBottomColor = act ? color : 'transparent';
            t.style.color = act ? '#1f1f1f' : '#6B7280';
        });

        whenAnalyticsFrameReady(function() {
            analyticsFetch('/analytics/data?cage=' + encodeURIComponent(cage) + '&period=' + encodeURIComponent(period), url, function(data) {
                // Update cage name KPI
                var kpiCage = document.getElementById('kpi-cage');
                if (kpiCage) {
                    kpiCage.textContent = data.isAll ? 'All Cages' : data.cageCode;
                    kpiCage.style.color = data.cageColor;
                }

                // Update all three chart title display labels
                var label = data.isAll ? 'FARM' : data.cageCode;
                var hdepTitle = document.getElementById('chart-title-label-hdep');
                if (hdepTitle) hdepTitle.textContent = label;
                var eggsTitle = document.getElementById('chart-title-label-eggs');
                if (eggsTitle) eggsTitle.textContent = label;
                var feedTitle = document.getElementById('chart-title-label-feed');
                if (feedTitle) feedTitle.textContent = label;
            });
        });
    });
}
</script>
@endpush
@endsection
