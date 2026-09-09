@extends('layouts.app')
@section('title', 'Egg Management')

@section('content')
<div class="space-y-5">

    <x-page-header title="Egg Management" subtitle="Full timeline of eggs logged since day 1" subtitle-id="egg-header-subtitle" />

    @include('eggs._tabs', ['activeTab' => 'history'])

    <turbo-frame id="egg-content">
    <div class="space-y-5">

    {{-- Summary cards — dashboard gradient KPI design --}}
    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <x-kpi-card label="Lifetime Total" icon="egg" cardGradient="linear-gradient(135deg,#d97706,#C2703E)" delay="0ms" :value="number_format($lifetimeEggs)">
            <div class="text-xs mt-1.5 font-medium" style="color: rgba(255,255,255,0.85);">eggs logged since day 1</div>
        </x-kpi-card>

        <x-kpi-card label="Timeline Records" icon="layers" cardGradient="linear-gradient(135deg,#8B5CF6,#6B4C8A)" delay="60ms" :value="number_format($timelineRecordsTotal)">
            <div class="text-xs mt-1.5 font-medium" style="color: rgba(255,255,255,0.85);">{{ ucfirst($groupBy) }} aggregates</div>
        </x-kpi-card>

        <x-kpi-card label="Active Cages" icon="warehouse" cardGradient="linear-gradient(135deg,#0075de,#1D4E8F)" delay="120ms" :value="$byCage->count()">
            <div class="text-xs mt-1.5 font-medium" style="color: rgba(255,255,255,0.85);">with production records</div>
        </x-kpi-card>

        <x-kpi-card label="Size Records" icon="package" cardGradient="linear-gradient(135deg,#16a34a,#2D7D46)" delay="180ms" :value="number_format($bySize->sum('total'))">
            <div class="text-xs mt-1.5 font-medium" style="color: rgba(255,255,255,0.85);">eggs with size breakdown</div>
        </x-kpi-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
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

    {{-- Timeline --}}
    <x-card header="Production Timeline">
        <div class="flex items-center gap-2 mb-4">
            <span class="text-xs" style="color: #615d59;">Group by:</span>
            @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $value => $label)
            <a href="{{ route('egg-production-history', ['group_by' => $value]) }}"
               data-turbo-frame="egg-content"
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
                        <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-5 py-3" style="color: #615d59;">Period</th>
                        <th class="text-right text-xs font-semibold tracking-[0.125px] uppercase px-5 py-3" style="color: #615d59;">Records</th>
                        <th class="text-right text-xs font-semibold tracking-[0.125px] uppercase px-5 py-3" style="color: #615d59;">Total Eggs</th>
                        <th class="text-right text-xs font-semibold tracking-[0.125px] uppercase px-5 py-3" style="color: #615d59;">Cumulative</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($timeline as $row)
                    <tr class="border-b hover:bg-black/[0.02] transition-colors" style="border-color: #e6e6e6;">
                        <td class="px-5 py-3.5 text-sm" style="color: #1f1f1f;">{{ $row['label'] }}</td>
                        <td class="px-5 py-3.5 text-sm text-right font-mono" style="color: #1f1f1f;">{{ number_format($row['records']) }}</td>
                        <td class="px-5 py-3.5 text-sm text-right font-mono" style="color: #1f1f1f;">{{ number_format($row['total_eggs']) }}</td>
                        <td class="px-5 py-3.5 text-sm text-right font-mono" style="color: #615d59;">{{ number_format($row['cumulative']) }}</td>
                    </tr>
                    @endforeach
                    @if($timeline->count() > 0)
                    {{-- Blank filler rows to keep the table at a consistent height (perPage rows). --}}
                    @for($i = 0; $i < $timeline->perPage() - $timeline->count(); $i++)
                    <tr class="empty-filler-row" aria-hidden="true">
                        <td colspan="4" class="px-5 py-3.5">&nbsp;</td>
                    </tr>
                    @endfor
                    @endif
                </tbody>
            </table>
            <x-paginator :paginator="$timeline" />
        </div>
        @endif
    </x-card>

    </div>
</turbo-frame>
</div>
@endsection
