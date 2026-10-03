<turbo-frame id="dashboard-stats-feed">
    <div class="space-y-2">
        <div>
            <x-section-label title="Feed & Nutrition" icon="wheat">
                @if($kpiAsOf ?? null)<x-slot:meta>· as of {{ $kpiAsOf }}</x-slot:meta> @elseif($feedAsOf ?? null)<x-slot:meta>· last logged {{ $feedAsOf }}</x-slot:meta> @endif
            </x-section-label>
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <x-kpi-card
                    label="Avg CP% This Week" infoKey="feed.avg-cp"
                    icon="flask-conical"
                    cardGradient="linear-gradient(135deg,#16a34a,#15803d)"
                    delay="240ms"
                    :href="route('feed')"
                    kpi="feed-avg-cp"
                    ariaLabel="Go to Feed"
                    infoLabel="Avg CP% breakdown"
                    :value="number_format($avgCp, 1) . '%'"
                />

                <x-kpi-card
                    label="Avg Feed/Cage/Day" infoKey="feed.avg-per-cage-day"
                    icon="scale"
                    cardGradient="linear-gradient(135deg,#16a34a,#15803d)"
                    delay="280ms"
                    :href="route('feed')"
                    kpi="feed-avg-cage-day"
                    ariaLabel="Go to Feed"
                    infoLabel="Avg feed per cage breakdown"
                    :target="$avgFeedPerCage"
                    :decimals="1"
                />

                <x-kpi-card
                    label="Total Feed Used" infoKey="feed.total-week"
                    icon="package"
                    cardGradient="linear-gradient(135deg,#16a34a,#15803d)"
                    delay="320ms"
                    :href="route('feed')"
                    kpi="feed-total-week"
                    ariaLabel="Go to Feed"
                    infoLabel="Total feed used per cage breakdown"
                    :target="round($totalFeedWeek, 1)"
                    :decimals="1"
                    :todayCount="$feedTodayKg"
                    :todayDecimals="1"
                    todaySuffix=" kg"
                />

                <x-kpi-card
                    label="Feed Cost This Month" infoKey="feed.cost-month"
                    icon="banknote"
                    cardGradient="linear-gradient(135deg,#16a34a,#15803d)"
                    delay="360ms"
                    :href="route('feed')"
                    kpi="feed-cost-month"
                    ariaLabel="Go to Feed"
                    infoLabel="Feed cost per cage breakdown"
                    :value="$totalFeedCostMonth !== null && $totalFeedCostMonth > 0 ? '₱' . number_format($totalFeedCostMonth, 2) : null"
                    :todayCount="$feedCostToday"
                    :todayDecimals="2"
                    todayPrefix="₱"
                />
            </div>
        </div>
    </div>
    <script>
    window.KPI_DATA = Object.assign(window.KPI_DATA || {}, {
        'feed-avg-cp': { title: 'Crude Protein % by Batch', rows: {!! $allBatches->map(fn($b) => ['label' => $b->batch_code . ($b->brand ? ' (' . $b->brand . ')' : ''), 'color' => '#16a34a', 'bgColor' => '#e6f6ee', 'value' => number_format($b->crude_protein, 1) . '%'])->values()->toJson() !!} },
        'feed-avg-cage-day': { title: 'Avg Feed/Cage/Day (7-Day)', rows: {!! $feedWeekByCage->map(fn($r) => ['label' => $r->cage_code, 'color' => $r->color, 'bgColor' => $r->color_soft, 'value' => number_format(round($r->feed_kg / 7, 1), 1) . ' kg/day'])->values()->toJson() !!} },
        'feed-total-week': { title: 'Total Feed Used This Week by Cage', rows: {!! $feedWeekByCage->map(fn($r) => ['label' => $r->cage_code, 'color' => $r->color, 'bgColor' => $r->color_soft, 'value' => number_format($r->feed_kg, 2) . ' kg'])->values()->toJson() !!} },
        'feed-cost-month': { title: 'Feed Cost This Month by Cage', rows: {!! $feedCostByCage->map(fn($r) => ['label' => $r->cage_code, 'color' => $r->color, 'bgColor' => $r->color_soft, 'value' => '₱' . number_format($r->cost, 2)])->values()->toJson() !!} },
    });
    if (typeof bindKpiCards === 'function') bindKpiCards(document.getElementById('dashboard-stats-feed'));
    (function(){ var root=document.getElementById('dashboard-stats-feed'); if(!root) return; root.querySelectorAll('.kpi-count').forEach(function(el){ var target=parseFloat(el.dataset.target||'0'); var decimals=parseInt(el.dataset.decimals||'0',10); if(isNaN(target)||target<=0){ el.textContent=decimals>0?'0.0':'0'; return; } var duration=900,start=null; function fmt(v){ return decimals>0? v.toFixed(decimals) : Math.round(v).toLocaleString(); } function step(ts){ if(!start) start=ts; var p=Math.min((ts-start)/duration,1); var eased=1-Math.pow(1-p,3); el.textContent=fmt(target*eased); if(p<1) requestAnimationFrame(step); } requestAnimationFrame(step); }); })();
    </script>
</turbo-frame>
