@extends('layouts.app')
@section('title', 'Forecast')

@section('content')
<div class="space-y-5">

    <x-page-header title="Forecast" subtitle="Project egg production based on historical HDEP trends" />

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- ── Inputs Panel ── --}}
        <x-card>
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">FORECAST INPUTS</div>
            <form method="POST" action="{{ route('forecast.generate') }}" id="forecastForm">
                @csrf
                <input type="hidden" name="scope" value="{{ $scope }}" id="formScope">
                <input type="hidden" name="cage" value="{{ $cageCode }}" id="formCage">

                <label class="block text-sm text-[#333333] mb-2">Scope</label>
                <div class="flex flex-col gap-2 mb-4">
                    <a href="{{ route('forecast', ['scope'=>'farm','horizon'=>$horizon]) }}"
                       class="flex items-center justify-center gap-2 overflow-hidden py-2 rounded-lg text-sm border whitespace-nowrap {{ $scope === 'farm' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                        <i data-lucide="globe" class="w-4 h-4 shrink-0"></i> Whole Farm
                    </a>
                    <a href="{{ route('forecast', ['scope'=>'cage','cage'=>$cageCode,'horizon'=>$horizon]) }}"
                       class="flex items-center justify-center gap-2 overflow-hidden py-2 rounded-lg text-sm border whitespace-nowrap {{ $scope === 'cage' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                        <i data-lucide="box" class="w-4 h-4 shrink-0"></i> Per Cage
                    </a>
                    <a href="{{ route('forecast', ['scope'=>'breed','breed'=>$allBreeds->first() ?? 'ISA Brown','horizon'=>$horizon]) }}"
                       class="flex items-center justify-center gap-2 overflow-hidden py-2 rounded-lg text-sm border whitespace-nowrap {{ $scope === 'breed' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                        <i data-lucide="bird" class="w-4 h-4 shrink-0"></i> Per Breed
                    </a>
                </div>

                @if($scope === 'cage')
                <label class="block text-sm text-[#333333] mb-2">Select Cage</label>
                <select name="cage" onchange="this.form.submit()"
                        class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">
                    @foreach($allCages as $c)
                    <option value="{{ $c->cage_code }}" {{ $c->cage_code === $cageCode ? 'selected' : '' }}>{{ $c->cage_code }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-[#6B7280] mb-4">Forecasting: <span class="font-medium text-[#333333]">{{ $cageCode }}</span></p>

                @elseif($scope === 'breed')
                <label class="block text-sm text-[#333333] mb-2">Select Breed</label>
                <select name="breed" onchange="this.form.submit()"
                        class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">
                    @foreach($allBreeds as $b)
                    <option value="{{ $b }}" {{ $breed === $b ? 'selected' : '' }}>{{ $b }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-[#6B7280] mb-4">Forecasting: <span class="font-medium text-[#002D5E]">{{ $breed }}</span></p>

                @else
                <input type="hidden" name="cage" value="{{ $cageCode }}">
                <p class="text-xs text-[#6B7280] mb-4">Forecasting: <span class="font-medium text-[#333333]">Whole Farm</span></p>
                @endif

                <label class="block text-sm text-[#333333] mb-2">Forecast horizon</label>
                <div class="flex gap-4 mb-5">
                    @foreach([7,14,30] as $h)
                    <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                        <input type="radio" name="horizon" value="{{ $h }}" {{ $horizon == $h ? 'checked' : '' }} class="accent-[#002D5E]">
                        {{ $h }} days
                    </label>
                    @endforeach
                </div>

                <button type="submit" class="w-full bg-[#002D5E] text-white py-2.5 rounded-lg text-sm hover:bg-[#001F42] transition-colors">
                    Generate Forecast
                </button>
            </form>
        </x-card>

        {{-- ── Results (lazy): chart + forecast table ── --}}
        <turbo-frame id="forecast-results" src="{{ route('forecast.results', request()->only(['scope','cage','breed','horizon'])) }}" loading="lazy">
            @include('forecast._results-skeleton')
        </turbo-frame>
    </div>

</div>
@endsection
