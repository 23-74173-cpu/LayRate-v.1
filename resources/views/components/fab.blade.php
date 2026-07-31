@props(['id' => null, 'menuId' => null])

{{--
    Shared Floating Action Button — same pattern as the Forecast module.
    The toggle/menu are toggled by a delegated handler in layouts/app.blade.php
    (document-level click listener bound once, so it survives Turbo visits that
    re-render this markup inside <main>). Pages put their actions in the slot;
    the Egg Management pages leave it empty and populate the menu at runtime via
    eggs/_tabs.blade.php (menu-id="egg-fab-menu").
--}}
<div class="fixed bottom-6 right-3 z-40 flex flex-col items-end gap-3 pointer-events-none fab"
     @if($id) id="{{ $id }}" @endif>
    <div @if($menuId) id="{{ $menuId }}" @endif
         class="fab-menu flex flex-col items-end gap-2 mb-1 mr-2 transition-all duration-200 opacity-0 invisible translate-y-4 pointer-events-auto">
        @if(isset($slot) && trim($slot)){{ $slot }}@endif
    </div>
    <button type="button" class="fab-toggle w-16 h-12 rounded-full bg-surface text-ink border border-hairline shadow-soft hover:bg-canvas-soft transition-colors flex items-center justify-center flex-shrink-0 pointer-events-auto"
        aria-label="Open menu" aria-expanded="false">
        <i data-lucide="plus" class="fab-icon w-6 h-6 transition-transform duration-200 ease-out"></i>
    </button>
</div>
