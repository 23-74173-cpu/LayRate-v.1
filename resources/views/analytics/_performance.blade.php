<turbo-frame id="analytics-charts">
    @include('dashboard._cage-performance-content', [
        'cages' => $cages,
        'days' => $days,
        'cageCode' => $cageCode ?? null,
        'showDayFilter' => false,
        'chartRenderFn' => 'renderPerformanceCharts',
    ])
</turbo-frame>
