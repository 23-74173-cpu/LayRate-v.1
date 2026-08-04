<turbo-frame id="dashboard-stats">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-3">
        {{-- Total Hens --}}
        <div class="kpi-card kpi-blue dash-rise relative overflow-hidden rounded-2xl border bg-white p-5 cursor-pointer"
             style="border-color: #e6e6e6; animation-delay: 0ms;"
             role="link" tabindex="0" aria-label="Go to Chickens"
             data-nav="{{ route('chickens.index') }}" data-kpi="hens">
            <div class="absolute inset-x-0 top-0 h-1" style="background: linear-gradient(90deg, #0075de, #62aef0);"></div>
            <div class="kpi-glow" style="background: radial-gradient(circle, #0075de, transparent 70%);"></div>
            <i data-lucide="bird" class="kpi-watermark"></i>
            <div class="relative flex items-center justify-between mb-4">
                <span class="kpi-chip w-11 h-11" style="background: linear-gradient(135deg, #0075de, #62aef0);">
                    <i data-lucide="bird" class="w-5 h-5"></i>
                </span>
                <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors"
                        onclick="event.stopPropagation(); openKpiModal('hens')" aria-label="Hens per cage breakdown">
                    <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                </button>
            </div>
            <div class="relative">
                <div class="text-xs font-semibold tracking-[0.125px] uppercase" style="color: #0075de;">Total Hens</div>
                <div class="text-[32px] font-bold leading-none tracking-[-1px] text-[#1f1f1f] mt-2 kpi-count" data-target="{{ $totalHens }}">0</div>
                <div class="text-xs text-[#9CA3AF] mt-2">across {{ $cages->count() }} {{ Str::plural('cage', $cages->count()) }}</div>
            </div>
        </div>

        {{-- HDEP --}}
        <div class="kpi-card kpi-green dash-rise relative overflow-hidden rounded-2xl border bg-white p-5 cursor-pointer"
             style="border-color: #e6e6e6; animation-delay: 60ms;"
             role="link" tabindex="0" aria-label="Go to Egg Logging"
             data-nav="{{ route('eggs.logging') }}" data-kpi="hdep">
            <div class="absolute inset-x-0 top-0 h-1" style="background: linear-gradient(90deg, #1f6b3a, #62c887);"></div>
            <div class="kpi-glow" style="background: radial-gradient(circle, #1f6b3a, transparent 70%);"></div>
            <i data-lucide="chart-no-axes-combined" class="kpi-watermark"></i>
            <div class="relative flex items-center justify-between mb-4">
                <span class="kpi-chip w-11 h-11" style="background: linear-gradient(135deg, #1f6b3a, #62c887);">
                    <i data-lucide="chart-no-axes-combined" class="w-5 h-5"></i>
                </span>
                <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors"
                        onclick="event.stopPropagation(); openKpiModal('hdep')" aria-label="HDEP per cage breakdown">
                    <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                </button>
            </div>
            <div class="relative">
                <div class="text-xs font-semibold tracking-[0.125px] uppercase" style="color: #1f6b3a;">Today's HDEP</div>
                <div class="text-[32px] font-bold leading-none tracking-[-1px] text-[#1f1f1f] mt-2">
                    <span class="kpi-count" data-target="{{ $todayHdep }}" data-decimals="1">0</span>%
                </div>
                <div class="text-xs font-medium mt-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5"
                     style="color: {{ $hdepDelta >= 0 ? '#1f6b3a' : '#9b1c24' }}; background-color: {{ $hdepDelta >= 0 ? '#e8f5ec' : '#fbe4e6' }};">
                    {{ $hdepDelta >= 0 ? '▲' : '▼' }} {{ abs($hdepDelta) }}% vs yesterday
                </div>
            </div>
        </div>

        {{-- Eggs Collected --}}
        <div class="kpi-card kpi-orange dash-rise relative overflow-hidden rounded-2xl border bg-white p-5 cursor-pointer"
             style="border-color: #e6e6e6; animation-delay: 120ms;"
             role="link" tabindex="0" aria-label="Go to Egg Logging"
             data-nav="{{ route('eggs.logging') }}" data-kpi="eggs">
            <div class="absolute inset-x-0 top-0 h-1" style="background: linear-gradient(90deg, #c05621, #f0a35e);"></div>
            <div class="kpi-glow" style="background: radial-gradient(circle, #c05621, transparent 70%);"></div>
            <i data-lucide="egg" class="kpi-watermark"></i>
            <div class="relative flex items-center justify-between mb-4">
                <span class="kpi-chip w-11 h-11" style="background: linear-gradient(135deg, #c05621, #f0a35e);">
                    <i data-lucide="egg" class="w-5 h-5"></i>
                </span>
                <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors"
                        onclick="event.stopPropagation(); openKpiModal('eggs')" aria-label="Eggs per cage breakdown">
                    <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                </button>
            </div>
            <div class="relative">
                <div class="text-xs font-semibold tracking-[0.125px] uppercase" style="color: #c05621;">Eggs Today</div>
                <div class="text-[32px] font-bold leading-none tracking-[-1px] text-[#1f1f1f] mt-2 kpi-count" data-target="{{ $eggsToday }}">0</div>
                <div class="text-xs text-[#9CA3AF] mt-2">collected today</div>
            </div>
        </div>

        {{-- Lifetime Eggs --}}
        <div class="kpi-card kpi-purple dash-rise relative overflow-hidden rounded-2xl border bg-white p-5 cursor-pointer"
             style="border-color: #e6e6e6; animation-delay: 180ms;"
             role="link" tabindex="0" aria-label="Go to Egg Production History"
             data-nav="{{ route('egg-production-history') }}" data-kpi="lifetime-eggs">
            <div class="absolute inset-x-0 top-0 h-1" style="background: linear-gradient(90deg, #6d5bd0, #a99bf0);"></div>
            <div class="kpi-glow" style="background: radial-gradient(circle, #6d5bd0, transparent 70%);"></div>
            <i data-lucide="layers" class="kpi-watermark"></i>
            <div class="relative flex items-center justify-between mb-4">
                <span class="kpi-chip w-11 h-11" style="background: linear-gradient(135deg, #6d5bd0, #a99bf0);">
                    <i data-lucide="layers" class="w-5 h-5"></i>
                </span>
                <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors"
                        onclick="event.stopPropagation(); openKpiModal('lifetime-eggs')" aria-label="Lifetime eggs per cage breakdown">
                    <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                </button>
            </div>
            <div class="relative">
                <div class="text-xs font-semibold tracking-[0.125px] uppercase" style="color: #6d5bd0;">Lifetime Eggs</div>
                <div class="text-[32px] font-bold leading-none tracking-[-1px] text-[#1f1f1f] mt-2 kpi-count" data-target="{{ $lifetimeEggs }}">0</div>
                <div class="text-xs text-[#9CA3AF] mt-2">total since day 1</div>
            </div>
        </div>

        {{-- Coop Environment --}}
        <div class="kpi-card kpi-teal dash-rise relative overflow-hidden rounded-2xl border bg-white p-5 cursor-pointer"
             style="border-color: #e6e6e6; animation-delay: 240ms;"
             role="link" tabindex="0" aria-label="Go to Environment"
             data-nav="{{ route('environment') }}" data-kpi="env">
            <div class="absolute inset-x-0 top-0 h-1" style="background: linear-gradient(90deg, #0e7c5a, #5fc7a4);"></div>
            <div class="kpi-glow" style="background: radial-gradient(circle, #0e7c5a, transparent 70%);"></div>
            <i data-lucide="thermometer" class="kpi-watermark"></i>
            <div class="relative flex items-center justify-between mb-4">
                <span class="kpi-chip w-11 h-11" style="background: linear-gradient(135deg, #0e7c5a, #5fc7a4);">
                    <i data-lucide="thermometer" class="w-5 h-5"></i>
                </span>
                <button type="button" class="p-1 rounded-full hover:bg-black/5 transition-colors"
                        onclick="event.stopPropagation(); openKpiModal('env')" aria-label="Environment per cage breakdown">
                    <i data-lucide="info" class="w-4 h-4 text-[#9CA3AF]"></i>
                </button>
            </div>
            <div class="relative">
                <div class="text-xs font-semibold tracking-[0.125px] uppercase" style="color: #0e7c5a;">Coop Environment</div>
                <div class="flex items-end gap-5 mt-2 mb-2">
                    <div>
                        <div class="text-xs text-[#9CA3AF]">Temp</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#1f1f1f] mt-1">
                            <span class="kpi-count" data-target="{{ $avgTemp }}" data-decimals="1">0</span>°
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-[#9CA3AF]">Humidity</div>
                        <div class="text-[28px] font-bold leading-none tracking-[-1px] text-[#1f1f1f] mt-1">
                            <span class="kpi-count" data-target="{{ $avgHum }}" data-decimals="1">0</span>%
                        </div>
                    </div>
                </div>
                <x-status-badge status="Normal" type="sensor" />
            </div>
        </div>
    </div>

    <script>
    // Breakdown data for the shared KPI modal (shell) — items 8 + 9
    window.KPI_DATA = Object.assign(window.KPI_DATA || {}, {
        hens: {
            title: 'Hens per Cage',
            rows: {!! $cages->map(fn($c) => ['label' => $c->cage_code, 'color' => $c->color, 'value' => number_format($c->hen_count) . ' hens · ' . $c->breed])->values()->toJson() !!}
        },
        hdep: {
            title: "Today's HDEP by Cage",
            rows: {!! $cages->map(fn($c) => ['label' => $c->cage_code, 'color' => $c->color, 'value' => number_format($c->today_hdep, 1) . '%'])->values()->toJson() !!}
        },
        eggs: {
            title: 'Eggs Collected by Cage',
            rows: {!! $cages->map(fn($c) => ['label' => $c->cage_code, 'color' => $c->color, 'value' => number_format($c->today_eggs) . ' eggs'])->values()->toJson() !!}
        },
        'lifetime-eggs': {
            title: 'Lifetime Eggs by Cage',
            rows: {!! $cages->map(fn($c) => ['label' => $c->cage_code, 'color' => $c->color, 'value' => number_format($c->productionLogs->sum('egg_count')) . ' eggs'])->values()->toJson() !!}
        },
        env: {
            title: 'Environment by Cage',
            rows: {!! $liveReadings->map(fn($r) => ['label' => $r->cage, 'color' => $r->color, 'value' => $r->temp . ' · ' . $r->hum . ' · ' . $r->status])->values()->toJson() !!}
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
            if (isNaN(target) || target <= 0) { el.textContent = '0'; return; }
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
