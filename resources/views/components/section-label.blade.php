{{-- Section label — "PRODUCTION" style section starts: 12px uppercase
     muted with icon, thin divider extending right. Replaces the scattered
     h3 section labels (10px/12px, random tracking) in metric-card groups.

     Props:
       title (string, required) — e.g. "Production"
       icon (string|null) — Lucide name, rendered in navy
     Slots:
       meta (optional) — muted suffix, e.g. "· as of 10/01/2026"
       actions (optional) — right-aligned controls (e.g. "Show Full Ranking")
--}}
@props(['title', 'icon' => null])

<h3 class="section-label mb-2">
    @if($icon)<i data-lucide="{{ $icon }}"></i>@endif
    <span>{{ $title }}</span>
    @if(isset($meta) && trim($meta) !== '')
    <span class="section-label-meta">{{ $meta }}</span>
    @endif
    @if(isset($actions) && trim($actions) !== '')
    <span class="section-label-actions">{{ $actions }}</span>
    @endif
</h3>
