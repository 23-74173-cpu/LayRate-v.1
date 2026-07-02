<turbo-frame id="environment-live-data">
    {{-- ── Top Metric Cards ── --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs tracking-wider text-[#6B7280] mb-2">COOP AVG TEMPERATURE</div>
            <div class="flex items-end gap-2 mb-1">
                <span class="text-3xl tracking-tight text-[#333333]">{{ $avgTemp ? number_format($avgTemp,1) . '°C' : '—' }}</span>
                <span class="text-xs bg-[#D5E8D4] text-[#2D6A4F] px-2 py-0.5 rounded mb-1">In Range</span>
            </div>
            <div class="text-xs text-[#6B7280]">Spread across sensors: 1.3°C</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs tracking-wider text-[#6B7280] mb-2">COOP AVG HUMIDITY</div>
            <div class="flex items-end gap-2 mb-1">
                <span class="text-3xl tracking-tight text-[#333333]">{{ $avgHum ? number_format($avgHum,1) . '%' : '—' }}</span>
                <span class="text-xs bg-[#D5E8D4] text-[#2D6A4F] px-2 py-0.5 rounded mb-1">In Range</span>
            </div>
            <div class="text-xs text-[#6B7280]">Spread across sensors: 4.4%</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs tracking-wider text-[#6B7280] mb-2">ACTIVE SENSORS</div>
            <div class="flex items-end gap-2 mb-1">
                <span class="text-3xl tracking-tight text-[#333333]">{{ $latestPerCage->count() }}</span>
                <span class="text-xs bg-[#D5E8D4] text-[#2D6A4F] px-2 py-0.5 rounded mb-1">Live</span>
            </div>
            <div class="text-xs text-[#6B7280]">One sensor node mapped per cage.</div>
        </div>
    </div>

    {{-- ── Alert Threshold Configuration ── --}}
    <x-card>
        <h3 class="text-xs font-semibold tracking-[0.05em] uppercase mb-4" style="color: #615d59;">Alert Threshold Configuration</h3>
        <form action="{{ route('environment.thresholds') }}" method="POST">
            @csrf
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">TEMP MIN (°C)</label>
                    <input type="number" name="temp_min" step="0.5"
                           value="{{ $thresholds['temp_min'] }}"
                           class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm w-24 focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">TEMP MAX (°C)</label>
                    <input type="number" name="temp_max" step="0.5"
                           value="{{ $thresholds['temp_max'] }}"
                           class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm w-24 focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">HUMIDITY MIN (%)</label>
                    <input type="number" name="hum_min" step="1"
                           value="{{ $thresholds['hum_min'] }}"
                           class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm w-24 focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">HUMIDITY MAX (%)</label>
                    <input type="number" name="hum_max" step="1"
                           value="{{ $thresholds['hum_max'] }}"
                           class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm w-24 focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <button type="submit"
                        class="bg-[#102A4C] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#1D4E8F] transition-colors">
                    Save Thresholds
                </button>
            </div>
            @if($errors->any())
            <div class="mt-3 text-xs text-red-500">{{ $errors->first() }}</div>
            @endif
        </form>
    </x-card>

    {{-- ── Per-cage Sensor Cards ── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mt-5 mb-8">
        @foreach($latestPerCage as $r)
        @php
            $color = $r->cage->color;
            $sensorId = 'S-0' . $loop->iteration;
            $statusBg  = $r->status === 'Normal' ? '#D5E8D4' : ($r->status === 'Watch' ? '#FFF3CD' : '#F8D7DA');
            $statusTxt = $r->status === 'Normal' ? '#2D6A4F' : ($r->status === 'Watch' ? '#856404' : '#721C24');
            $tBg  = $r->tempStatus === 'OK' ? '#D5E8D4' : ($r->tempStatus === 'Watch' ? '#FFF3CD' : '#F8D7DA');
            $tTxt = $r->tempStatus === 'OK' ? '#2D6A4F' : ($r->tempStatus === 'Watch' ? '#856404' : '#721C24');
            $hBg  = $r->humStatus  === 'OK' ? '#D5E8D4' : ($r->humStatus  === 'Watch' ? '#FFF3CD' : '#F8D7DA');
            $hTxt = $r->humStatus  === 'OK' ? '#2D6A4F' : ($r->humStatus  === 'Watch' ? '#856404' : '#721C24');
        @endphp
        <div class="bg-white rounded-lg border-2 overflow-hidden" style="border-color:{{ $color }}">
            <div class="px-4 py-2.5 flex items-center justify-between" style="background:{{ $color }}22">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $color }}"></span>
                    <span class="text-sm font-medium text-[#333333]">{{ $r->cage->cage_code }}</span>
                </div>
                <span class="text-xs text-[#6B7280]">{{ $sensorId }}</span>
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
            <div class="px-4 py-2.5 bg-[#F5F6F8] flex items-center gap-2">
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
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs tracking-wider text-[#6B7280] mb-3">TEMPERATURE TREND (COOP + PER CAGE)</div>
            <canvas id="envTempChart" height="160"></canvas>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs tracking-wider text-[#6B7280] mb-3">HUMIDITY TREND (COOP + PER CAGE)</div>
            <canvas id="envHumChart" height="160"></canvas>
        </div>
    </div>



    <script>
    (function() {
        const cageColors  = ['#2D7D46','#1D4E8F','#C2703E','#6B4C8A','#6B7280'];
        const trendData   = @json($trendData);
        const cagesMap    = @json($cages->pluck('cage_code','id'));
        const labels = ['14:00','16:00','18:00','20:00','22:00','00:00','02:00','04:00','06:00','08:00','10:00','12:00'];

        function buildDatasets(field) {
            const sets = [];
            let i = 0;
            for (const [cageId, rows] of Object.entries(trendData)) {
                const name = cagesMap[cageId] || 'Cage '+cageId;
                const data = labels.map(l => {
                    const r = rows.find(r => r.hour === l);
                    return r ? r[field] : null;
                });
                sets.push({ label: name, data, borderColor: cageColors[i%cageColors.length], tension: 0.3, pointRadius: 3, borderWidth: 1.5, fill: false });
                i++;
            }
            return sets;
        }

        const chartOpts = {
            responsive: true,
            plugins: { legend: { display: true, labels: { boxWidth: 10, font: { size: 10 } } } },
            scales: {
                x: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } } },
                y: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } } },
            }
        };

        function initEnvCharts() {
            const tempCanvas = document.getElementById('envTempChart');
            const humCanvas  = document.getElementById('envHumChart');
            if (!tempCanvas || !humCanvas) return;

            if (window.envTempChart) window.envTempChart.destroy();
            window.envTempChart = new Chart(tempCanvas, { type:'line', data:{ labels, datasets: buildDatasets('avg_temp') }, options: {...chartOpts, scales:{...chartOpts.scales, y:{...chartOpts.scales.y, min:24,max:32}}} });

            if (window.envHumChart) window.envHumChart.destroy();
            window.envHumChart = new Chart(humCanvas, { type:'line', data:{ labels, datasets: buildDatasets('avg_hum')  }, options: {...chartOpts, scales:{...chartOpts.scales, y:{...chartOpts.scales.y, min:50,max:80}}} });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initEnvCharts);
        } else {
            initEnvCharts();
        }
    })();
    </script>
</turbo-frame>
