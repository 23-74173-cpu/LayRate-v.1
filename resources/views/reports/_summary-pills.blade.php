{{-- Summary pills — shared by the preview table and the printable document --}}
{{-- Migrated to <x-kpi-card variant="plain"> per audit: metadata summary alongside report output, not primary KPI --}}
@if($summary !== null)
    @if($type === 'production')
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-kpi-card variant="plain" label="Total Eggs" :value="$summary->total_eggs" />
        <x-kpi-card variant="plain" label="Avg HDEP" :value="$summary->avg_hdep" />
        <x-kpi-card variant="plain" label="Total Hens" :value="$summary->total_hens" />
        <x-kpi-card variant="plain" label="Days Covered" :value="$summary->days" />
    </div>
    @elseif($type === 'feed')
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-kpi-card variant="plain" label="Total Consumed" :value="$summary->total_kg . ' kg'" />
        <x-kpi-card variant="plain" label="Avg per Day" :value="$summary->avg_per_day . ' kg'" />
        <x-kpi-card variant="plain" label="Batches Used" :value="$summary->batches" />
        <x-kpi-card variant="plain" label="Days Covered" :value="$summary->days" />
    </div>
    @elseif($type === 'environment')
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-kpi-card variant="plain" label="Avg Temperature" :value="$summary->avg_temp" />
        <x-kpi-card variant="plain" label="Avg Humidity" :value="$summary->avg_hum" />
        <x-kpi-card variant="plain" label="Total Readings" :value="$summary->readings" />
        <x-kpi-card variant="plain" label="Alert Readings" :value="$summary->alerts" />
    </div>
    @elseif($type === 'egg_stock')
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-kpi-card variant="plain" label="Total Stocked" :value="$summary->total_stocked" />
        <x-kpi-card variant="plain" label="Batches" :value="$summary->batches" />
        <x-kpi-card variant="plain" label="Top Size" :value="$summary->top_size" />
        <x-kpi-card variant="plain" label="Days Covered" :value="$summary->days" />
    </div>
    @elseif($type === 'mortality')
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-kpi-card variant="plain" label="Total Deaths" :value="$summary->total_deaths" />
        <x-kpi-card variant="plain" label="Top Cause" :value="$summary->top_cause" />
        <x-kpi-card variant="plain" label="Most Affected" :value="$summary->most_affected" />
        <x-kpi-card variant="plain" label="Days Covered" :value="$summary->days" />
    </div>
    @endif
@endif
