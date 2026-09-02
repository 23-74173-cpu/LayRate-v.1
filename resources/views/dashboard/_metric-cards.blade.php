<turbo-frame id="dashboard-stats">
    <style>
        .kpi-accent {
            position: absolute; left: 0; right: 0; bottom: 0; height: 3px;
        }
    </style>

    <div class="space-y-2 mb-2">
        {{-- Production Metrics --}}
        <div>
            <h3 class="text-[10px] font-semibold uppercase tracking-[0.125px] text-[#6B7280] mb-2">
                <i data-lucide="factory" class="w-4 h-4 inline-block mr-1.5 -mt-0.5" style="color:#0075de;"></i>
                Production
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                {{-- Total Hens --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border p-3 cursor-pointer"
                     style="background-color: #f8f8f8; border-color: #e6e6e6; animation-delay: 0ms;"
                     role="link" tabindex="0" aria-label="Go to Hens"
                     data-nav="{{ route('chickens.index') }}" data-kpi="hens">
                    <span class="kpi-watermark" style="color:#CDD2DA;"><i data-lucide="bird" class="w-full h-full"></i></span>
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #d6f0e3; color: #2D7D46; border: 1px solid #2D7D46;">
                            <i data-lucide="bird" class="w-4 h-4"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('hens')" aria-label="Hens per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-2">
                        <div class="text-[10px] font-semibold tracking-[0.125px] uppercase" style="color: #5b6472;">Total Hens</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#102A4C] mt-2 kpi-count" data-target="{{ $totalHens }}">0</div>
                    </div>
                    <span class="kpi-accent" style="background-color: #d6f0e3;"></span>
                </div>

                {{-- HDEP --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border p-3 cursor-pointer"
                     style="background-color: #f8f8f8; border-color: #e6e6e6; animation-delay: 60ms;"
                     role="link" tabindex="0" aria-label="Go to Egg Logging"
                     data-nav="{{ route('eggs.logging') }}" data-kpi="hdep">
                    <span class="kpi-watermark" style="color:#CDD2DA;"><i data-lucide="gauge" class="w-full h-full"></i></span>
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #dcebfa; color: #1D4E8F; border: 1px solid #1D4E8F;">
                            <i data-lucide="gauge" class="w-4 h-4"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('hdep')" aria-label="HDEP per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-2">
                        <div class="text-[10px] font-semibold tracking-[0.125px] uppercase" style="color: #5b6472;">Today's HDEP</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#102A4C] mt-2">
                            <span class="kpi-count" data-target="{{ $todayHdep }}" data-decimals="1">0</span>%
                        </div>
                        <div class="text-xs font-medium mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5"
                             style="color: {{ $hdepDelta >= 0 ? '#0f7a44' : '#b71c2c' }}; background-color: rgba(255,255,255,0.78); border: 1px solid {{ $hdepDelta >= 0 ? 'rgba(31,138,79,0.35)' : 'rgba(183,28,44,0.35)' }};">
                            {{ $hdepDelta >= 0 ? '▲' : '▼' }} {{ abs($hdepDelta) }}% vs yesterday
                        </div>
                    </div>
                    <span class="kpi-accent" style="background-color: #dcebfa;"></span>
                </div>

                {{-- Eggs Collected --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border p-3 cursor-pointer"
                     style="background-color: #f8f8f8; border-color: #e6e6e6; animation-delay: 120ms;"
                     role="link" tabindex="0" aria-label="Go to Egg Logging"
                     data-nav="{{ route('eggs.logging') }}" data-kpi="eggs">
                    <span class="kpi-watermark" style="color:#CDD2DA;"><i data-lucide="egg" class="w-full h-full"></i></span>
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #fae3d0; color: #C2703E; border: 1px solid #C2703E;">
                            <i data-lucide="egg" class="w-4 h-4"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('eggs')" aria-label="Eggs per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-2">
                        <div class="text-[10px] font-semibold tracking-[0.125px] uppercase" style="color: #5b6472;">Eggs Today</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#102A4C] mt-2 kpi-count" data-target="{{ $eggsToday }}">0</div>
                        <div class="text-xs font-medium mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5"
                             style="color: {{ $eggsDelta >= 0 ? '#0f7a44' : '#b71c2c' }}; background-color: rgba(255,255,255,0.78); border: 1px solid {{ $eggsDelta >= 0 ? 'rgba(31,138,79,0.35)' : 'rgba(183,28,44,0.35)' }};">
                            {{ $eggsDelta >= 0 ? '▲' : '▼' }} {{ abs($eggsDelta) }} vs yesterday
                        </div>
                    </div>
                    <span class="kpi-accent" style="background-color: #fae3d0;"></span>
                </div>

                {{-- Lifetime Eggs --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border p-3 cursor-pointer"
                     style="background-color: #f8f8f8; border-color: #e6e6e6; animation-delay: 180ms;"
                     role="link" tabindex="0" aria-label="Go to Egg Production History"
                     data-nav="{{ route('egg-production-history') }}" data-kpi="lifetime-eggs">
                    <span class="kpi-watermark" style="color:#CDD2DA;"><i data-lucide="layers" class="w-full h-full"></i></span>
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #e9e0f5; color: #6B4C8A; border: 1px solid #6B4C8A;">
                            <i data-lucide="layers" class="w-4 h-4"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('lifetime-eggs')" aria-label="Lifetime eggs per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-2">
                        <div class="text-[10px] font-semibold tracking-[0.125px] uppercase" style="color: #5b6472;">Lifetime Eggs</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#102A4C] mt-2 kpi-count" data-target="{{ $lifetimeEggs }}">0</div>
                        <div class="text-xs font-medium mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5"
                             style="color: #0f7a44; background-color: rgba(255,255,255,0.78); border: 1px solid rgba(31,138,79,0.35);">
                            ▲ +{{ number_format($eggsToday) }} today
                        </div>
                    </div>
                    <span class="kpi-accent" style="background-color: #e9e0f5;"></span>
                </div>
            </div>
        </div>

        {{-- Environment & Health --}}
        <div>
            <h3 class="text-[10px] font-semibold uppercase tracking-[0.125px] text-[#6B7280] mb-2">
                <i data-lucide="heart-pulse" class="w-4 h-4 inline-block mr-1.5 -mt-0.5" style="color:#0891b2;"></i>
                Environment &amp; Health
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                {{-- Coop Temperature --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border p-3 cursor-pointer"
                     style="background-color: #f8f8f8; border-color: #e6e6e6; animation-delay: 120ms;"
                     role="link" tabindex="0" aria-label="Go to Environment"
                     data-nav="{{ route('environment') }}" data-kpi="env">
                    <span class="kpi-watermark" style="color:#CDD2DA;"><i data-lucide="thermometer" class="w-full h-full"></i></span>
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #f7e3cf; color: #C2703E; border: 1px solid #C2703E;">
                            <i data-lucide="thermometer" class="w-4 h-4"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('env')" aria-label="Environment per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-2">
                        <div class="text-[10px] font-semibold tracking-[0.125px] uppercase" style="color: #5b6472;">Average Coop Temperature</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#102A4C] mt-2">
                            <span class="kpi-count" data-target="{{ $avgTemp }}" data-decimals="1">0</span>°
                        </div>
                    </div>
                    <span class="kpi-accent" style="background-color: #f7e3cf;"></span>
                </div>

                {{-- Coop Humidity --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border p-3 cursor-pointer"
                     style="background-color: #f8f8f8; border-color: #e6e6e6; animation-delay: 160ms;"
                     role="link" tabindex="0" aria-label="Go to Environment"
                     data-nav="{{ route('environment') }}" data-kpi="env">
                    <span class="kpi-watermark" style="color:#CDD2DA;"><i data-lucide="droplets" class="w-full h-full"></i></span>
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #d5ecf4; color: #2C7C91; border: 1px solid #2C7C91;">
                            <i data-lucide="droplets" class="w-4 h-4"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('env')" aria-label="Environment per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-2">
                        <div class="text-[10px] font-semibold tracking-[0.125px] uppercase" style="color: #5b6472;">Average Humidity</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#102A4C] mt-2">
                            <span class="kpi-count" data-target="{{ $avgHum }}" data-decimals="1">0</span>%
                        </div>
                    </div>
                    <span class="kpi-accent" style="background-color: #d5ecf4;"></span>
                </div>

                {{-- Mortality Today --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border p-3 cursor-pointer col-span-2 sm:col-span-1"
                     style="background-color: #f8f8f8; border-color: #e6e6e6; animation-delay: 200ms;"
                     role="link" tabindex="0" aria-label="Go to Mortality"
                     data-nav="{{ route('chickens.index', ['tab' => 'mortality']) }}" data-kpi="mortality">
                    <span class="kpi-watermark" style="color:#CDD2DA;"><i data-lucide="heart-crack" class="w-full h-full"></i></span>
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #fadfe3; color: #C2405C; border: 1px solid #C2405C;">
                            <i data-lucide="heart-crack" class="w-4 h-4"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('mortality')" aria-label="Mortality per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-2">
                        <div class="text-[10px] font-semibold tracking-[0.125px] uppercase" style="color: #5b6472;">Mortality Today</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] mt-2 {{ $mortalityTodayTotal > 0 ? 'text-[#9b1c24]' : 'text-[#102A4C]' }}">
                            {{ number_format($mortalityTodayTotal) }}
                        </div>
                    </div>
                    <span class="kpi-accent" style="background-color: #fadfe3;"></span>
                </div>
            </div>
        </div>

        {{-- Feed --}}
        <div>
            <h3 class="text-[10px] font-semibold uppercase tracking-[0.125px] text-[#6B7280] mb-2">
                <i data-lucide="wheat" class="w-4 h-4 inline-block mr-1.5 -mt-0.5" style="color:#16a34a;"></i>
                Feed &amp; Nutrition
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                {{-- Avg CP% This Week --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border p-3 cursor-pointer"
                     style="background-color: #f8f8f8; border-color: #e6e6e6; animation-delay: 240ms;"
                     role="link" tabindex="0" aria-label="Go to Feed"
                     data-nav="{{ route('feed') }}" data-kpi="feed-avg-cp">
                    <span class="kpi-watermark" style="color:#CDD2DA;"><i data-lucide="flask-conical" class="w-full h-full"></i></span>
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #e6f6ee; color: #16a34a; border: 1px solid #16a34a;">
                            <i data-lucide="flask-conical" class="w-4 h-4"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('feed-avg-cp')" aria-label="Avg CP% breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-2">
                        <div class="text-[10px] font-semibold tracking-[0.125px] uppercase" style="color: #5b6472;">Avg CP% This Week</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#102A4C] mt-2">{{ number_format($avgCp, 1) }}%</div>
                    </div>
                    <span class="kpi-accent" style="background-color: #e6f6ee;"></span>
                </div>

                {{-- Avg Feed/Cage/Day --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border p-3 cursor-pointer"
                     style="background-color: #f8f8f8; border-color: #e6e6e6; animation-delay: 280ms;"
                     role="link" tabindex="0" aria-label="Go to Feed"
                     data-nav="{{ route('feed') }}" data-kpi="feed-avg-cage-day">
                    <span class="kpi-watermark" style="color:#CDD2DA;"><i data-lucide="scale" class="w-full h-full"></i></span>
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #e6f6ee; color: #16a34a; border: 1px solid #16a34a;">
                            <i data-lucide="scale" class="w-4 h-4"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('feed-avg-cage-day')" aria-label="Avg feed per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-2">
                        <div class="text-[10px] font-semibold tracking-[0.125px] uppercase" style="color: #5b6472;">Avg Feed/Cage/Day</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#102A4C] mt-2 kpi-count" data-target="{{ $avgFeedPerCage }}" data-decimals="1">0</div>
                    </div>
                    <span class="kpi-accent" style="background-color: #e6f6ee;"></span>
                </div>

                {{-- Total Feed Used --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border p-3 cursor-pointer"
                     style="background-color: #f8f8f8; border-color: #e6e6e6; animation-delay: 320ms;"
                     role="link" tabindex="0" aria-label="Go to Feed"
                     data-nav="{{ route('feed') }}" data-kpi="feed-total-week">
                    <span class="kpi-watermark" style="color:#CDD2DA;"><i data-lucide="package" class="w-full h-full"></i></span>
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #e6f6ee; color: #16a34a; border: 1px solid #16a34a;">
                            <i data-lucide="package" class="w-4 h-4"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('feed-total-week')" aria-label="Total feed used per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-2">
                        <div class="text-[10px] font-semibold tracking-[0.125px] uppercase" style="color: #5b6472;">Total Feed Used</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#102A4C] mt-2 kpi-count" data-target="{{ round($totalFeedWeek, 1) }}" data-decimals="1">0</div>
                        <div class="text-xs font-medium mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5"
                             style="color: #0f7a44; background-color: rgba(255,255,255,0.78); border: 1px solid rgba(31,138,79,0.35);">
                            ▲ +{{ number_format(round($feedTodayKg, 1), 1) }} kg today
                        </div>
                    </div>
                    <span class="kpi-accent" style="background-color: #e6f6ee;"></span>
                </div>

                {{-- Feed Cost This Month --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border p-3 cursor-pointer"
                     style="background-color: #f8f8f8; border-color: #e6e6e6; animation-delay: 360ms;"
                     role="link" tabindex="0" aria-label="Go to Feed"
                     data-nav="{{ route('feed') }}" data-kpi="feed-cost-month">
                    <span class="kpi-watermark" style="color:#CDD2DA;"><i data-lucide="banknote" class="w-full h-full"></i></span>
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #e6f6ee; color: #16a34a; border: 1px solid #16a34a;">
                            <i data-lucide="banknote" class="w-4 h-4"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('feed-cost-month')" aria-label="Feed cost per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-2">
                        <div class="text-[10px] font-semibold tracking-[0.125px] uppercase" style="color: #5b6472;">Feed Cost This Month</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#102A4C] mt-2">
                            @if($totalFeedCostMonth !== null && $totalFeedCostMonth > 0)
                                ₱{{ number_format($totalFeedCostMonth, 2) }}
                            @else
                                <span class="text-lg text-[#9CA3AF]">&mdash;</span>
                            @endif
                        </div>
                        <div class="text-xs font-medium mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5"
                             style="color: #0f7a44; background-color: rgba(255,255,255,0.78); border: 1px solid rgba(31,138,79,0.35);">
                            ▲ +₱{{ number_format($feedCostToday ?? 0, 2) }} today
                        </div>
                    </div>
                    <span class="kpi-accent" style="background-color: #e6f6ee;"></span>
                </div>
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