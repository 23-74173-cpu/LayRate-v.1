{{--
/**
 * <x-quick-actions-dock />
 *
 * ONE always-visible floating dock for every authenticated page (rendered
 * once in layouts/app.blade.php, data-turbo-permanent). Bottom-right corner,
 * horizontal row: checklist button on the left, "+" page-actions button on
 * the right by the corner. Idle transparency keeps it unobtrusive.
 *
 * Page actions reach the "+" menu via @stack('dock-actions') — pages push
 * the same markup they used to put in <x-fab> (same links, handlers,
 * permissions). The shared delegated .fab-toggle handler in layouts/app
 * drives the menu, so no per-page toggle code is needed.
 *
 * Checklist data comes from the layouts.app composer ($dockCompleteness).
 * Because the dock is permanent across Turbo visits, quick-dock.js patches
 * badge counts + panel items + fab menu from the incoming page
 * (turbo:before-render).
 */
--}}

@props(['dataCompleteness' => []])

@php
    $items = [
        ['key' => 'eggs', 'label' => 'Eggs', 'icon' => 'egg', 'route' => route('eggs.logging')],
        ['key' => 'environment', 'label' => 'Environment', 'icon' => 'thermometer', 'route' => route('environment')],
        ['key' => 'feed', 'label' => 'Feed', 'icon' => 'wheat', 'route' => route('feed')],
    ];
    $totalActiveCages = $dataCompleteness['eggs']['total'] ?? 0;
    $doneCount = 0;
    $loggedSum = 0;
    $totalSum = 0;
    foreach ($items as $item) {
        $d = $dataCompleteness[$item['key']] ?? ['logged' => 0, 'total' => 0, 'complete' => true];
        if ($d['complete']) $doneCount++;
        $loggedSum += $d['logged'] ?? 0;
        $totalSum += $d['total'] ?? 0;
    }
    $anyIncomplete = $doneCount < count($items);
    $incompleteCount = count($items) - $doneCount;
    $progressPct = $totalSum > 0 ? (int) round($loggedSum / $totalSum * 100) : ($anyIncomplete ? 0 : 100);
@endphp

@if($totalActiveCages > 0)
<div id="quickDock" data-quick-dock data-turbo-permanent
     class="fixed z-40 flex items-center gap-2.5"
     style="right: 16px; bottom: 16px; padding-bottom: env(safe-area-inset-bottom); padding-right: env(safe-area-inset-right); pointer-events: none;">
    {{-- Checklist panel: popover above the dock; bottom sheet on mobile --}}
    <div id="dataChecklistPanel" class="hidden absolute bottom-full right-0 mb-3 w-[350px] max-w-[calc(100vw-3rem)] max-h-[70vh] overflow-y-auto rounded-2xl border border-hairline bg-surface text-ink" style="box-shadow: 0 8px 32px rgba(10, 22, 46, 0.18); pointer-events: auto;" role="dialog" aria-labelledby="dockChecklistTitle">
        <div class="flex items-center gap-3 px-4 pt-4 pb-3 border-b border-hairline">
            <span class="w-9 h-9 rounded-xl bg-info-bg text-navy flex items-center justify-center shrink-0" aria-hidden="true">
                <i data-lucide="clipboard-check" class="w-5 h-5"></i>
            </span>
            <div class="flex-1 min-w-0">
                <h3 id="dockChecklistTitle" class="text-base font-semibold text-ink leading-tight">Today's Checklist</h3>
                <p class="text-[13px] text-ink-muted leading-tight mt-0.5">{{ now()->format('m/d/Y') }} · {{ $loggedSum }} of {{ $totalSum }} done</p>
            </div>
            <button type="button" data-dock-close-panel class="p-1.5 rounded-full hover:bg-black/5 transition-colors shrink-0" aria-label="Close checklist">
                <i data-lucide="x" class="w-5 h-5 text-ink-muted"></i>
            </button>
        </div>
        <div class="px-4 pt-3">
            <div class="h-1.5 rounded-full bg-hairline overflow-hidden" role="progressbar" aria-valuenow="{{ $progressPct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Checklist progress">
                <div class="h-full rounded-full bg-primary transition-all" style="width: {{ $progressPct }}%;"></div>
            </div>
        </div>
        <div class="px-2 py-2" data-dock-panel-items>
            @foreach($items as $item)
                @php
                    $d = $dataCompleteness[$item['key']] ?? ['logged' => 0, 'total' => 0, 'complete' => true];
                    $isComplete = $d['complete'];
                    $isPartial = !$isComplete && ($d['logged'] ?? 0) > 0;
                @endphp
                <a href="{{ $item['route'] }}" class="flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-canvas-soft focus-visible:outline-2 transition-colors">
                    @if($isComplete)
                        <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0 text-success" aria-hidden="true"></i>
                    @elseif($isPartial)
                        <i data-lucide="circle-dot" class="w-5 h-5 shrink-0 text-warning" aria-hidden="true"></i>
                    @else
                        <span class="w-5 h-5 shrink-0 rounded-full border-2 border-hairline bg-surface" aria-hidden="true"></span>
                    @endif
                    <span class="flex-1 min-w-0 text-[15px] font-medium {{ $isComplete ? 'text-ink-muted line-through' : 'text-ink' }}">{{ $item['label'] }}@if($isComplete)<span class="sr-only"> (done)</span>@endif</span>
                    <span class="text-[13px] text-ink-muted tabular-nums shrink-0">{{ $d['logged'] ?? 0 }}/{{ $d['total'] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
        @if(!$anyIncomplete)
        <p class="px-4 pb-4 text-[13px] text-success">All caught up — nice work.</p>
        @endif
    </div>

    {{-- Checklist button (left) --}}
    <button type="button" data-dock-action="checklist" data-dock-fade
            @if($anyIncomplete) data-dock-pending="true" @endif
            style="width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15); cursor: pointer; border: 1px solid rgba(255,255,255,0.35); position: relative; background-color: var(--color-navy); color: #ffffff; pointer-events: auto;"
            aria-label="Open checklist" aria-expanded="false" aria-controls="dataChecklistPanel">
        <i data-lucide="{{ $anyIncomplete ? 'clipboard-check' : 'check-circle' }}" style="width: 24px; height: 24px;" aria-hidden="true"></i>
        @if($anyIncomplete)
        <span data-dock-badge aria-label="{{ $incompleteCount }} pending tasks" style="position: absolute; top: -4px; right: -4px; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; background-color: var(--color-danger); color: #ffffff;">
            {{ $incompleteCount }}
        </span>
        @endif
    </button>

    {{-- "+" page actions (same .fab contract as the old x-fab) --}}
    <div class="fab relative" style="pointer-events: none;">
        <div id="dockFabMenu" class="fab-menu hidden absolute bottom-full right-0 mb-2 w-max max-w-[calc(100vw-2rem)] flex flex-col items-end gap-2" role="menu" aria-label="Quick actions">
            @stack('dock-actions')
        </div>
        <button type="button" data-dock-fade class="fab-toggle rounded-full text-white border shadow-soft hover:brightness-90 transition-colors flex items-center justify-center flex-shrink-0" style="width: 48px; height: 48px; background-color: var(--color-navy); border-color: rgba(255,255,255,0.35); pointer-events: auto;"
            aria-label="Quick actions" aria-expanded="false" aria-controls="dockFabMenu">
            <i data-lucide="plus" class="fab-icon w-6 h-6 transition-transform duration-200 ease-out" aria-hidden="true"></i>
        </button>
    </div>
</div>
@endif
