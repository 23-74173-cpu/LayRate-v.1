@php
    $totalActiveCages = $dataCompleteness['eggs']['total'] ?? 0;
    $items = [
        [
            'key'   => 'eggs',
            'label' => 'Eggs',
            'icon'  => 'egg',
            'route' => route('eggs.logging'),
            'color' => '#0075de',
            'bg'    => '#e8f4fd',
        ],
        [
            'key'   => 'environment',
            'label' => 'Environment',
            'icon'  => 'thermometer',
            'route' => route('environment'),
            'color' => '#e09c00',
            'bg'    => '#fef3e2',
        ],
        [
            'key'   => 'feed',
            'label' => 'Feed',
            'icon'  => 'wheat',
            'route' => route('feed'),
            'color' => '#16a34a',
            'bg'    => '#e6f6ee',
        ],
        [
            'key'   => 'mortality',
            'label' => 'Mortality',
            'icon'  => 'heart-crack',
            'route' => route('chickens.index', ['tab' => 'mortality']),
            'color' => '#dc2626',
            'bg'    => '#fde8e8',
        ],
    ];

    $anyIncomplete = collect($items)->contains(function ($item) use ($dataCompleteness) {
        $d = $dataCompleteness[$item['key']] ?? null;
        return $d && !$d['complete'];
    });

    $incompleteCount = collect($items)->filter(function ($item) use ($dataCompleteness) {
        $d = $dataCompleteness[$item['key']] ?? null;
        return $d && !$d['complete'];
    })->count();
@endphp

@if($totalActiveCages > 0)
<div id="dataChecklistFab" style="position: fixed; bottom: 20px; right: 20px; z-index: 40; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    {{-- Panel (notepad style, positioned above the FAB) --}}
    <div id="dataChecklistPanel" class="hidden" style="position: absolute; bottom: 70px; right: 0; width: 260px; background: #fffef5; border: 2px solid #2c2c2c; border-radius: 4px; box-shadow: 6px 6px 0 #b0a990; overflow: hidden;">
        {{-- Spiral binding holes --}}
        <div style="position: relative; padding-left: 28px;">
            <div style="position: absolute; left: 0; top: 0; bottom: 0; width: 24px; background: #f0e6d3; border-right: 1px dashed #c4b89a;">
                @for($i = 0; $i < 12; $i++)
                <div style="width: 10px; height: 10px; border-radius: 50%; background: #fffef5; border: 1px solid #c4b89a; margin: 8px auto 0;"></div>
                @endfor
            </div>

            {{-- Header --}}
            <div style="padding: 10px 14px 6px; border-bottom: 1px solid #e8e0cc;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-size: 13px; font-weight: 700; color: #2c2c2c; font-family: 'Georgia', serif;">Today's Checklist</span>
                    <button onclick="document.getElementById('dataChecklistPanel').classList.add('hidden')" style="width: 20px; height: 20px; border-radius: 50%; border: none; background: #e8e0cc; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #6b5e4a; line-height: 1;">x</button>
                </div>
                <div style="font-size: 11px; color: #a39e98; margin-top: 2px; font-family: 'Georgia', serif;">{{ now()->format('M d, Y') }}</div>
            </div>

            {{-- Items --}}
            <div style="padding: 8px 14px 12px;">
                @foreach($items as $item)
                    @php
                        $d = $dataCompleteness[$item['key']] ?? ['logged' => 0, 'total' => 0, 'complete' => true];
                        $isComplete = $d['complete'];
                    @endphp
                    <a href="{{ $item['route'] }}" style="display: flex; align-items: center; gap: 10px; padding: 7px 0; text-decoration: none; border-bottom: 1px dotted #e8e0cc; color: inherit;">
                        {{-- Checkbox --}}
                        <div style="width: 20px; height: 20px; border: 2px solid {{ $isComplete ? '#16a34a' : '#c4b89a' }}; border-radius: 3px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: {{ $isComplete ? '#f0fdf4' : '#fffef5' }};">
                            @if($isComplete)
                                <i data-lucide="check" style="width: 14px; height: 14px; color: #16a34a; stroke-width: 3;"></i>
                            @endif
                        </div>
                        {{-- Label --}}
                        <div style="flex: 1; min-width: 0;">
                            <span style="font-size: 13px; font-family: 'Georgia', serif; color: {{ $isComplete ? '#8a8279' : '#2c2c2c' }}; {{ $isComplete ? 'text-decoration: line-through;' : '' }}">{{ $item['label'] }}</span>
                        </div>
                        {{-- Status icon --}}
                        @if(!$isComplete && $item['key'] !== 'mortality' && $d['total'] > 0)
                            <span style="font-size: 11px; color: #a39e98; font-family: 'Georgia', serif;">{{ $d['logged'] }}/{{ $d['total'] }}</span>
                        @elseif($isComplete)
                            <i data-lucide="check-circle" style="width: 16px; height: 16px; color: #16a34a;"></i>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- FAB --}}
    <button onclick="var p = document.getElementById('dataChecklistPanel'); p.classList.toggle('hidden'); if(!p.classList.contains('hidden') && typeof lucide !== 'undefined') lucide.createIcons();"
            style="width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15); cursor: pointer; border: none; position: relative; background-color: {{ $anyIncomplete ? '#b45309' : '#16a34a' }}; color: #ffffff; transition: transform 0.15s;"
            onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'" onmousedown="this.style.transform='scale(0.95)'"
            aria-label="Toggle data checklist">
        <i data-lucide="{{ $anyIncomplete ? 'clipboard-check' : 'check-circle' }}" style="width: 24px; height: 24px;"></i>
        @if($anyIncomplete)
        <span style="position: absolute; top: -4px; right: -4px; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; background-color: #dc2626; color: #ffffff;">
            {{ $incompleteCount }}
        </span>
        @endif
    </button>
</div>
@endif
