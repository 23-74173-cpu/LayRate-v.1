<turbo-frame id="dashboard-cage-performance">
    @include('dashboard._cage-performance-content', [
        'cages' => $cages,
        'targetDate' => $targetDate,
        'cageCode' => $cageCode ?? null,
    ])
</turbo-frame>
