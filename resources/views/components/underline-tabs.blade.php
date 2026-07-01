@props(['tabs' => [], 'active' => ''])

<div class="border-b border-[#D9D9D9]">
    <nav class="flex gap-6">
        @foreach($tabs as $key => $tab)
            @php
                $isActive = $key === $active;
                $classes = 'pb-2 text-sm font-medium border-b-2 -mb-px transition-colors ' .
                    ($isActive ? 'border-[#002D5E] text-[#002D5E]' : 'border-transparent text-[#6B7280] hover:text-[#333]');
            @endphp
            @if(isset($tab['route']))
                <a href="{{ route($tab['route']) }}" class="{{ $classes }}">
                    @if(isset($tab['icon']))<i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4 inline mr-1"></i>@endif
                    {{ $tab['label'] }}
                </a>
            @else
                <button onclick="{{ $tab['onclick'] ?? "switchTab('$key')" }}" class="{{ $classes }}">
                    @if(isset($tab['icon']))<i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4 inline mr-1"></i>@endif
                    {{ $tab['label'] }}
                </button>
            @endif
        @endforeach
    </nav>
</div>
