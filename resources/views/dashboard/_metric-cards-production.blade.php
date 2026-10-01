<turbo-frame id="dashboard-stats-production">
    <div class="space-y-2">
        <div>
            <x-section-label title="Production" icon="factory">
                @if($kpiAsOf ?? null)<x-slot:meta>· as of {{ $kpiAsOf }}</x-slot:meta>@endif
            </x-section-label>
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <x-kpi-card
                    label="Total Hens"
                    icon="bird"
                    cardGradient="linear-gradient(135deg,#16a34a,#2D7D46)"
                    delay="0ms"
                    :href="route('chickens.index')"
                    kpi="hens"
                    ariaLabel="Go to Hens"
                    infoLabel="Hens per cage breakdown"
                    :target="$totalHens"
                    :decimals="0"
                />

                <x-kpi-card
                    label="Today's HDEP"
                    icon="gauge"
                    cardGradient="linear-gradient(135deg,var(--color-navy),var(--color-info))"
                    delay="60ms"
                    :href="route('eggs.logging')"
                    kpi="hdep"
                    ariaLabel="Go to Egg Logging"
                    infoLabel="HDEP per cage breakdown"
                    :target="$todayHdep"
                    :decimals="1"
                    suffix="%"
                >
                    <div class="{{ $hdepDelta >= 0 ? 'trend-pill-up' : 'trend-pill-down' }}">{{ $hdepDelta >= 0 ? '▲' : '▼' }} {{ abs($hdepDelta) }}% vs yesterday</div>
                </x-kpi-card>

                <x-kpi-card
                    label="Eggs Today"
                    icon="egg"
                    cardGradient="linear-gradient(135deg,#d97706,#C2703E)"
                    delay="120ms"
                    :href="route('eggs.logging')"
                    kpi="eggs"
                    ariaLabel="Go to Egg Logging"
                    infoLabel="Eggs per cage breakdown"
                    :target="$eggsToday"
                    :decimals="0"
                >
                    <div class="{{ $eggsDelta >= 0 ? 'trend-pill-up' : 'trend-pill-down' }}">{{ $eggsDelta >= 0 ? '▲' : '▼' }} {{ abs($eggsDelta) }} vs yesterday</div>
                </x-kpi-card>

                <x-kpi-card
                    label="Lifetime Eggs"
                    icon="layers"
                    cardGradient="linear-gradient(135deg,#8B5CF6,#6B4C8A)"
                    delay="180ms"
                    :href="route('egg-production-history')"
                    kpi="lifetime-eggs"
                    ariaLabel="Go to Egg Production History"
                    infoLabel="Lifetime eggs per cage breakdown"
                    :target="$lifetimeEggs"
                    :decimals="0"
                >
                    <div class="trend-pill-up">▲ +{{ number_format($eggsToday) }} today</div>
                </x-kpi-card>
            </div>
        </div>
    </div>
    <script>
    window.KPI_DATA = Object.assign(window.KPI_DATA || {}, {
        hens: { title: 'Hens per Cage', rows: {!! $cages->map(fn($c) => ['label' => $c->cage_code, 'color' => $c->color, 'bgColor' => $c->colorSoft, 'value' => number_format($c->hen_count) . ' hens · ' . $c->breed])->values()->toJson() !!} },
        hdep: { title: "Today's HDEP by Cage", rows: {!! $cages->map(fn($c) => ['label' => $c->cage_code, 'color' => $c->color, 'bgColor' => $c->colorSoft, 'value' => number_format($c->today_hdep, 1) . '%'])->values()->toJson() !!} },
        eggs: { title: 'Eggs Collected by Cage', rows: {!! $cages->map(fn($c) => ['label' => $c->cage_code, 'color' => $c->color, 'bgColor' => $c->colorSoft, 'value' => number_format($c->today_eggs) . ' eggs'])->values()->toJson() !!} },
        'lifetime-eggs': { title: 'Lifetime Eggs by Cage', rows: {!! $cages->map(fn($c) => ['label' => $c->cage_code, 'color' => $c->color, 'bgColor' => $c->colorSoft, 'value' => number_format($c->productionLogs->sum('egg_count')) . ' eggs'])->values()->toJson() !!} },
    });
    if (typeof bindKpiCards === 'function') bindKpiCards(document.getElementById('dashboard-stats-production'));
    (function(){ var root=document.getElementById('dashboard-stats-production'); if(!root) return; root.querySelectorAll('.kpi-count').forEach(function(el){ var target=parseFloat(el.dataset.target||'0'); var decimals=parseInt(el.dataset.decimals||'0',10); if(isNaN(target)||target<=0){ el.textContent=decimals>0?'0.0':'0'; return; } var duration=900,start=null; function fmt(v){ return decimals>0? v.toFixed(decimals) : Math.round(v).toLocaleString(); } function step(ts){ if(!start) start=ts; var p=Math.min((ts-start)/duration,1); var eased=1-Math.pow(1-p,3); el.textContent=fmt(target*eased); if(p<1) requestAnimationFrame(step); } requestAnimationFrame(step); }); })();
    </script>
</turbo-frame>
