@extends('layouts.app')
@section('title', 'Egg Production History')

@section('content')
<div class="space-y-5">

    <x-page-header title="Egg Production History" subtitle="Full timeline of eggs logged since day 1" />

    @include('eggs._tabs', ['activeTab' => 'history'])

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-card>
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Lifetime Total</div>
            <div class="text-4xl font-bold leading-none tracking-[-0.5px]" style="color: #1f1f1f;">{{ number_format($lifetimeEggs) }}</div>
            <div class="text-xs mt-2" style="color: #a39e98;">eggs logged since day 1</div>
        </x-card>

        <x-card>
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Timeline Records</div>
            <div class="text-4xl font-bold leading-none tracking-[-0.5px]" style="color: #1f1f1f;">{{ number_format($timeline->sum('records')) }}</div>
            <div class="text-xs mt-2" style="color: #a39e98;">{{ ucfirst($groupBy) }} aggregates</div>
        </x-card>

        <x-card>
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Active Cages</div>
            <div class="text-4xl font-bold leading-none tracking-[-0.5px]" style="color: #1f1f1f;">{{ $byCage->count() }}</div>
            <div class="text-xs mt-2" style="color: #a39e98;">with production records</div>
        </x-card>

        <x-card>
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Size Records</div>
            <div class="text-4xl font-bold leading-none tracking-[-0.5px]" style="color: #1f1f1f;">{{ number_format($bySize->sum('total')) }}</div>
            <div class="text-xs mt-2" style="color: #a39e98;">eggs with size breakdown</div>
        </x-card>
    </div>

    {{-- Timeline --}}
    <x-card header="Production Timeline">
        <div class="flex items-center gap-2 mb-4">
            <span class="text-xs" style="color: #615d59;">Group by:</span>
            @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $value => $label)
            <a href="{{ route('egg-production-history', ['group_by' => $value]) }}"
               class="text-xs px-3 py-1 rounded-full border transition-colors {{ $groupBy === $value ? 'font-semibold text-white' : '' }}"
               style="{{ $groupBy === $value ? 'background-color: #0075de; border-color: #0075de;' : 'background-color: #ffffff; border-color: #e6e6e6; color: #31302e;' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>

        @if($timeline->isEmpty())
        <div class="rounded-xl border p-10 text-center text-sm" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
            No production records yet.
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="background-color: #f6f5f4; border-color: #e6e6e6;">
                        <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Period</th>
                        <th class="text-right text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Records</th>
                        <th class="text-right text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Total Eggs</th>
                        <th class="text-right text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Cumulative</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $cumulative = $lifetimeEggs;
                    @endphp
                    @foreach($timeline as $row)
                    <tr class="border-b hover:bg-black/[0.02] transition-colors" style="border-color: #e6e6e6;">
                        <td class="px-6 py-3 text-sm" style="color: #1f1f1f;">{{ $row['label'] }}</td>
                        <td class="px-6 py-3 text-sm text-right font-mono" style="color: #1f1f1f;">{{ number_format($row['records']) }}</td>
                        <td class="px-6 py-3 text-sm text-right font-mono" style="color: #1f1f1f;">{{ number_format($row['total_eggs']) }}</td>
                        <td class="px-6 py-3 text-sm text-right font-mono" style="color: #615d59;">{{ number_format($cumulative) }}</td>
                    </tr>
                    @php
                        $cumulative -= $row['total_eggs'];
                    @endphp
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- By Cage --}}
        <x-card header="Breakdown by Cage">
            @if($byCage->isEmpty())
            <div class="rounded-xl border p-10 text-center text-sm" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
                No cage data available.
            </div>
            @else
            <div class="space-y-3">
                @foreach($byCage as $cage)
                <div class="flex items-center justify-between p-3 rounded-lg border" style="background-color: #ffffff; border-color: #e6e6e6;">
                    <div class="flex items-center gap-3">
                        <span class="w-3 h-3 rounded-full" style="background-color: {{ $cage['color'] }};"></span>
                        <span class="text-sm font-semibold" style="color: #1f1f1f;">{{ $cage['cage_code'] }}</span>
                    </div>
                    <span class="text-sm font-mono" style="color: #1f1f1f;">{{ number_format($cage['total_eggs']) }} eggs</span>
                </div>
                @endforeach
            </div>
            @endif
        </x-card>

        {{-- By Size --}}
        <x-card header="Breakdown by Size">
            @if($bySize->isEmpty())
            <div class="rounded-xl border p-10 text-center text-sm" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
                No size breakdowns recorded yet.
            </div>
            @else
            <div class="space-y-3">
                @foreach($bySize as $size)
                <div class="flex items-center justify-between p-3 rounded-lg border" style="background-color: #ffffff; border-color: #e6e6e6;">
                    <span class="text-sm font-semibold capitalize" style="color: #1f1f1f;">{{ $size['size'] }}</span>
                    <span class="text-sm font-mono" style="color: #1f1f1f;">{{ number_format($size['total']) }} eggs</span>
                </div>
                @endforeach
            </div>
            @endif
        </x-card>
    </div>

</div>
@endsection
