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

    {{-- Alerts list --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        @if($alerts->isEmpty())
        <div class="p-8 text-center text-sm text-[#6B7280]">
            <i data-lucide="bell-off" class="w-8 h-8 mx-auto mb-2 text-[#D9D9D9]"></i>
            No notifications yet.
        </div>
        @else
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Status</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Cage</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Type</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Message</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Triggered</th>
                    <th class="text-right text-xs text-[#6B7280] px-5 py-3 font-medium">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($alerts as $alert)
                <tr class="border-b border-[#D9D9D9] {{ $alert->is_read ? 'bg-[#FAFAFA]' : '' }}">
                    <td class="px-5 py-3.5">
                        @if($alert->is_read)
                        <span class="inline-flex items-center gap-1 text-xs text-[#6B7280]">
                            <i data-lucide="check" class="w-3 h-3"></i> Read
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-[#0075de]">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#0075de]"></span> Unread
                        </span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-sm text-[#333333]">
                        @if($alert->cage)
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full" style="background-color: {{ $alert->cage->color }}"></span>
                            {{ $alert->cage->cage_code }}
                        </span>
                        @else
                        <span class="text-[#9CA3AF]">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $alert->alert_type }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#1f1f1f]">{{ $alert->message }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7280]">{{ $alert->triggered_at->format('Y-m-d g:i A') }}</td>
                    <td class="px-5 py-3.5 text-right">
                        @if(! $alert->is_read)
                        <form method="POST" action="{{ route('alerts.read', $alert) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-xs text-[#0075de] hover:underline font-medium">Mark read</button>
                        </form>
                        @else
                        <span class="text-xs text-[#9CA3AF]">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if($alerts->hasPages())
        <div class="px-5 py-3 border-t border-[#D9D9D9]">
            {{ $alerts->links() }}
        </div>
        @endif
        @endif
    </div>

</div>
@endsection
