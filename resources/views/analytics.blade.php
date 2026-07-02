@extends('layouts.app')
@section('title', 'Analytics')

@section('content')
<div class="space-y-5">

    {{-- ── Page Header ── --}}
    <x-page-header title="Analytics" subtitle="HDEP trends, egg production, and feed correlation charts" />

    {{-- ── Cage + Period Selectors ── --}}
    <div class="flex flex-wrap items-center gap-3">
        {{-- Cage tabs --}}
        @foreach($allCages as $c)
        @php $isActive = $c->cage_code === $cageCode; $cColor = match($c->cage_code){'CAGE-A'=>'#2D7D46','CAGE-B'=>'#1D4E8F','CAGE-C'=>'#C2703E','CAGE-D'=>'#6B4C8A',default=>'#6B7280'}; @endphp
        <a href="{{ route('analytics', ['cage'=>$c->cage_code,'period'=>$period]) }}"
           class="px-3 py-1.5 rounded-lg text-sm transition-colors {{ $isActive ? 'text-white' : 'border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}"
           style="{{ $isActive ? 'background:'.$cColor : '' }}">
            {{ $c->cage_code }}
        </a>
        @endforeach

        {{-- Period tabs --}}
        @foreach(['week'=>'Week','month'=>'Month','3months'=>'3 Months'] as $key => $label)
        @php $isP = $period === $key; @endphp
        <a href="{{ route('analytics', ['cage'=>$cageCode,'period'=>$key]) }}"
           class="px-3 py-1.5 rounded-lg text-sm transition-colors {{ $isP ? 'bg-[#002D5E] text-white' : 'border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}">
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
