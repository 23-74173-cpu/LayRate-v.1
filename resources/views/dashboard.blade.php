@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<main class="p-5 space-y-5">

    {{-- ── Metric Cards ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Hens --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4 min-h-[104px]">
            <div class="text-[10px] tracking-wider text-[#6B7280] mb-2">TOTAL HENS</div>
            <div class="text-3xl tracking-tight text-[#333333]">{{ number_format($totalHens) }}</div>
            <div class="text-xs text-[#6B7280] mt-1">+0 today</div>
        </div>

        {{-- HDEP --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4 min-h-[104px]">
            <div class="text-[10px] tracking-wider text-[#6B7280] mb-2">TODAY'S HDEP</div>
            <div class="text-3xl tracking-tight text-[#333333]">{{ $todayHdep }}%</div>
            <div class="text-xs mt-1 {{ $hdepDelta >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                {{ $hdepDelta >= 0 ? '↑' : '↓' }} {{ abs($hdepDelta) }}% vs yesterday
            </div>
        </div>

        {{-- Eggs --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4 min-h-[104px]">
            <div class="text-[10px] tracking-wider text-[#6B7280] mb-2">EGGS COLLECTED</div>
            <div class="text-3xl tracking-tight text-[#333333]">{{ number_format($eggsToday) }}</div>
            <div class="text-xs text-[#6B7280] mt-1">auto-counted via IR sensor</div>
        </div>

        {{-- Coop Environment --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4 min-h-[104px]">
            <div class="text-[10px] tracking-wider text-[#6B7280] mb-2">COOP ENVIRONMENT</div>
            <div class="grid grid-cols-2 gap-2 mb-2">
                <div>
                    <div class="text-[10px] text-[#6B7280]">TEMP</div>
                    <div class="text-xl tracking-tight text-[#333333]">{{ $avgTemp ? $avgTemp.'°' : '—' }}</div>
                </div>
                <div>
                    <div class="text-[10px] text-[#6B7280]">HUMIDITY</div>
                    <div class="text-xl tracking-tight text-[#333333]">{{ $avgHum ? $avgHum.'%' : '—' }}</div>
                </div>
            </div>
            <div class="flex gap-2">
                <span class="text-[10px] bg-[#D5E8D4] text-[#004F9F] px-2 py-0.5 rounded">Temp OK</span>
                <span class="text-[10px] bg-[#D5E8D4] text-[#004F9F] px-2 py-0.5 rounded">Humidity OK</span>
            </div>
        </div>
    </div>

    {{-- ── Cage Overview ── --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-medium text-[#333333]">Cage Overview</h2>
            <span class="text-[11px] text-[#6B7280]">Drag to reorder · Click Edit or View to manage</span>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach($cages as $cage)
            @php
                $prod = $cage->latestProduction;
                $hdep = $prod?->hdep ?? 0;
                $color = $cage->color;
                $hen = $cage->hens->first();
                $hdepBg = $hdep > 70 ? '#D5E8D4' : ($hdep > 40 ? '#FFF3CD' : '#F8D7DA');
                $hdepTxt = $hdep > 70 ? '#004F9F' : ($hdep > 40 ? '#856404' : '#721C24');
                $rows = ['A','B','C'];
                $cols = range(1,10);
            @endphp
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
                <div class="flex items-start justify-between mb-1">
                    <div>
                        <span class="text-sm font-medium" style="color:{{ $color }}">{{ $cage->cage_code }}</span>
                        <span class="text-sm text-[#6B7280] ml-2">{{ $hen?->breed ?? '—' }}</span>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded" style="background:{{ $hdepBg }};color:{{ $hdepTxt }}">{{ number_format($hdep,1) }}%</span>
                </div>
                <div class="text-[11px] text-[#6B7280] mb-3 flex gap-3">
                    <span>{{ $cage->capacity }} hens</span>
                    @if($hen)
                    <span>{{ $hen->current_age_weeks }} wks</span>
                    @endif
                    <span class="px-1.5 py-0.5 rounded text-[10px] {{ $cage->is_active ? 'bg-[#D5E8D4] text-[#2D6A4F]' : 'bg-gray-200 text-gray-500' }}">
                        {{ $cage->is_active ? '2 sensor' : 'No sensor' }}
                    </span>
                </div>
                {{-- Slot grid --}}
                <div class="mb-3 overflow-x-auto">
                    <div class="min-w-[360px] space-y-1">
                        @foreach($rows as $row)
                        <div class="flex items-center gap-1">
                            <div class="w-6 text-[11px] text-[#6B7280] shrink-0">{{ $row }}</div>
                            @foreach($cols as $col)
                            <div class="flex-1 text-center text-[10px] py-1.5 rounded bg-[#F5F6F8] text-[#6B7280] hover:bg-[#EAF0F8] cursor-pointer">
                                {{ $row }}{{ $col }}
                            </div>
                            @endforeach
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('cages.index') }}" class="flex-1 flex items-center justify-center gap-1.5 bg-[#F5F6F8] text-[#6B7280] py-2 rounded text-xs hover:bg-[#EAF0F8] border border-[#D9D9D9]">
                        <i data-lucide="pencil" class="w-3 h-3"></i> Edit
                    </a>
                    <a href="{{ route('analytics', ['cage' => $cage->cage_code]) }}" class="flex-1 text-center text-white py-2 rounded text-xs" style="background-color:{{ $color }}">
                        View Detail
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── Bottom Row 1: Feed / Mortality / Tasks ── --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

        {{-- Feed Today --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <h3 class="text-sm font-medium text-[#333333] mb-3">Feed Today</h3>
            @forelse($feedToday as $cageCode => $feed)
            @php
                $fColor = match($cageCode) { 'CAGE-A'=>'#2D7D46','CAGE-B'=>'#1D4E8F','CAGE-C'=>'#C2703E','CAGE-D'=>'#6B4C8A',default=>'#6B7280' };
                $total = 48;
                $consumed = $feed->feed_consumed_kg;
                $pct = min(100, round(($consumed/$total)*100));
            @endphp
            <div class="mb-3">
                <div class="flex justify-between mb-1">
                    <span class="text-xs" style="color:{{ $fColor }}">{{ $cageCode }}</span>
                    <span class="text-xs text-[#6B7280]">{{ number_format($consumed,0) }} / {{ $total }} kg</span>
                </div>
                <div class="w-full bg-[#F5F6F8] rounded-full h-2">
                    <div class="h-2 rounded-full" style="width:{{ $pct }}%;background:{{ $fColor }}"></div>
                </div>
                <div class="text-[10px] mt-0.5 {{ $pct < 80 ? 'text-amber-600' : 'text-[#6B7280]' }}">{{ $pct }}% consumed</div>
            </div>
            @empty
            <p class="text-xs text-[#6B7280]">No feed data for today.</p>
            @endforelse
            <div class="pt-2 border-t border-[#D9D9D9] flex justify-between text-xs mt-2">
                <span class="text-[#6B7280]">Total consumed</span>
                <span class="text-[#333333] font-medium">{{ number_format($feedToday->sum('feed_consumed_kg'), 0) }} kg</span>
            </div>
        </div>

        {{-- Mortality Today --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-medium text-[#333333]">Mortality Today</h3>
                <span class="text-[10px] {{ $mortalityTodayTotal > 0 ? 'bg-[#F8D7DA] text-[#721C24]' : 'bg-[#D5E8D4] text-[#2D6A4F]' }} px-2 py-0.5 rounded">
                    {{ $mortalityTodayTotal }} total
                </span>
            </div>
            @foreach($cages as $cage)
            @php
                $fColor = $cage->color;
                $mCount = $mortalityToday[$cage->cage_code] ?? 0;
            @endphp
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-2.5 h-2.5 rounded-full" style="background:{{ $fColor }}"></div>
                    <span class="text-xs text-[#333333]">{{ $cage->cage_code }}</span>
                </div>
                @if($mCount > 0)
                <span class="text-xs text-[#721C24] font-medium">{{ $mCount }} {{ $mCount === 1 ? 'hen' : 'hens' }}</span>
                @else
                <span class="text-xs text-[#6B7280]">None</span>
                @endif
            </div>
            @endforeach
            <div class="pt-2 border-t border-[#D9D9D9] mt-1">
                <a href="{{ route('mortality.index') }}" class="text-[11px] text-[#102A4C] hover:underline">View full mortality log →</a>
            </div>
        </div>

        {{-- Recent Alerts --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-medium text-[#333333]">Recent Alerts</h3>
                @if($alertCount > 0)
                <form action="{{ route('alerts.read-all') }}" method="POST">
                    @csrf
                    <button class="text-[10px] text-[#6B7280] hover:text-[#333333] transition-colors">Mark all read</button>
                </form>
                @endif
            </div>
            @forelse($recentAlerts as $alert)
            @php
                $isUnread = ! $alert->is_read;
                $cageColor = $alert->cage?->color ?? '#6B7280';
            @endphp
            <div class="flex items-start gap-2 mb-3 {{ $isUnread ? '' : 'opacity-50' }}">
                <div class="mt-1 shrink-0">
                    <div class="w-2 h-2 rounded-full {{ $isUnread ? 'bg-red-400' : 'bg-gray-300' }}"></div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 mb-0.5">
                        <span class="text-[10px] font-medium" style="color:{{ $cageColor }}">
                            {{ $alert->cage?->cage_code ?? '—' }}
                        </span>
                        <span class="text-[10px] text-[#6B7280]">{{ $alert->triggered_at->diffForHumans() }}</span>
                    </div>
                    <p class="text-xs text-[#333333] leading-tight truncate">{{ $alert->message }}</p>
                </div>
                @if($isUnread)
                <form action="{{ route('alerts.read', $alert) }}" method="POST" class="shrink-0">
                    @csrf
                    <button class="text-[9px] text-[#6B7280] hover:text-[#333333] mt-1 transition-colors">✓</button>
                </form>
                @endif
            </div>
            @empty
            <p class="text-xs text-[#6B7280] py-2">No alerts recorded.</p>
            @endforelse
            @if($alertCount > 0)
            <div class="pt-2 border-t border-[#D9D9D9] mt-1">
                <span class="text-[11px] text-[#721C24]">{{ $alertCount }} unread alert{{ $alertCount > 1 ? 's' : '' }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Bottom Row 2: Env Summary / Live Readings ── --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

        {{-- Environmental Summary --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <h3 class="text-sm font-medium text-[#333333] mb-3">Environmental Summary</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-[#F5F6F8] p-3 rounded-lg border border-[#D9D9D9]">
                    <div class="text-[10px] tracking-wider text-[#6B7280] mb-1">TEMPERATURE</div>
                    <div class="text-2xl tracking-tight mb-2 text-[#333333]">{{ $avgTemp ? $avgTemp.'°C' : '—' }}</div>
                    <span class="text-[10px] bg-[#D5E8D4] text-[#2D6A4F] px-2 py-0.5 rounded">In range</span>
                    <div class="text-[10px] text-[#6B7280] mt-2">Last reading: 2 mins ago</div>
                </div>
                <div class="bg-[#F5F6F8] p-3 rounded-lg border border-[#D9D9D9]">
                    <div class="text-[10px] tracking-wider text-[#6B7280] mb-1">HUMIDITY</div>
                    <div class="text-2xl tracking-tight mb-2 text-[#333333]">{{ $avgHum ? $avgHum.'%' : '—' }}</div>
                    <span class="text-[10px] bg-[#D5E8D4] text-[#2D6A4F] px-2 py-0.5 rounded">In range</span>
                    <div class="text-[10px] text-[#6B7280] mt-2">Last reading: 2 mins ago</div>
                </div>
            </div>
        </div>

        {{-- Live Readings --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <h3 class="text-sm font-medium text-[#333333] mb-3">Live Readings</h3>
            <div class="grid grid-cols-2 gap-3">
                @foreach($liveReadings as $r)
                @php
                    $sBg  = $r->status === 'Normal' ? '#D5E8D4' : ($r->status === 'Watch' ? '#FFF3CD' : '#F8D7DA');
                    $sTxt = $r->status === 'Normal' ? '#2D6A4F' : ($r->status === 'Watch' ? '#856404' : '#721C24');
                @endphp
                <div class="rounded-lg overflow-hidden border border-[#D9D9D9]">
                    <div class="flex items-center justify-between px-3 py-1.5" style="background:{{ $r->color }}">
                        <span class="text-[11px] text-white">{{ $r->cage }}</span>
                        <span class="text-[9px] px-1.5 py-0.5 rounded" style="background:{{ $sBg }};color:{{ $sTxt }}">{{ $r->status }}</span>
                    </div>
                    <div class="bg-white px-3 py-2 flex gap-4">
                        <div>
                            <div class="text-[9px] text-[#6B7280] tracking-wider">TEMPERATURE</div>
                            <div class="text-sm text-[#333333]">{{ $r->temp }}</div>
                        </div>
                        <div>
                            <div class="text-[9px] text-[#6B7280] tracking-wider">HUMIDITY</div>
                            <div class="text-sm text-[#333333]">{{ $r->hum }}</div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

</main>
@endsection

@push('scripts')
<script>lucide.createIcons();</script>
@endpush
