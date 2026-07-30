@extends('layouts.app')
@section('title', 'Session Expired')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center">
    <div class="text-center max-w-md">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background-color: #fbe4e6;">
            <i data-lucide="clock" class="w-8 h-8" style="color: #9b1c24;"></i>
        </div>
        <h1 class="text-2xl font-semibold mb-2" style="color: #1f1f1f;">Session Expired</h1>
        <p class="text-sm mb-6" style="color: #6B7280;">
            Your session has timed out due to inactivity. Please log in again to continue.
        </p>
        <a href="{{ route('login') }}"
           class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-medium text-white transition-colors"
           style="background-color: #002D5E;"
           onmouseover="this.style.backgroundColor='#001F42'"
           onmouseout="this.style.backgroundColor='#002D5E'">
            <i data-lucide="log-in" class="w-4 h-4"></i>
            Log In Again
        </a>
        <a href="{{ url()->previous() }}"
           class="inline-flex items-center gap-2 px-6 py-2.5 ml-3 rounded-lg text-sm font-medium transition-colors"
           style="color: #1f1f1f; border: 1px solid #e6e6e6;"
           onmouseover="this.style.backgroundColor='#f6f5f4'"
           onmouseout="this.style.backgroundColor='transparent'">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Go Back
        </a>
    </div>
</div>
@endsection