{{-- Page Header — consistent boxed two-tier header across all sections --}}
@props(['title', 'subtitle'])

<div class="bg-white rounded-lg border border-[#D9D9D9] p-5 flex items-center justify-between gap-4">
    <div>
        <h1 class="text-xl font-bold text-[#333333]">{{ $title }}</h1>
        @if($subtitle)
        <p class="text-sm text-[#6B7280] mt-1">{{ $subtitle }}</p>
        @endif
    </div>
    @if(isset($actions))
    <div class="shrink-0">
        {{ $actions }}
    </div>
    @endif
</div>
