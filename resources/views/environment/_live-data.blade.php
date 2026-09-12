<turbo-frame id="environment-live-data">
    {{-- ── Top Metric Cards — dashboard gradient KPI design ── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-8">
        <x-kpi-card
            label="Coop Avg Temp"
            icon="thermometer"
            cardGradient="linear-gradient(135deg,#f59e0b,#C2703E)"
            delay="0ms"
            :value="($avgTemp ? number_format($avgTemp,1) : '—') . '°C'"
        >
            <div class="text-xs mt-1.5 font-medium" style="color: rgba(255,255,255,0.85);">Spread {{ $tempValues->count() > 1 ? number_format(max(0, $tempValues->max() - $tempValues->min()), 1) . '°C across cages' : 'across cages' }}</div>
        </x-kpi-card>
        <x-kpi-card
            label="Coop Avg Humidity"
            icon="droplets"
            cardGradient="linear-gradient(135deg,#0d9488,#2C7C91)"
            delay="60ms"
            :value="($avgHum ? number_format($avgHum,1) : '—') . '%'"
        >
            <div class="text-xs mt-1.5 font-medium" style="color: rgba(255,255,255,0.85);">Spread {{ $humValues->count() > 1 ? number_format(max(0, $humValues->max() - $humValues->min()), 1) . '% across cages' : 'across cages' }}</div>
        </x-kpi-card>
        <x-kpi-card
            class="col-span-2 sm:col-span-1"
            label="Active Sensors"
            icon="radio"
            cardGradient="linear-gradient(135deg,#0075de,#1D4E8F)"
            delay="120ms"
            :value="$activeSensors . ' sensors'"
        >
            <div class="text-xs mt-1.5 font-medium" style="color: rgba(255,255,255,0.85);">{{ $activeSensors > 0 ? 'One node mapped per cage' : 'All entries are manual logs' }}</div>
        </x-kpi-card>
    </div>

    {{-- ── Per-cage Sensor Cards (sensor-assigned cages with readings only) ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-10">
        @forelse(($sensorReadings ?? $latestPerCage) as $r)
        @php
            $color = $r->cage->color;
            $soft  = $r->cage->color_soft;
            // Canonical ok/watch/alert palette from DESIGN-SYSTEM.md §2.4 (matches <x-status-badge>)
            $toneColors = fn (string $tone) => match ($tone) {
                'Normal', 'OK' => ['#e8f5ec', '#1f6b3a'],
                'Watch'        => ['#fdf3e0', '#8a5a00'],
                default        => ['#fbe4e6', '#9b1c24'],
            };
            [$tBg, $tTxt] = $toneColors($r->tempStatus);
            [$hBg, $hTxt] = $toneColors($r->humStatus);
            $isStale = $r->env->recorded_at?->lt(now()->subMinutes(30)) ?? false;
        @endphp
        <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
            <div class="h-1 w-full" style="background:{{ $isStale ? '#9b1c24' : $color }}"></div>
            <div class="p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $color }}"></span>
                        <span class="text-sm font-semibold text-[#1f1f1f]">{{ $r->cage->cage_code }}</span>
                    </div>
                    <span class="text-[10px] font-semibold tracking-[0.05em] uppercase px-2 py-0.5 rounded-full"
                          style="background:{{ $r->source === 'Manual Log' ? '#fdf3e0' : '#e8f5ec' }};color:{{ $r->source === 'Manual Log' ? '#8a5a00' : '#1f6b3a' }};">{{ $r->source }}</span>
                </div>

                <div class="flex items-center justify-between gap-3">
                    {{-- Temp --}}
                    <div class="flex-1">
                        <div class="text-[10px] font-semibold tracking-[0.125px] uppercase text-[#9CA3AF] mb-1 flex items-center gap-1">
                            <i data-lucide="thermometer" class="w-3 h-3"></i> Temp
                        </div>
                        <div class="flex items-baseline gap-0.5">
                            <span class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#1f1f1f]">{{ number_format($r->env->temperature_c, 1) }}</span>
                            <span class="text-xs font-medium text-[#a39e98]">°C</span>
                        </div>
                        <span class="text-[10px] font-semibold uppercase mt-1 inline-block px-1.5 py-0.5 rounded" style="background:{{ $tBg }};color:{{ $tTxt }}">{{ $r->tempStatus }}</span>
                    </div>
                    {{-- Vertical divider --}}
                    <div class="w-px self-stretch" style="background:{{ $soft }}"></div>
                    {{-- Humidity --}}
                    <div class="flex-1">
                        <div class="text-[10px] font-semibold tracking-[0.125px] uppercase text-[#9CA3AF] mb-1 flex items-center gap-1">
                            <i data-lucide="droplets" class="w-3 h-3"></i> Humidity
                        </div>
                        <div class="flex items-baseline gap-0.5">
                            <span class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#1f1f1f]">{{ number_format($r->env->humidity_pct, 0) }}</span>
                            <span class="text-xs font-medium text-[#a39e98]">%</span>
                        </div>
                        <span class="text-[10px] font-semibold uppercase mt-1 inline-block px-1.5 py-0.5 rounded" style="background:{{ $hBg }};color:{{ $hTxt }}">{{ $r->humStatus }}</span>
                    </div>
                </div>
            </div>

            <div class="px-4 py-2.5 flex items-center justify-between border-t border-[#F0F0F0]" style="background:{{ $soft }}33">
                <span class="text-[11px] {{ $isStale ? 'text-[#9b1c24] font-medium' : 'text-[#9CA3AF]' }} flex items-center gap-1">
                    <i data-lucide="{{ $isStale ? 'alert-triangle' : 'clock' }}" class="w-3 h-3"></i>
                    {{ $isStale ? 'Stale · ' : '' }}{{ $r->env->recorded_at?->diffForHumans() ?? 'No timestamp' }}
                </span>
                <span class="text-[11px] text-[#9CA3AF]">{{ $r->status }}</span>
            </div>
        </div>
        @empty
        <div class="col-span-full">
            <div class="bg-white rounded-lg border border-dashed border-[#D9D9D9] py-10 text-center text-sm" style="color: #a39e98;">
                No environmental readings recorded yet.
            </div>
        </div>
        @endforelse

        {{-- Cages with no assigned sensor (or sensor cages with no readings yet) --}}
        @foreach($cages as $cage)
        @php
            $hasSensor = ($sensorCageIds ?? collect())->contains($cage->id);
            $hasReading = $latestPerCage->pluck('cage.id')->contains($cage->id);
        @endphp
        @if(! $hasSensor || ! $hasReading)
        <div class="bg-white rounded-lg border border-dashed border-[#D9D9D9] overflow-hidden">
            <div class="px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $cage->color }}"></span>
                    <span class="text-sm font-medium text-[#333333]">{{ $cage->cage_code }}</span>
                </div>
                <span class="text-[10px] font-semibold tracking-[0.05em] uppercase px-2 py-0.5 rounded-full bg-[#F5F6F8] text-[#a39e98]">Offline</span>
            </div>
            <div class="px-4 py-6 text-center text-xs text-[#6B7280]">
                <i data-lucide="wifi-off" class="w-5 h-5 mx-auto mb-2 text-gray-300"></i>
                {{ ! $hasSensor ? 'No sensor assigned' : 'No sensor data' }}
            </div>
        </div>
        @endif
        @endforeach
    </div>

    {{-- ── Trend Charts ── --}}
    <div class="flex items-center justify-between mb-3">
        <span class="text-sm font-semibold text-[#1f1f1f]">Trend Charts</span>
        <select id="trendRange" onchange="changeTrendRange(this.value)"
                class="text-xs border border-[#D9D9D9] rounded-lg px-2.5 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30">
            <option value="24h" {{ ($range ?? '24h') === '24h' ? 'selected' : '' }}>24 Hours</option>
            <option value="week" {{ ($range ?? '24h') === 'week' ? 'selected' : '' }}>Week</option>
            <option value="month" {{ ($range ?? '24h') === 'month' ? 'selected' : '' }}>Month</option>
        </select>
    </div>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs font-semibold tracking-wider text-[#6B7280] mb-3">TEMPERATURE TREND</div>
            <div id="envTempChartWrap" class="relative w-full h-[160px]">
                <canvas id="envTempChart" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
            <div id="envTempChartEmpty" class="hidden h-[160px] flex items-center justify-center text-sm" style="color: #a39e98;">No temperature readings in this window.</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs font-semibold tracking-wider text-[#6B7280] mb-3">HUMIDITY TREND</div>
            <div id="envHumChartWrap" class="relative w-full h-[160px]">
                <canvas id="envHumChart" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
            <div id="envHumChartEmpty" class="hidden h-[160px] flex items-center justify-center text-sm" style="color: #a39e98;">No humidity readings in this window.</div>
        </div>
    </div>



    <script>
    (function() {
        window.initEnvCharts = function initEnvCharts() {
            // Looked up per cage code from the canonical Cage color accessor —
            // previously a positional array assigned colors by iteration order,
            // which only happened to line up with A/B/C/D by coincidence.
            const cageColors  = @json($cages->pluck('color', 'id'));
            const trendData   = @json($trendData);
            const cagesMap    = @json($cages->pluck('cage_code','id'));
            const allHours = new Set();
            for (const rows of Object.values(trendData)) {
                rows.forEach(function(r) { allHours.add(r.period); });
            }
            const labels = Array.from(allHours).sort();
            const hasAnyData = Object.values(trendData).some(rows => rows.length > 0);

            function buildDatasets(field) {
                const sets = [];
                for (const [cageId, rows] of Object.entries(trendData)) {
                    const name = cagesMap[cageId] || 'Cage '+cageId;
                    const data = labels.map(l => {
                        const r = rows.find(r => r.period === l);
                        return r ? r[field] : null;
                    });
                    sets.push({ label: name, data, borderColor: cageColors[cageId] || '#6B7280', tension: 0.3, pointRadius: 3, borderWidth: 1.5, fill: false });
                }
                return sets;
            }

            const chartOpts = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true } },
                scales: {
                    x: { ticks: { autoSkip: true, maxRotation: 45, minRotation: 0 } },
                    y: {},
                }
            };

            const tempWrap  = document.getElementById('envTempChartWrap');
            const humWrap   = document.getElementById('envHumChartWrap');
            const tempEmpty = document.getElementById('envTempChartEmpty');
            const humEmpty  = document.getElementById('envHumChartEmpty');
            if (!tempWrap || !humWrap) return;

            tempWrap.classList.toggle('hidden', !hasAnyData);
            humWrap.classList.toggle('hidden', !hasAnyData);
            if (tempEmpty) tempEmpty.classList.toggle('hidden', hasAnyData);
            if (humEmpty) humEmpty.classList.toggle('hidden', hasAnyData);
            if (!hasAnyData) return;

            // create(), not update(): this page's 10s poll replaces the canvas DOM
            // nodes wholesale via innerHTML=, so any existing chart instance is
            // already bound to a detached canvas by the time this runs. update()
            // would silently redraw onto that invisible old node instead of the
            // new one — create() always re-queries the live canvas by id.
            LayRateChart.create('envTempChart', { type:'line', data:{ labels, datasets: buildDatasets('avg_temp') }, options: chartOpts });
            LayRateChart.create('envHumChart', { type:'line', data:{ labels, datasets: buildDatasets('avg_hum')  }, options: chartOpts });
        }

        if (!window.__envChartsLifecycleBound) {
            window.__envChartsLifecycleBound = true;
            document.addEventListener('turbo:load', initEnvCharts);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initEnvCharts);
        } else {
            initEnvCharts();
        }
    })();

    </script>
</turbo-frame>
