{{-- IR Reference Card: the live DHT22 reading next to the standard (optimal)
     range, so it is clear at a glance whether conditions are ideal. This
     judges the live reading only; the hints in the Alert Thresholds dialog
     judge the typed threshold values (same range, separate logic).
     Props: $optimal (EnvironmentStatusService::optimalRange()), $thresholds,
     $reference (DHT22 values, compareToRange() results, sensors, updated_at, stale). --}}
@php
    $toneFor = fn (string $state) => match ($state) {
        'within' => ['#e8f5ec', '#1f6b3a', 'circle-check'],
        'none'   => ['#f1f1ef', '#615d59', 'circle-help'],
        default  => ['#fdf3e0', '#8a5a00', 'circle-alert'],
    };
    $rangeText = fn ($min, $max, $unit) => rtrim(rtrim(number_format($min, 1), '0'), '.') . '–' . rtrim(rtrim(number_format($max, 1), '0'), '.') . ' ' . $unit;
    $metrics = [
        [
            'label'   => 'Temperature',
            'icon'    => 'thermometer',
            'current' => $reference['temp_value'] !== null ? number_format($reference['temp_value'], 1) . ' °C' : '—',
            'optimal' => $rangeText($optimal['temp_min'], $optimal['temp_max'], '°C'),
            'alerts'  => $rangeText($thresholds['temp_min'], $thresholds['temp_max'], '°C'),
            'result'  => $reference['temp'],
            'unit'    => '°C',
        ],
        [
            'label'   => 'Humidity',
            'icon'    => 'droplets',
            'current' => $reference['hum_value'] !== null ? number_format($reference['hum_value'], 1) . ' %' : '—',
            'optimal' => $rangeText($optimal['hum_min'], $optimal['hum_max'], '%'),
            'alerts'  => $rangeText($thresholds['hum_min'], $thresholds['hum_max'], '%'),
            'result'  => $reference['hum'],
            'unit'    => '%',
        ],
    ];
    $allWithin = $reference['temp']['state'] === 'within' && $reference['hum']['state'] === 'within';
    $noData = $reference['temp']['state'] === 'none' && $reference['hum']['state'] === 'none';
@endphp

<section class="bg-white rounded-lg border border-[#D9D9D9] p-5 mb-6" aria-labelledby="ir-reference-title">
    <div class="flex flex-wrap items-start justify-between gap-2 mb-4">
        <div>
            <h2 id="ir-reference-title" class="text-sm font-semibold text-[#1f1f1f]">IR Reference: Current vs. Optimal</h2>
            <p class="text-xs text-[#6B7280] mt-0.5">
                @if($noData)
                    No live DHT22 reading yet. The optimal range is shown for reference.
                @elseif($reference['sensors'] === 1)
                    Current = latest DHT22 reading, {{ $reference['updated_at']->diffForHumans() }}.
                @else
                    Current = average of the latest DHT22 reading from {{ $reference['sensors'] }} sensors, newest {{ $reference['updated_at']->diffForHumans() }}.
                @endif
            </p>
            @if(! $noData && $reference['stale'])
            <p class="text-xs font-medium mt-0.5" style="color:#9b1c24;">Stale: no new sensor reading in the last 30 minutes.</p>
            @endif
        </div>
        @unless($noData)
        <span class="text-[11px] font-semibold uppercase px-2.5 py-1 rounded-full whitespace-nowrap"
              style="background:{{ $allWithin ? '#e8f5ec' : '#fdf3e0' }};color:{{ $allWithin ? '#1f6b3a' : '#8a5a00' }};">
            {{ $allWithin ? 'All within optimal range' : 'Outside optimal range' }}
        </span>
        @endunless
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($metrics as $m)
        @php
            [$bg, $fg, $icon] = $toneFor($m['result']['state']);
            $diff = rtrim(rtrim(number_format($m['result']['diff'], 1), '0'), '.');
            $statusText = match ($m['result']['state']) {
                'within' => 'Within optimal range',
                'above'  => 'Above optimal by ' . $diff . ' ' . $m['unit'],
                'below'  => 'Below optimal by ' . $diff . ' ' . $m['unit'],
                default  => 'No reading',
            };
        @endphp
        <div class="rounded-lg border border-[#E6E6E6] p-4" data-reference-metric="{{ strtolower($m['label']) }}">
            <div class="text-xs font-semibold tracking-wider uppercase text-[#6B7280] mb-3 flex items-center gap-1.5">
                <i data-lucide="{{ $m['icon'] }}" class="w-3.5 h-3.5"></i> {{ $m['label'] }}
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-[#9CA3AF]">Current</div>
                    <div class="text-2xl font-bold leading-tight text-[#1f1f1f]">{{ $m['current'] }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-[#9CA3AF]">Optimal (standard)</div>
                    <div class="text-2xl font-bold leading-tight text-[#6B7280]">{{ $m['optimal'] }}</div>
                </div>
            </div>
            <div class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold" style="background:{{ $bg }};color:{{ $fg }};">
                <i data-lucide="{{ $icon }}" class="w-3.5 h-3.5"></i> {{ $statusText }}
            </div>
            {{-- Lowercase "alert" on purpose: the status words Alert / Watch / OK
                 on this page belong to the per-cage badges. --}}
            <div class="text-[11px] text-[#9CA3AF] mt-2">This farm's alert thresholds: {{ $m['alerts'] }}</div>
        </div>
        @endforeach
    </div>
</section>
