@extends('layouts.app')
@section('title', 'Egg Management')

@section('content')
<div class="space-y-5">

    <x-page-header title="Egg Management" subtitle="Daily egg logs calendar" subtitle-id="egg-header-subtitle" />

    @include('eggs._tabs', ['activeTab' => 'production-history'])

    <turbo-frame id="egg-content">
    <div id="eggProductionHistoryCalendar" class="space-y-5" data-day-url="{{ url('eggs/production-history/day') }}" data-can-delete="{{ auth()->user()->isAdmin() ? '1' : '0' }}">

        <turbo-frame id="dashboard-calendar" src="{{ route('dashboard.calendar') }}" loading="lazy" class="block">
            @include('dashboard._calendar-skeleton')
        </turbo-frame>

    </div>
    <div id="productionDayModal" data-modal data-close="closeProductionDayModal" role="dialog" aria-modal="true" aria-labelledby="productionDayTitle" class="hidden fixed inset-0 z-50 items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" data-day-close></div>
        <div class="relative bg-white rounded-2xl p-5 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center gap-3 mb-4"><h2 id="productionDayTitle" class="text-lg font-semibold">Daily production</h2><button type="button" data-day-close aria-label="Close" class="px-3 py-2">Close</button></div>
            <div class="flex flex-wrap gap-3 mb-4">
                <button type="button" data-day-details class="px-4 py-2 rounded-lg bg-navy text-white">View overall details</button>
                @if(auth()->user()->isAdmin())
                <button type="button" data-day-delete class="px-4 py-2 rounded-lg border border-red-300 text-red-700">Delete all records on this day</button>
                @endif
            </div>
            <p data-day-message role="status" class="text-sm text-[#6B7280] mb-3">Choose an action. Both actions apply to all cages on this date.</p>
            <div data-day-content></div>
        </div>
    </div>
    </turbo-frame>

</div>
@endsection
