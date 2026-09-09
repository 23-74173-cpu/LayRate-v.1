<turbo-frame id="dashboard-stats-environment">
    <div class="space-y-2">
        <div>
            <h3 class="text-[10px] font-semibold uppercase tracking-[0.125px] text-[#6B7280] mb-2">
                <i data-lucide="heart-pulse" class="w-4 h-4 inline-block mr-1.5 -mt-0.5" style="color:#0891b2;"></i>
                Environment &amp; Health
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-2 gap-3">
                <x-kpi-card
                    label="Average Coop Temperature"
                    icon="thermometer"
                    cardGradient="linear-gradient(135deg,#f59e0b,#C2703E)"
                    delay="120ms"
                    :href="route('environment')"
                    kpi="env"
                    ariaLabel="Go to Environment"
                    infoLabel="Environment per cage breakdown"
                    :target="$avgTemp"
                    :decimals="1"
                    suffix="°"
                />

                <x-kpi-card
                    label="Average Humidity"
                    icon="droplets"
                    cardGradient="linear-gradient(135deg,#0d9488,#2C7C91)"
                    delay="160ms"
                    :href="route('environment')"
                    kpi="env"
                    ariaLabel="Go to Environment"
                    infoLabel="Environment per cage breakdown"
                    :target="$avgHum"
                    :decimals="1"
                    suffix="%"
                />
            </div>
        </div>
    </div>
    <script>
    window.KPI_DATA = Object.assign(window.KPI_DATA || {}, {
        env: { title: 'Environment by Cage', rows: {!! $liveReadings->map(fn($r) => ['label' => $r->cage, 'color' => $r->color, 'bgColor' => $r->colorSoft, 'value' => $r->temp . ' · ' . $r->hum . ' · ' . $r->status])->values()->toJson() !!} },
    });
    if (typeof bindKpiCards === 'function') bindKpiCards(document.getElementById('dashboard-stats-environment'));
    (function(){ var root=document.getElementById('dashboard-stats-environment'); if(!root) return; root.querySelectorAll('.kpi-count').forEach(function(el){ var target=parseFloat(el.dataset.target||'0'); var decimals=parseInt(el.dataset.decimals||'0',10); if(isNaN(target)||target<=0){ el.textContent=decimals>0?'0.0':'0'; return; } var duration=900,start=null; function fmt(v){ return decimals>0? v.toFixed(decimals) : Math.round(v).toLocaleString(); } function step(ts){ if(!start) start=ts; var p=Math.min((ts-start)/duration,1); var eased=1-Math.pow(1-p,3); el.textContent=fmt(target*eased); if(p<1) requestAnimationFrame(step); } requestAnimationFrame(step); }); })();
    </script>
</turbo-frame>
