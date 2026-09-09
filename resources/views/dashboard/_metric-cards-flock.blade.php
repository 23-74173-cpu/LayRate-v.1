<turbo-frame id="dashboard-stats-flock">
    <div class="space-y-2">
        <div>
            <h3 class="text-[10px] font-semibold uppercase tracking-[0.125px] text-[#6B7280] mb-2">
                <i data-lucide="users" class="w-4 h-4 inline-block mr-1.5 -mt-0.5" style="color:#7c3aed;"></i>
                Flock &amp; Mortality
                @if($kpiAsOf ?? null)<span class="normal-case tracking-normal font-medium text-[#9ca3af]">· as of {{ $kpiAsOf }}</span>@endif
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @php
                    $livabilityDenom = $totalHens + $mortalityTodayTotal;
                    $livabilityToday = $livabilityDenom > 0 ? round(100 * $totalHens / $livabilityDenom, 1) : 100.0;
                @endphp

                <x-kpi-card
                    class="col-span-2 sm:col-span-1"
                    label="Mortality Today"
                    icon="heart-crack"
                    cardGradient="linear-gradient(135deg,#ec4899,#9d174d)"
                    delay="0ms"
                    :href="route('chickens.index', ['tab' => 'mortality'])"
                    kpi="mortality"
                    ariaLabel="Go to Mortality"
                    infoLabel="Mortality per cage breakdown"
                    :value="number_format($mortalityTodayTotal)"
                />

                <x-kpi-card
                    label="Livability"
                    icon="shield-check"
                    cardGradient="linear-gradient(135deg,#16a34a,#2D7D46)"
                    delay="60ms"
                    :target="$livabilityToday"
                    :decimals="1"
                    suffix="%"
                />

                <x-kpi-card
                    label="Yesterday's Mortality"
                    icon="calendar-x"
                    cardGradient="linear-gradient(135deg,#d97706,#C2703E)"
                    delay="120ms"
                    :href="route('chickens.index', ['tab' => 'mortality'])"
                    :value="number_format($yesterdayMortalityTotal)"
                />
            </div>
        </div>
    </div>
    <script>
    window.KPI_DATA = Object.assign(window.KPI_DATA || {}, {
        mortality: { title: 'Mortality by Cage{{ ($mortalityDays ?? 1) > 1 ? " (Last {$mortalityDays} Days)" : "" }}', rows: {!! $cages->map(function ($cage) use ($mortalityToday) { $count = $mortalityToday[$cage->cage_code] ?? 0; return ['label' => $cage->cage_code, 'color' => $cage->color, 'bgColor' => $cage->colorSoft, 'value' => $count . ' ' . Str::plural('hen', $count)]; })->values()->toJson() !!} },
    });
    if (typeof bindKpiCards === 'function') bindKpiCards(document.getElementById('dashboard-stats-flock'));
    (function(){ var root=document.getElementById('dashboard-stats-flock'); if(!root) return; root.querySelectorAll('.kpi-count').forEach(function(el){ var target=parseFloat(el.dataset.target||'0'); var decimals=parseInt(el.dataset.decimals||'0',10); if(isNaN(target)||target<=0){ el.textContent=decimals>0?'0.0':'0'; return; } var duration=900,start=null; function fmt(v){ return decimals>0? v.toFixed(decimals) : Math.round(v).toLocaleString(); } function step(ts){ if(!start) start=ts; var p=Math.min((ts-start)/duration,1); var eased=1-Math.pow(1-p,3); el.textContent=fmt(target*eased); if(p<1) requestAnimationFrame(step); } requestAnimationFrame(step); }); })();
    </script>
</turbo-frame>