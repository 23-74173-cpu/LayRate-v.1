<turbo-frame id="dashboard-stats">
    <div class="space-y-2 mb-2">
        {{-- Production Metrics --}}
        <div>
            <h3 class="text-[10px] font-semibold uppercase tracking-[0.125px] text-[#6B7280] mb-2">
                <i data-lucide="factory" class="w-4 h-4 inline-block mr-1.5 -mt-0.5" style="color:#0075de;"></i>
                Production
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <x-kpi-card
                    label="Total Hens"
                    icon="bird"
                    iconBg="#d6f0e3"
                    iconColor="#2D7D46"
                    gradient="linear-gradient(135deg,#16a34a,#2D7D46)"
                    accent="#d6f0e3"
                    delay="0ms"
                    :href="route('chickens.index')"
                    kpi="hens"
                    ariaLabel="Go to Hens"
                    infoLabel="Hens per cage breakdown"
                    :target="$totalHens"
                />

                <x-kpi-card
                    label="Today's HDEP"
                    icon="gauge"
                    iconBg="#dcebfa"
                    iconColor="#1D4E8F"
                    gradient="linear-gradient(135deg,#0075de,#1D4E8F)"
                    accent="#dcebfa"
                    delay="60ms"
                    :href="route('eggs.logging')"
                    kpi="hdep"
                    ariaLabel="Go to Egg Logging"
                    infoLabel="HDEP per cage breakdown"
                    :target="$todayHdep"
                    :decimals="1"
                    suffix="%"
                >
                    <div class="text-xs font-medium mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5" style="color: {{ $hdepDelta >= 0 ? '#0f7a44' : '#b71c2c' }}; background-color: rgba(255,255,255,0.78); border: 1px solid {{ $hdepDelta >= 0 ? 'rgba(31,138,79,0.35)' : 'rgba(183,28,44,0.35)' }};">{{ $hdepDelta >= 0 ? '▲' : '▼' }} {{ abs($hdepDelta) }}% vs yesterday</div>
                </x-kpi-card>

                <x-kpi-card
                    label="Eggs Today"
                    icon="egg"
                    iconBg="#fae3d0"
                    iconColor="#C2703E"
                    gradient="linear-gradient(135deg,#d97706,#C2703E)"
                    accent="#fae3d0"
                    delay="120ms"
                    :href="route('eggs.logging')"
                    kpi="eggs"
                    ariaLabel="Go to Egg Logging"
                    infoLabel="Eggs per cage breakdown"
                    :target="$eggsToday"
                >
                    <div class="text-xs font-medium mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5" style="color: {{ $eggsDelta >= 0 ? '#0f7a44' : '#b71c2c' }}; background-color: rgba(255,255,255,0.78); border: 1px solid {{ $eggsDelta >= 0 ? 'rgba(31,138,79,0.35)' : 'rgba(183,28,44,0.35)' }};">{{ $eggsDelta >= 0 ? '▲' : '▼' }} {{ abs($eggsDelta) }} vs yesterday</div>
                </x-kpi-card>

                <x-kpi-card
                    label="Lifetime Eggs"
                    icon="layers"
                    iconBg="#e9e0f5"
                    iconColor="#6B4C8A"
                    gradient="linear-gradient(135deg,#8B5CF6,#6B4C8A)"
                    accent="#e9e0f5"
                    delay="180ms"
                    :href="route('egg-production-history')"
                    kpi="lifetime-eggs"
                    ariaLabel="Go to Egg Production History"
                    infoLabel="Lifetime eggs per cage breakdown"
                    :target="$lifetimeEggs"
                >
                    <div class="text-xs font-medium mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5" style="color: #0f7a44; background-color: rgba(255,255,255,0.78); border: 1px solid rgba(31,138,79,0.35);">▲ +{{ number_format($eggsToday) }} today</div>
                </x-kpi-card>
            </div>
        </div>

        {{-- Environment & Health --}}
        <div>
            <h3 class="text-[10px] font-semibold uppercase tracking-[0.125px] text-[#6B7280] mb-2">
                <i data-lucide="heart-pulse" class="w-4 h-4 inline-block mr-1.5 -mt-0.5" style="color:#0891b2;"></i>
                Environment &amp; Health
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <x-kpi-card
                    label="Average Coop Temperature"
                    icon="thermometer"
                    iconBg="#f7e3cf"
                    iconColor="#C2703E"
                    gradient="linear-gradient(135deg,#f59e0b,#C2703E)"
                    accent="#f7e3cf"
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
                    iconBg="#d5ecf4"
                    iconColor="#2C7C91"
                    gradient="linear-gradient(135deg,#0d9488,#2C7C91)"
                    accent="#d5ecf4"
                    delay="160ms"
                    :href="route('environment')"
                    kpi="env"
                    ariaLabel="Go to Environment"
                    infoLabel="Environment per cage breakdown"
                    :target="$avgHum"
                    :decimals="1"
                    suffix="%"
                />

                <x-kpi-card
                    class="col-span-2 sm:col-span-1"
                    label="Mortality Today"
                    icon="heart-crack"
                    iconBg="#fadfe3"
                    iconColor="#C2405C"
                    :gradient="$mortalityTodayTotal > 0 ? 'linear-gradient(135deg,#dc2626,#9b1c24)' : null"
                    accent="#fadfe3"
                    delay="200ms"
                    :href="route('chickens.index', ['tab' => 'mortality'])"
                    kpi="mortality"
                    ariaLabel="Go to Mortality"
                    infoLabel="Mortality per cage breakdown"
                    :value="number_format($mortalityTodayTotal)"
                />
            </div>
        </div>

        {{-- Feed --}}
        <div>
            <h3 class="text-[10px] font-semibold uppercase tracking-[0.125px] text-[#6B7280] mb-2">
                <i data-lucide="wheat" class="w-4 h-4 inline-block mr-1.5 -mt-0.5" style="color:#16a34a;"></i>
                Feed &amp; Nutrition
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <x-kpi-card
                    label="Avg CP% This Week"
                    icon="flask-conical"
                    iconBg="#e6f6ee"
                    iconColor="#16a34a"
                    gradient="linear-gradient(135deg,#16a34a,#15803d)"
                    accent="#e6f6ee"
                    delay="240ms"
                    :href="route('feed')"
                    kpi="feed-avg-cp"
                    ariaLabel="Go to Feed"
                    infoLabel="Avg CP% breakdown"
                    :value="number_format($avgCp, 1) . '%'"
                />

                <x-kpi-card
                    label="Avg Feed/Cage/Day"
                    icon="scale"
                    iconBg="#e6f6ee"
                    iconColor="#16a34a"
                    gradient="linear-gradient(135deg,#16a34a,#15803d)"
                    accent="#e6f6ee"
                    delay="280ms"
                    :href="route('feed')"
                    kpi="feed-avg-cage-day"
                    ariaLabel="Go to Feed"
                    infoLabel="Avg feed per cage breakdown"
                    :target="$avgFeedPerCage"
                    :decimals="1"
                />

                <x-kpi-card
                    label="Total Feed Used"
                    icon="package"
                    iconBg="#e6f6ee"
                    iconColor="#16a34a"
                    gradient="linear-gradient(135deg,#16a34a,#15803d)"
                    accent="#e6f6ee"
                    delay="320ms"
                    :href="route('feed')"
                    kpi="feed-total-week"
                    ariaLabel="Go to Feed"
                    infoLabel="Total feed used per cage breakdown"
                    :target="round($totalFeedWeek, 1)"
                    :decimals="1"
                >
                    <div class="text-xs font-medium mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5" style="color: #0f7a44; background-color: rgba(255,255,255,0.78); border: 1px solid rgba(31,138,79,0.35);">▲ +{{ number_format(round($feedTodayKg, 1), 1) }} kg today</div>
                </x-kpi-card>

                <x-kpi-card
                    label="Feed Cost This Month"
                    icon="banknote"
                    iconBg="#e6f6ee"
                    iconColor="#16a34a"
                    gradient="linear-gradient(135deg,#16a34a,#15803d)"
                    accent="#e6f6ee"
                    delay="360ms"
                    :href="route('feed')"
                    kpi="feed-cost-month"
                    ariaLabel="Go to Feed"
                    infoLabel="Feed cost per cage breakdown"
                    :value="$totalFeedCostMonth !== null && $totalFeedCostMonth > 0 ? '₱' . number_format($totalFeedCostMonth, 2) : null"
                >
                    <div class="text-xs font-medium mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5" style="color: #0f7a44; background-color: rgba(255,255,255,0.78); border: 1px solid rgba(31,138,79,0.35);">▲ +₱{{ number_format($feedCostToday ?? 0, 2) }} today</div>
                </x-kpi-card>
            </div>
        </div>
    </div>

    <script>
    // Breakdown data for the shared KPI modal (shell)
    window.KPI_DATA = Object.assign(window.KPI_DATA || {}, {
        hens: {
            title: 'Hens per Cage',
            rows: {!! $cages->map(fn($c) => ['label' => $c->cage_code, 'color' => $c->color, 'bgColor' => $c->colorSoft, 'value' => number_format($c->hen_count) . ' hens · ' . $c->breed])->values()->toJson() !!}
        },
        hdep: {
            title: "Today's HDEP by Cage",
            rows: {!! $cages->map(fn($c) => ['label' => $c->cage_code, 'color' => $c->color, 'bgColor' => $c->colorSoft, 'value' => number_format($c->today_hdep, 1) . '%'])->values()->toJson() !!}
        },
        eggs: {
            title: 'Eggs Collected by Cage',
            rows: {!! $cages->map(fn($c) => ['label' => $c->cage_code, 'color' => $c->color, 'bgColor' => $c->colorSoft, 'value' => number_format($c->today_eggs) . ' eggs'])->values()->toJson() !!}
        },
        'lifetime-eggs': {
            title: 'Lifetime Eggs by Cage',
            rows: {!! $cages->map(fn($c) => ['label' => $c->cage_code, 'color' => $c->color, 'bgColor' => $c->colorSoft, 'value' => number_format($c->productionLogs->sum('egg_count')) . ' eggs'])->values()->toJson() !!}
        },
        env: {
            title: 'Environment by Cage',
            rows: {!! $liveReadings->map(fn($r) => ['label' => $r->cage, 'color' => $r->color, 'bgColor' => $r->colorSoft, 'value' => $r->temp . ' · ' . $r->hum . ' · ' . $r->status])->values()->toJson() !!}
        },
        mortality: {
            title: 'Mortality by Cage{{ ($mortalityDays ?? 1) > 1 ? " (Last {$mortalityDays} Days)" : "" }}',
            rows: {!! $cages->map(function ($cage) use ($mortalityToday) {
                $count = $mortalityToday[$cage->cage_code] ?? 0;
                return ['label' => $cage->cage_code, 'color' => $cage->color, 'bgColor' => $cage->colorSoft, 'value' => $count . ' ' . Str::plural('hen', $count)];
            })->values()->toJson() !!}
        },
        'feed-avg-cp': {
            title: 'Crude Protein % by Batch',
            rows: {!! $allBatches->map(fn($b) => ['label' => $b->batch_code . ($b->brand ? ' (' . $b->brand . ')' : ''), 'color' => '#16a34a', 'bgColor' => '#e6f6ee', 'value' => number_format($b->crude_protein, 1) . '%'])->values()->toJson() !!}
        },
        'feed-avg-cage-day': {
            title: 'Avg Feed/Cage/Day (7-Day)',
            rows: {!! $feedWeekByCage->map(fn($r) => ['label' => $r->cage_code, 'color' => $r->color, 'bgColor' => $r->color_soft, 'value' => number_format(round($r->feed_kg / 7, 1), 1) . ' kg/day'])->values()->toJson() !!}
        },
        'feed-total-week': {
            title: 'Total Feed Used This Week by Cage',
            rows: {!! $feedWeekByCage->map(fn($r) => ['label' => $r->cage_code, 'color' => $r->color, 'bgColor' => $r->color_soft, 'value' => number_format($r->feed_kg, 2) . ' kg'])->values()->toJson() !!}
        },
        'feed-cost-month': {
            title: 'Feed Cost This Month by Cage',
            rows: {!! $feedCostByCage->map(fn($r) => ['label' => $r->cage_code, 'color' => $r->color, 'bgColor' => $r->color_soft, 'value' => '₱' . number_format($r->cost, 2)])->values()->toJson() !!}
        },
    });
    if (typeof bindKpiCards === 'function') bindKpiCards(document.getElementById('dashboard-stats'));

    // Count-up animation for .kpi-count elements in this frame
    (function () {
        var root = document.getElementById('dashboard-stats');
        if (!root) return;
        root.querySelectorAll('.kpi-count').forEach(function (el) {
            var target = parseFloat(el.dataset.target || '0');
            var decimals = parseInt(el.dataset.decimals || '0', 10);
            if (isNaN(target) || target <= 0) { el.textContent = decimals > 0 ? '0.0' : '0'; return; }
            var duration = 900, start = null;
            function fmt(v) {
                return decimals > 0 ? v.toFixed(decimals) : Math.round(v).toLocaleString();
            }
            function step(ts) {
                if (!start) start = ts;
                var p = Math.min((ts - start) / duration, 1);
                var eased = 1 - Math.pow(1 - p, 3);
                el.textContent = fmt(target * eased);
                if (p < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        });
    })();
    </script>
</turbo-frame>
