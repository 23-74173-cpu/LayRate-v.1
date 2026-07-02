<turbo-frame id="dashboard-stats">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
        {{-- Total Hens --}}
        <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Total Hens</div>
            <div class="text-[22px] font-bold leading-[1.27] tracking-[-0.25px]" style="color: #1f1f1f;">{{ number_format($totalHens) }}</div>
            <div class="text-xs mt-1" style="color: #a39e98;">across {{ $cages->count() }} cages</div>
        </div>

        {{-- HDEP --}}
        <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Today's HDEP</div>
            <div class="text-[22px] font-bold leading-[1.27] tracking-[-0.25px]" style="color: #1f1f1f;">{{ $todayHdep }}%</div>
            <div class="text-xs mt-1" style="color: {{ $hdepDelta >= 0 ? '#1f6b3a' : '#9b1c24' }};">
                {{ $hdepDelta >= 0 ? '▲' : '▼' }} {{ abs($hdepDelta) }}% vs yesterday
            </div>
        </div>

        {{-- Eggs Collected --}}
        <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Eggs Collected</div>
            <div class="text-[22px] font-bold leading-[1.27] tracking-[-0.25px]" style="color: #1f1f1f;">{{ number_format($eggsToday) }}</div>
            <div class="text-xs mt-1" style="color: #a39e98;">manual entry · logged by operator</div>
        </div>

        {{-- Coop Environment --}}
        <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Coop Environment</div>
            <div class="grid grid-cols-2 gap-2 mb-2">
                <div>
                    <div class="text-xs" style="color: #a39e98;">Temp</div>
                    <div class="text-lg font-semibold" style="color: #1f1f1f;">{{ $avgTemp ? $avgTemp.'°C' : '—' }}</div>
                </div>
                <div>
                    <div class="text-xs" style="color: #a39e98;">Humidity</div>
                    <div class="text-lg font-semibold" style="color: #1f1f1f;">{{ $avgHum ? $avgHum.'%' : '—' }}</div>
                </div>
            </div>
            <x-status-badge status="Normal" type="sensor" />
        </div>
    </div>
</turbo-frame>
