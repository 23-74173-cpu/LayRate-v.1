<turbo-frame id="dashboard-stats">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
        {{-- Total Hens --}}
        <div class="kpi-card relative rounded-xl border p-6 cursor-pointer transition-shadow hover:shadow-md"
             style="background-color: #ffffff; border-color: #e6e6e6;"
             role="link" tabindex="0" aria-label="Go to Cages"
             data-nav="{{ route('cages.index') }}" data-kpi="hens">
            <button type="button" class="absolute top-4 right-4 p-1 rounded-full hover:bg-black/5 transition-colors"
                    onclick="event.stopPropagation(); openKpiModal('hens')" aria-label="Hens per cage breakdown">
                <i data-lucide="info" class="w-4 h-4" style="color: #a39e98;"></i>
            </button>
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Total Hens</div>
            <div class="text-4xl font-bold leading-none tracking-[-0.5px]" style="color: #1f1f1f;">{{ number_format($totalHens) }}</div>
            <div class="text-xs mt-2" style="color: #a39e98;">across {{ $cages->count() }} cages</div>
        </div>

        {{-- HDEP --}}
        <div class="kpi-card relative rounded-xl border p-6 cursor-pointer transition-shadow hover:shadow-md"
             style="background-color: #ffffff; border-color: #e6e6e6;"
             role="link" tabindex="0" aria-label="Go to Egg Logging"
             data-nav="{{ route('eggs.logging') }}" data-kpi="hdep">
            <button type="button" class="absolute top-4 right-4 p-1 rounded-full hover:bg-black/5 transition-colors"
                    onclick="event.stopPropagation(); openKpiModal('hdep')" aria-label="HDEP per cage breakdown">
                <i data-lucide="info" class="w-4 h-4" style="color: #a39e98;"></i>
            </button>
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Today's HDEP</div>
            <div class="text-4xl font-bold leading-none tracking-[-0.5px]" style="color: #1f1f1f;">{{ $todayHdep }}%</div>
            <div class="text-xs mt-2" style="color: {{ $hdepDelta >= 0 ? '#1f6b3a' : '#9b1c24' }};">
                {{ $hdepDelta >= 0 ? '▲' : '▼' }} {{ abs($hdepDelta) }}% vs yesterday
            </div>
        </div>

        {{-- Eggs Collected --}}
        <div class="kpi-card relative rounded-xl border p-6 cursor-pointer transition-shadow hover:shadow-md"
             style="background-color: #ffffff; border-color: #e6e6e6;"
             role="link" tabindex="0" aria-label="Go to Egg Logging"
             data-nav="{{ route('eggs.logging') }}" data-kpi="eggs">
            <button type="button" class="absolute top-4 right-4 p-1 rounded-full hover:bg-black/5 transition-colors"
                    onclick="event.stopPropagation(); openKpiModal('eggs')" aria-label="Eggs per cage breakdown">
                <i data-lucide="info" class="w-4 h-4" style="color: #a39e98;"></i>
            </button>
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Eggs Collected</div>
            <div class="text-4xl font-bold leading-none tracking-[-0.5px]" style="color: #1f1f1f;">{{ number_format($eggsToday) }}</div>
            <div class="text-xs mt-2" style="color: #a39e98;">manual entry · logged by operator</div>
        </div>

        {{-- Coop Environment --}}
        <div class="kpi-card relative rounded-xl border p-6 cursor-pointer transition-shadow hover:shadow-md"
             style="background-color: #ffffff; border-color: #e6e6e6;"
             role="link" tabindex="0" aria-label="Go to Environment"
             data-nav="{{ route('environment') }}" data-kpi="env">
            <button type="button" class="absolute top-4 right-4 p-1 rounded-full hover:bg-black/5 transition-colors"
                    onclick="event.stopPropagation(); openKpiModal('env')" aria-label="Environment per cage breakdown">
                <i data-lucide="info" class="w-4 h-4" style="color: #a39e98;"></i>
            </button>
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Coop Environment</div>
            <div class="grid grid-cols-2 gap-2 mb-2">
                <div>
                    <div class="text-xs" style="color: #a39e98;">Temp</div>
                    <div class="text-3xl font-bold leading-none tracking-[-0.5px]" style="color: #1f1f1f;">{{ $avgTemp ? $avgTemp.'°C' : '—' }}</div>
                </div>
                <div>
                    <div class="text-xs" style="color: #a39e98;">Humidity</div>
                    <div class="text-3xl font-bold leading-none tracking-[-0.5px]" style="color: #1f1f1f;">{{ $avgHum ? $avgHum.'%' : '—' }}</div>
                </div>
            </div>
            <x-status-badge status="Normal" type="sensor" />
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
        env: {
            title: 'Environment by Cage',
            rows: {!! $liveReadings->map(fn($r) => ['label' => $r->cage, 'color' => $r->color, 'value' => $r->temp . ' · ' . $r->hum . ' · ' . $r->status])->values()->toJson() !!}
        }
    });
    if (typeof bindKpiCards === 'function') bindKpiCards(document.getElementById('dashboard-stats'));
    </script>
</turbo-frame>
