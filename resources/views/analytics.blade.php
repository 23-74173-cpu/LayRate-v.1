@extends('layouts.app')
@section('title', 'Analytics')

@section('content')
<div class="space-y-5">

    {{-- ── Page Header ── --}}
    <x-page-header title="Analytics" subtitle="HDEP trends, egg production, and feed correlation charts" />

    {{-- ── Summary KPI Cards ── --}}
    @php $cColor = $cage->color; @endphp
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Cage</div>
            <div class="text-2xl font-bold leading-none tracking-[-0.5px]" style="color:{{ $cColor }}">{{ $cageCode }}</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Breed</div>
            <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ $cage->hens->first()?->breed ?? '—' }}</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Avg HDEP</div>
            <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ $avgHdep }}%</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Best Day</div>
            <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ $bestDay }}%</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Worst Day</div>
            <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ $worstDay }}%</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Flock Age</div>
            <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ $cage->hens->first() ? $cage->hens->first()->current_age_weeks . ' wks' : '—' }}</div>
        </div>
    </div>

    {{-- ── Cage + Period Selectors (dashboard-style tabs) ── --}}
    <div class="flex flex-wrap items-center gap-0 border-b overflow-x-auto" style="border-color: #e6e6e6;">
        @foreach($allCages as $c)
        @php $isActive = $c->cage_code === $cageCode; $cColor = $c->color; @endphp
        <a href="{{ route('analytics', ['cage'=>$c->cage_code,'period'=>$period]) }}"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
           style="border-bottom-color: {{ $isActive ? $cColor : 'transparent' }}; color: {{ $isActive ? '#1f1f1f' : '#6B7280' }};">
            <span class="inline-block w-2 h-2 rounded-full mr-1.5" style="background-color: {{ $cColor }};"></span>
            {{ $c->cage_code }}
        </a>
        @endforeach

        <span class="flex-1"></span>

        @foreach(['week'=>'Week','month'=>'Month','3months'=>'3 Months'] as $key => $label)
        @php $isP = $period === $key; @endphp
        <a href="{{ route('analytics', ['cage'=>$cageCode,'period'=>$key]) }}"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
           style="border-bottom-color: {{ $isP ? '#002D5E' : 'transparent' }}; color: {{ $isP ? '#1f1f1f' : '#6B7280' }};">
            {{ $label }}
        </a>
        @endforeach
    </div>

    {{-- ── Charts + Summary (lazy) ── --}}
    <turbo-frame id="analytics-charts" src="{{ route('analytics.charts', ['cage' => $cageCode, 'period' => $period]) }}" loading="lazy">
        @include('analytics._charts-skeleton')
    </turbo-frame>

</div>
@endsection
