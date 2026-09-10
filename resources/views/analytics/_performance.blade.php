{{-- @deprecated Scheduled for removal in Phase 4 of the Dashboard/Analytics consolidation. --}}
<turbo-frame id="analytics-charts">
    @include('dashboard._cage-performance-content', [
        'cages' => $cages,
        'targetDate' => $targetDate,
        'cageCode' => $cageCode ?? null,
        'chartRenderFn' => 'renderPerformanceCharts',
    ])
</turbo-frame>
