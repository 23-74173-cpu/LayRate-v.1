{{-- Report summary — plain label: value text, no scorecard widgets.
     Values already carry their correct unit of measurement, formatted once
     in ReportController::buildSummary() so this view and the PDF both read
     an already-correct string. --}}
@if($summary !== null)
    @php
    $fields = match($type) {
        'production'  => ['Total Eggs' => $summary->total_eggs, 'Avg HDEP' => $summary->avg_hdep, 'Total Hens' => $summary->total_hens, 'Days Covered' => $summary->days],
        'feed'        => ['Total Consumed' => $summary->total_kg, 'Avg per Day' => $summary->avg_per_day, 'Batches Used' => $summary->batches, 'Days Covered' => $summary->days],
        'environment' => ['Avg Temperature' => $summary->avg_temp, 'Avg Humidity' => $summary->avg_hum, 'Total Readings' => $summary->readings, 'Alert Readings' => $summary->alerts],
        'egg_stock'   => ['Total Stocked' => $summary->total_stocked, 'Batches' => $summary->batches, 'Top Size' => $summary->top_size, 'Days Covered' => $summary->days],
        'mortality'   => ['Total Deaths' => $summary->total_deaths, 'Top Cause' => $summary->top_cause, 'Most Affected' => $summary->most_affected, 'Days Covered' => $summary->days],
        default       => [],
    };
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2 mb-6 text-sm">
        @foreach($fields as $label => $value)
        <div>
            <span class="font-semibold" style="color: #1f1f1f;">{{ $label }}:</span>
            <span style="color: #333333;">{{ $value }}</span>
        </div>
        @endforeach
    </div>
@endif
