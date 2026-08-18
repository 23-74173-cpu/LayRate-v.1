<turbo-frame id="dashboard-stats">
    <div class="space-y-4 mb-3">
        {{-- Production Metrics --}}
        <div>
            <h3 class="text-[11px] font-semibold uppercase tracking-[0.125px] text-[#6B7280] mb-2">Production</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Total Hens --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border bg-white p-5 cursor-pointer"
                     style="border-color: #e6e6e6; animation-delay: 0ms;"
                     role="link" tabindex="0" aria-label="Go to Chickens"
                     data-nav="{{ route('chickens.index') }}" data-kpi="hens">
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #e8f3fe; color: #0075de;">
                            <i data-lucide="bird" class="w-5 h-5"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('hens')" aria-label="Hens per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-4">
                        <div class="text-[11px] font-semibold tracking-[0.125px] uppercase" style="color: #6B7280;">Total Hens</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#1f1f1f] mt-2 kpi-count" data-target="{{ $totalHens }}">0</div>
                        <div class="text-xs text-[#9CA3AF] mt-1.5">across {{ $cages->count() }} {{ Str::plural('cage', $cages->count()) }}</div>
                    </div>
                </div>

                {{-- HDEP --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border bg-white p-5 cursor-pointer"
                     style="border-color: #e6e6e6; animation-delay: 60ms;"
                     role="link" tabindex="0" aria-label="Go to Egg Logging"
                     data-nav="{{ route('eggs.logging') }}" data-kpi="hdep">
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #e8f5ec; color: #1f6b3a;">
                            <i data-lucide="chart-no-axes-combined" class="w-5 h-5"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('hdep')" aria-label="HDEP per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-4">
                        <div class="text-[11px] font-semibold tracking-[0.125px] uppercase" style="color: #6B7280;">Today's HDEP</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#1f1f1f] mt-2">
                            <span class="kpi-count" data-target="{{ $todayHdep }}" data-decimals="1">0</span>%
                        </div>
                        <div class="text-xs font-medium mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5"
                             style="color: {{ $hdepDelta >= 0 ? '#1f6b3a' : '#9b1c24' }}; background-color: {{ $hdepDelta >= 0 ? '#e8f5ec' : '#fbe4e6' }};">
                            {{ $hdepDelta >= 0 ? '▲' : '▼' }} {{ abs($hdepDelta) }}% vs yesterday
                        </div>
                    </div>
                </div>

                {{-- Eggs Collected --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border bg-white p-5 cursor-pointer"
                     style="border-color: #e6e6e6; animation-delay: 120ms;"
                     role="link" tabindex="0" aria-label="Go to Egg Logging"
                     data-nav="{{ route('eggs.logging') }}" data-kpi="eggs">
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #fff3e0; color: #d97a3e;">
                            <i data-lucide="egg" class="w-5 h-5"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('eggs')" aria-label="Eggs per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-4">
                        <div class="text-[11px] font-semibold tracking-[0.125px] uppercase" style="color: #6B7280;">Eggs Today</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#1f1f1f] mt-2 kpi-count" data-target="{{ $eggsToday }}">0</div>
                        <div class="text-xs text-[#9CA3AF] mt-1.5">collected today</div>
                    </div>
                </div>

                {{-- Lifetime Eggs --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border bg-white p-5 cursor-pointer"
                     style="border-color: #e6e6e6; animation-delay: 180ms;"
                     role="link" tabindex="0" aria-label="Go to Egg Production History"
                     data-nav="{{ route('egg-production-history') }}" data-kpi="lifetime-eggs">
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #e9e0f5; color: #6B4C8A;">
                            <i data-lucide="layers" class="w-5 h-5"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('lifetime-eggs')" aria-label="Lifetime eggs per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-4">
                        <div class="text-[11px] font-semibold tracking-[0.125px] uppercase" style="color: #6B7280;">Lifetime Eggs</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#1f1f1f] mt-2 kpi-count" data-target="{{ $lifetimeEggs }}">0</div>
                        <div class="text-xs text-[#9CA3AF] mt-1.5">total since day 1</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Environment & Health --}}
        <div>
            <h3 class="text-[11px] font-semibold uppercase tracking-[0.125px] text-[#6B7280] mb-2">Environment & Health</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {{-- Coop Temperature --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border bg-white p-5 cursor-pointer"
                     style="border-color: #e6e6e6; animation-delay: 240ms;"
                     role="link" tabindex="0" aria-label="Go to Environment"
                     data-nav="{{ route('environment') }}" data-kpi="env">
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #fff3e0; color: #c45c1c;">
                            <i data-lucide="thermometer" class="w-5 h-5"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('env')" aria-label="Environment per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-4">
                        <div class="text-[11px] font-semibold tracking-[0.125px] uppercase" style="color: #6B7280;">Coop Temp</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#1f1f1f] mt-2">
                            <span class="kpi-count" data-target="{{ $avgTemp }}" data-decimals="1">0</span>°
                        </div>
                        <div class="text-xs text-[#9CA3AF] mt-1.5">avg across cages</div>
                    </div>
                </div>

                {{-- Coop Humidity --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border bg-white p-5 cursor-pointer"
                     style="border-color: #e6e6e6; animation-delay: 300ms;"
                     role="link" tabindex="0" aria-label="Go to Environment"
                     data-nav="{{ route('environment') }}" data-kpi="env">
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #e0f2fe; color: #0369a1;">
                            <i data-lucide="droplets" class="w-5 h-5"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('env')" aria-label="Environment per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-4">
                        <div class="text-[11px] font-semibold tracking-[0.125px] uppercase" style="color: #6B7280;">Humidity</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#1f1f1f] mt-2">
                            <span class="kpi-count" data-target="{{ $avgHum }}" data-decimals="1">0</span>%
                        </div>
                        <div class="text-xs text-[#9CA3AF] mt-1.5">avg across cages</div>
                    </div>
                </div>

                {{-- Mortality Today --}}
                <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border bg-white p-5 cursor-pointer"
                     style="border-color: #e6e6e6; animation-delay: 360ms;"
                     role="link" tabindex="0" aria-label="Go to Mortality"
                     data-nav="{{ route('chickens.index', ['tab' => 'mortality']) }}" data-kpi="mortality">
                    <div class="relative flex items-start justify-between">
                        <span class="kpi-chip" style="background-color: #fbe4e6; color: #9b1c24;">
                            <i data-lucide="heart-crack" class="w-5 h-5"></i>
                        </span>
                        <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors -mt-1 -mr-1"
                                onclick="event.stopPropagation(); openKpiModal('mortality')" aria-label="Mortality per cage breakdown">
                            <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                        </button>
                    </div>
                    <div class="relative mt-4">
                        <div class="text-[11px] font-semibold tracking-[0.125px] uppercase" style="color: #6B7280;">Mortality Today</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] mt-2 {{ $mortalityTodayTotal > 0 ? 'text-[#9b1c24]' : 'text-[#1f1f1f]' }}">
                            {{ number_format($mortalityTodayTotal) }}
                        </div>
                        <div class="text-xs text-[#9CA3AF] mt-1.5">{{ $mortalityTodayTotal === 1 ? 'hen today' : 'hens today' }}</div>
                    </div>
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
            title: 'Mortality by Cage',
            rows: {!! $cages->map(function ($cage) use ($mortalityToday) {
                $count = $mortalityToday[$cage->cage_code] ?? 0;
                return ['label' => $cage->cage_code, 'color' => $cage->color, 'bgColor' => $cage->colorSoft, 'value' => $count . ' ' . Str::plural('hen', $count)];
            })->values()->toJson() !!}
        }
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
