@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
<div class="space-y-5">

    <x-page-header title="Notifications" subtitle="All system alerts and warnings">
        <x-slot:actions>
            <form method="POST" action="{{ route('alerts.read-all') }}">
                @csrf
                <button type="submit" class="flex items-center gap-2 bg-[#002D5E] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#001F42] transition-colors">
                    <i data-lucide="check-check" class="w-4 h-4"></i> Mark all read
                </button>
            </form>
        </x-slot:actions>
    </x-page-header>

    {{-- Alerts list — lazy loaded via Turbo Frame --}}
    <turbo-frame id="notifications-table" src="{{ route('notifications.table') }}" loading="lazy">
        @include('notifications._table-skeleton')
    </turbo-frame>

</div>
@endsection
