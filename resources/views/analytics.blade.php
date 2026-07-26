@extends('layouts.app')
@section('title', 'Analytics')

@section('content')
<div class="space-y-5">

    {{-- ── Page Header ── --}}
    <x-page-header title="Analytics" subtitle="HDEP trends, egg production, and feed correlation charts" />

    {{-- ── Cage Selector ── --}}
    <div class="flex items-center gap-0 border-b overflow-x-auto scrollbar-thin" style="border-color: #e6e6e6;">
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

    {{-- ── Summary KPI Cards ── --}}
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

    var hdepCvs = document.getElementById('hdepChart');
    var hdepEmp = document.getElementById('hdepChartEmpty');
    if (hdepCvs && hdepEmp) {
        hdepCvs.style.display = hasLogs ? '' : 'none';
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
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { color: gridColor }, ticks: { font: { size: 10 }, autoSkip: true, autoSkipPadding: 12, maxRotation: 45, minRotation: 0 } },
                            y: { grid: { color: gridColor }, ticks: { font: { size: 10 } }, min: 0, max: 100 },
                        }
                    };
                })()
            });
        } else {
            LayRateChart.destroy('hdepChart');
        }
    }

    var eggsCvs = document.getElementById('eggsChart');
    var eggsEmp = document.getElementById('eggsChartEmpty');
    if (eggsCvs && eggsEmp) {
        eggsCvs.style.display = hasLogs ? '' : 'none';
        eggsEmp.style.display = hasLogs ? 'none' : '';
        if (hasLogs) {
            LayRateChart.create('eggsChart', {
                type: 'bar',
                data: { labels: labels, datasets: [{ data: eggs, backgroundColor: cageColor, borderRadius: 3 }] },
                options: {
                    responsive: true,
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

    var scatCvs = document.getElementById('feedHdepChart');
    var scatEmp = document.getElementById('feedHdepChartEmpty');
    if (scatCvs && scatEmp) {
        scatCvs.style.display = hasFeedOverlap ? '' : 'none';
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
            LayRateChart.create('feedHdepChart', {
                type: 'scatter',
                data: { datasets: [{ data: scatter, backgroundColor: cageColor, pointRadius: 6 }] },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { type: 'linear', grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } }, title: { display: true, text: 'kg', font: { size: 10 } } },
                        y: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } }, title: { display: true, text: '%', font: { size: 10 } }, min: 0, max: 100 },
                    }
                }
            });
        } else {
            LayRateChart.destroy('feedHdepChart');
        }
    }
};

// ── Shared fetch-and-render helper ──
function analyticsFetch(fetchUrl, url, afterUpdate) {
    var container = document.getElementById('analytics-charts-container');
    if (container) container.classList.add('loading');

    fetch(fetchUrl)
        .then(function(r) { return r.json(); })
        .then(function(data) {
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

            // Update charts
            window.renderAnalyticsCharts(data.charts.logs, data.charts.feedLogs, data.cageColor, data.isAll, data.cageCode);

            // Tab-specific updates
            if (typeof afterUpdate === 'function') afterUpdate(data);

            // Update URL
            history.replaceState({}, '', url);

            // Remove loading overlay
            if (container) container.classList.remove('loading');
        })
        .catch(function(err) {
            console.error('Analytics update failed:', err);
            if (container) container.classList.remove('loading');
        });
}

// ── Period tab switcher (AJAX) ──
var __analyticsPeriod = (new URLSearchParams(window.location.search)).get('period') || 'week';
document.addEventListener('click', function(e) {
    var tab = e.target.closest('[data-period-tab]');
    if (!tab) return;
    if (!document.getElementById('hdepChart')) return;
    e.preventDefault();
    var period = tab.dataset.periodTab;
    if (period === __analyticsPeriod) return;
    __analyticsPeriod = period;
    var params = new URLSearchParams(window.location.search);
    var cage = params.get('cage') || 'all';
    // Build URL dynamically — tab href is stale after AJAX cage switches
    var url = window.location.pathname + '?cage=' + encodeURIComponent(cage) + '&period=' + encodeURIComponent(period);

    // Update active tab styling immediately
    document.querySelectorAll('[data-period-tab]').forEach(function(t) {
        var act = t.dataset.periodTab === period;
        t.style.borderBottomColor = act ? '#002D5E' : 'transparent';
        t.style.color = act ? '#1f1f1f' : '#6B7280';
    });

    analyticsFetch('/analytics/data?cage=' + encodeURIComponent(cage) + '&period=' + encodeURIComponent(period), url, function(data) {
        var dayCount = period === 'week' ? '7' : (period === 'month' ? '30' : '90');
        var titleEl = document.getElementById('chart-title-period');
        if (titleEl) titleEl.textContent = dayCount;
    });
});

// ── Reconcile frame src with URL on page restore (back/forward navigation) ──
document.addEventListener('turbo:load', function() {
    var frame = document.querySelector('turbo-frame#analytics-charts');
    if (!frame) return;
    var frameSrc = frame.getAttribute('src');
    if (!frameSrc) return;
    var params = new URLSearchParams(window.location.search);
    var urlCage = params.get('cage') || 'all';
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
    if (!document.getElementById('hdepChart')) return;
    e.preventDefault();
    var params = new URLSearchParams(window.location.search);
    var currentCage = params.get('cage') || 'all';
    if (tab.dataset.cageTab === currentCage) return;

    var cage = tab.dataset.cageTab;
    var period = params.get('period') || 'week';
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
</script>
@endpush
@endsection
