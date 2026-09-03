<turbo-frame id="dashboard-cage-performance">
    @include('dashboard._cage-performance-content', [
        'cages' => $cages,
        'days' => $days,
        'cageCode' => $cageCode ?? null,
        'showDayFilter' => false,
    ])
</turbo-frame>
