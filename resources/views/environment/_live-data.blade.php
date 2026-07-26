<turbo-frame id="environment-live-data">
    {{-- ── Top Metric Cards ── --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">COOP AVG TEMPERATURE</div>
            <div class="flex items-end gap-2 mb-1">
                <span class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ $avgTemp ? number_format($avgTemp,1) . '°C' : '—' }}</span>
                <x-status-badge status="In Range" type="sensor" class="mb-1" />
            </div>
            <div class="text-xs text-[#6B7280] mt-1">Spread across sensors: 1.3°C</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">COOP AVG HUMIDITY</div>
            <div class="flex items-end gap-2 mb-1">
                <span class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ $avgHum ? number_format($avgHum,1) . '%' : '—' }}</span>
                <x-status-badge status="In Range" type="sensor" class="mb-1" />
            </div>
            <div class="text-xs text-[#6B7280] mt-1">Spread across sensors: 4.4%</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">ACTIVE SENSORS</div>
            <div class="flex items-end gap-2 mb-1">
                <span class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ $latestPerCage->count() }}</span>
                <x-status-badge status="Live" type="sensor" class="mb-1" />
            </div>
            <div class="text-xs text-[#6B7280] mt-1">One sensor node mapped per cage.</div>
        </div>
    </div>

    {{-- ── Per-cage Sensor Cards ── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mt-5 mb-8">
        @foreach($latestPerCage as $r)
        @php
            $color = $r->cage->color;
            $sensorId = 'S-0' . $loop->iteration;
            // Canonical ok/watch/alert palette from DESIGN-SYSTEM.md §2.4 (matches <x-status-badge>) —
            // previously this used a second, incompatible hex palette (#D5E8D4/#FFF3CD/#F8D7DA).
            $toneColors = fn (string $tone) => match ($tone) {
                'Normal', 'OK' => ['#e8f5ec', '#1f6b3a'],
                'Watch'        => ['#fdf3e0', '#8a5a00'],
                default        => ['#fbe4e6', '#9b1c24'],
            };
            [$statusBg, $statusTxt] = $toneColors($r->status);
            [$tBg, $tTxt] = $toneColors($r->tempStatus);
            [$hBg, $hTxt] = $toneColors($r->humStatus);
            // A stale-but-present reading previously rendered identically to a
            // fresh one — no timestamp or staleness signal existed anywhere here.
            $isStale = $r->env->recorded_at?->lt(now()->subMinutes(30)) ?? false;
        @endphp
        <div class="bg-white rounded-lg border-2 overflow-hidden" style="border-color:{{ $isStale ? '#9b1c24' : $color }}">
            <div class="px-5 py-3 flex items-center justify-between" style="background:{{ $color }}22">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $color }}"></span>
                    <span class="text-sm font-medium text-[#333333]">{{ $r->cage->cage_code }}</span>
                </div>
                <span class="text-xs" style="color: {{ $isStale ? '#9b1c24' : '#6B7280' }};" title="{{ $r->env->recorded_at }}">
                    {{ $isStale ? 'Stale · ' : '' }}{{ $r->env->recorded_at?->diffForHumans() ?? $sensorId }}
                </span>
            </div>
            <div class="px-4 py-3 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-[#6B7280]">Temp:</span>
                    <span class="font-medium text-[#333333]">{{ $r->env->temperature_c }}°C</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-[#6B7280]">Humidity:</span>
                    <span class="font-medium text-[#333333]">{{ $r->env->humidity_pct }}%</span>
                </div>
                {{-- Temp range bar --}}
                <div>
                    <div class="text-xs text-[#6B7280] mb-1">Temp range use</div>
                    <div class="w-full h-1.5 bg-[#F5F6F8] rounded-full">
                        <div class="h-1.5 rounded-full" style="width:{{ min(100, (($r->env->temperature_c - 18) / 12) * 100) }}%;background:{{ $color }}"></div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-1.5 pt-1">
                    <span class="text-xs px-2 py-0.5 rounded" style="background:{{ $tBg }};color:{{ $tTxt }}">Temp {{ $r->tempStatus }}</span>
                    <span class="text-xs px-2 py-0.5 rounded" style="background:{{ $hBg }};color:{{ $hTxt }}">Humidity {{ $r->humStatus }}</span>
                    <span class="text-xs px-2 py-0.5 rounded" style="background:{{ $statusBg }};color:{{ $statusTxt }}">Status {{ $r->status }}</span>
                </div>
            </div>
        </div>
        @endforeach

        {{-- Cages with no sensor --}}
        @foreach($cages as $cage)
        @if($latestPerCage->pluck('cage.id')->doesntContain($cage->id))
        <div class="bg-white rounded-lg border border-dashed border-[#D9D9D9] overflow-hidden">
            <div class="px-5 py-3 bg-[#F5F6F8] flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-gray-300"></span>
                <span class="text-sm text-[#333333]">{{ $cage->cage_code }}</span>
            </div>
            <div class="px-4 py-4 text-center text-xs text-[#6B7280]">
                <i data-lucide="wifi-off" class="w-5 h-5 mx-auto mb-2 text-gray-300"></i>
                No sensor data
            </div>
        </div>
        @endif
        @endforeach
    </div>

    {{-- ── Trend Charts ── --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs tracking-wider text-[#6B7280] mb-3">TEMPERATURE TREND (COOP + PER CAGE)</div>
            <canvas id="envTempChart" height="160"></canvas>
            <div id="envTempChartEmpty" class="hidden h-[160px] flex items-center justify-center text-sm" style="color: #a39e98;">No temperature readings in this window.</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs tracking-wider text-[#6B7280] mb-3">HUMIDITY TREND (COOP + PER CAGE)</div>
            <canvas id="envHumChart" height="160"></canvas>
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
            const labels = ['14:00','16:00','18:00','20:00','22:00','00:00','02:00','04:00','06:00','08:00','10:00','12:00'];
            const hasAnyData = Object.values(trendData).some(rows => rows.length > 0);

            function buildDatasets(field) {
                const sets = [];
                for (const [cageId, rows] of Object.entries(trendData)) {
                    const name = cagesMap[cageId] || 'Cage '+cageId;
                    const data = labels.map(l => {
                        const r = rows.find(r => r.hour === l);
                        return r ? r[field] : null;
                    });
                    sets.push({ label: name, data, borderColor: cageColors[cageId] || '#6B7280', tension: 0.3, pointRadius: 3, borderWidth: 1.5, fill: false });
                }
                return sets;
            }

            const chartOpts = {
                responsive: true,
                plugins: { legend: { display: true, labels: { boxWidth: 10, font: { size: 10 } } } },
                scales: {
                    x: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 }, autoSkip: true, maxRotation: 45, minRotation: 0 } },
                    y: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } } },
                }
            };

            const tempCanvas = document.getElementById('envTempChart');
            const humCanvas  = document.getElementById('envHumChart');
            const tempEmpty  = document.getElementById('envTempChartEmpty');
            const humEmpty   = document.getElementById('envHumChartEmpty');
            if (!tempCanvas || !humCanvas) return;

            tempCanvas.classList.toggle('hidden', !hasAnyData);
            humCanvas.classList.toggle('hidden', !hasAnyData);
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
