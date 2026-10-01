{{-- Page Header — the single navy banner used by every page.
     Flat navy token, compact height, ONE calm egg outline anchored at the
     right edge (~6% opacity, gradient-masked toward the left so the title
     area stays clean). No random circles, no per-page decor scripts.

     Props:
       title (string, required)
       subtitle (string|null) — rendered in .page-subtitle (13px white/78)
       subtitleId / subtitleClass — kept for the Egg Management tab sync
         (eggs/_tabs.blade.php swaps #egg-header-subtitle text on tab clicks)
       actionsId — kept so Turbo-frame pages keep a stable sync target
     Slots:
       actions (preferred) or default — the page's primary action when one
         already exists (e.g. Log Eggs, Export), styled with the shared
         primary button inverted for navy. Never invent actions here.
       updated (optional) — "last updated" text, rendered muted right-aligned
         when no actions are present.
--}}
@props(['title', 'subtitle' => null, 'subtitleId' => null, 'subtitleClass' => '', 'actionsId' => null])

<div class="page-header">
    {{-- Single calm egg outline, right edge, fades left (see app.css). --}}
    <span class="page-header-egg" aria-hidden="true"><i data-lucide="egg"></i></span>

    <div class="relative z-[1] min-w-0">
        <h1 class="page-title">{{ $title }}</h1>
        @if($subtitle)
        <p @if($subtitleId) id="{{ $subtitleId }}" @endif class="page-subtitle {{ $subtitleClass }}">{{ $subtitle }}</p>
        @endif
    </div>
    {{--
        Always rendered, even with no actions/slot content, so pages that live
        behind a Turbo Frame (the Egg Management tabs) have a stable element to
        sync into on tab clicks. :empty (app.css) hides it when there are no
        buttons. The right side holds either the page's primary action or the
        "last updated" text — never both, never invented.
    --}}
    <div class="page-header-actions"
         @if($actionsId) id="{{ $actionsId }}" @endif>@if(isset($actions)){{ $actions }}@elseif(isset($slot) && trim($slot) !== ''){{ $slot }}@elseif(isset($updated) && trim($updated) !== '')<span class="page-header-updated">{{ $updated }}</span>@endif</div>
</div>
