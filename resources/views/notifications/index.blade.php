@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
<div class="space-y-5">

    <x-page-header title="Notifications" subtitle="All system alerts and warnings" />

    <x-fab>
        <form method="POST" action="{{ route('alerts.read-all') }}" data-turbo="false" class="contents">
            @csrf
            <button type="submit"
                    class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
                <span>Mark all read</span>
                <div class="w-8 h-8 rounded-full bg-[#2D7D46]/10 flex items-center justify-center">
                    <i data-lucide="check-check" class="w-4 h-4 text-[#2D7D46]"></i>
                </div>
            </button>
        </form>
    </x-fab>

    {{-- Alerts list — lazy loaded via Turbo Frame --}}
    <turbo-frame id="notifications-table" src="{{ route('notifications.table') }}" loading="lazy">
        @include('notifications._table-skeleton')
    </turbo-frame>

</div>
@endsection
