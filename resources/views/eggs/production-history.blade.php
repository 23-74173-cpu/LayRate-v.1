@extends('layouts.app')
@section('title', 'Egg Management')

@section('content')
<div class="space-y-5">

    <x-page-header title="Egg Management" subtitle="Daily egg logs calendar" subtitle-id="egg-header-subtitle" />

    @include('eggs._tabs', ['activeTab' => 'production-history'])

    <turbo-frame id="egg-content">
    <div class="space-y-5">

        <turbo-frame id="dashboard-calendar" src="{{ route('dashboard.calendar') }}" loading="lazy" class="block">
            @include('dashboard._calendar-skeleton')
        </turbo-frame>

    </div>
    </turbo-frame>

</div>
@endsection
