{{-- Summary pills — dashboard gradient KPI design (shared x-kpi-card) --}}
@if($summary !== null)
    @if($type === 'production')
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <x-kpi-card label="Total Eggs" icon="egg" cardGradient="linear-gradient(135deg,#d97706,#C2703E)" delay="0ms" :value="$summary->total_eggs" />
        <x-kpi-card label="Avg HDEP" icon="gauge" cardGradient="linear-gradient(135deg,#0075de,#1D4E8F)" delay="60ms" :value="$summary->avg_hdep" />
        <x-kpi-card label="Total Hens" icon="bird" cardGradient="linear-gradient(135deg,#16a34a,#2D7D46)" delay="120ms" :value="$summary->total_hens" />
        <x-kpi-card label="Days Covered" icon="calendar-days" cardGradient="linear-gradient(135deg,#8B5CF6,#6B4C8A)" delay="180ms" :value="$summary->days" />
    </div>
    @elseif($type === 'feed')
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <x-kpi-card label="Total Consumed" icon="package" cardGradient="linear-gradient(135deg,#16a34a,#15803d)" delay="0ms" :value="$summary->total_kg . ' kg'" />
        <x-kpi-card label="Avg per Day" icon="scale" cardGradient="linear-gradient(135deg,#16a34a,#15803d)" delay="60ms" :value="$summary->avg_per_day . ' kg'" />
        <x-kpi-card label="Batches Used" icon="layers" cardGradient="linear-gradient(135deg,#16a34a,#15803d)" delay="120ms" :value="$summary->batches" />
        <x-kpi-card label="Days Covered" icon="calendar-days" cardGradient="linear-gradient(135deg,#16a34a,#15803d)" delay="180ms" :value="$summary->days" />
    </div>
    @elseif($type === 'environment')
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <x-kpi-card label="Avg Temperature" icon="thermometer" cardGradient="linear-gradient(135deg,#f59e0b,#C2703E)" delay="0ms" :value="$summary->avg_temp" />
        <x-kpi-card label="Avg Humidity" icon="droplets" cardGradient="linear-gradient(135deg,#0d9488,#2C7C91)" delay="60ms" :value="$summary->avg_hum" />
        <x-kpi-card label="Total Readings" icon="radio" cardGradient="linear-gradient(135deg,#0075de,#1D4E8F)" delay="120ms" :value="$summary->readings" />
        <x-kpi-card label="Alert Readings" icon="alert-triangle" cardGradient="linear-gradient(135deg,#dc2626,#9b1c24)" delay="180ms" :value="$summary->alerts" />
    </div>
    @elseif($type === 'egg_stock')
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <x-kpi-card label="Total Stocked" icon="package" cardGradient="linear-gradient(135deg,#d97706,#C2703E)" delay="0ms" :value="$summary->total_stocked" />
        <x-kpi-card label="Batches" icon="layers" cardGradient="linear-gradient(135deg,#8B5CF6,#6B4C8A)" delay="60ms" :value="$summary->batches" />
        <x-kpi-card label="Top Size" icon="award" cardGradient="linear-gradient(135deg,#0075de,#1D4E8F)" delay="120ms" :value="$summary->top_size" />
        <x-kpi-card label="Days Covered" icon="calendar-days" cardGradient="linear-gradient(135deg,#16a34a,#2D7D46)" delay="180ms" :value="$summary->days" />
    </div>
    @elseif($type === 'mortality')
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <x-kpi-card label="Total Deaths" icon="skull" cardGradient="linear-gradient(135deg,#ec4899,#9d174d)" delay="0ms" :value="$summary->total_deaths" />
        <x-kpi-card label="Top Cause" icon="alert-triangle" cardGradient="linear-gradient(135deg,#dc2626,#9b1c24)" delay="60ms" :value="$summary->top_cause" />
        <x-kpi-card label="Most Affected" icon="heart-crack" cardGradient="linear-gradient(135deg,#ec4899,#9d174d)" delay="120ms" :value="$summary->most_affected" />
        <x-kpi-card label="Days Covered" icon="calendar-days" cardGradient="linear-gradient(135deg,#8B5CF6,#6B4C8A)" delay="180ms" :value="$summary->days" />
    </div>
    @endif
@endif
