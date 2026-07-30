{{-- Page Header — consistent boxed two-tier header across all sections --}}
@props(['title', 'subtitle' => null, 'subtitleId' => null, 'subtitleClass' => '', 'actionsId' => null])

<div class="bg-white rounded-lg border border-[#D9D9D9] p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
    <div>
        <h1 class="text-xl font-bold text-[#333333]">{{ $title }}</h1>
        @if($subtitle)
        <p @if($subtitleId) id="{{ $subtitleId }}" @endif class="text-sm text-[#6B7280] mt-1 {{ $subtitleClass }}">{{ $subtitle }}</p>
        @endif
    </div>
    {{--
        Always rendered, even with no actions/slot content, so pages that live
        behind a Turbo Frame (the Egg Management tabs) have a stable element to
        sync into on tab clicks. Those clicks only swap turbo-frame#egg-content —
        this header sits outside it and is otherwise never touched by them, which
        was the root cause of the "buttons disappear/reappear" bug: the header
        only repainted on a genuine full page load, never on a frame-scoped tab
        click. See eggs/_tabs.blade.php for the client-side sync.
    --}}
    <div class="shrink-0" @if($actionsId) id="{{ $actionsId }}" @endif>
        @if(isset($actions))
            {{ $actions }}
        @elseif(isset($slot) && trim($slot))
            {{ $slot }}
        @endif
    </div>
</div>
