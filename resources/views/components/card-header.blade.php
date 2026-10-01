{{-- Card header — the single header treatment for every card.
     One 36px brand-blue icon tile for all cards (colors are reserved for
     status meaning only), then Title Case title + optional one-line
     subtitle, actions slot right (toggles, buttons, Interpretation chip as
     a small ghost .interp-btn). Same min-height with or without subtitle
     so neighbor headers always align.

     Props:
       title (string, required) — TITLE CASE, never uppercase, never gray
       subtitle (string|null) — what is shown + period from active filters
         where that data exists (e.g. "Eggs per day · last 30 days").
         Omit when the card has no natural subtitle; alignment is kept.
       icon (string|null, default 'layout-grid') — Lucide name. Null omits
         the tile (form cards) without breaking alignment.
     Slots:
       actions — right-aligned controls.
--}}
@props(['title', 'subtitle' => null, 'icon' => 'layout-grid'])

<div class="card-header">
    @if($icon)
    <span class="card-icon-tile" aria-hidden="true"><i data-lucide="{{ $icon }}"></i></span>
    @endif
    <div class="min-w-0 flex-1">
        <div class="card-title truncate">{{ $title }}</div>
        @if($subtitle)
        <span class="card-subtitle">{{ $subtitle }}</span>
        @endif
    </div>
    @if(isset($actions) && trim($actions) !== '')
    <div class="ml-auto flex shrink-0 items-center gap-2">{{ $actions }}</div>
    @endif
</div>
