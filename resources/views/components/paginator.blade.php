{{--
    <x-paginator :paginator="$logs" />
    Notion-style minimal pagination: text links + chevrons, no heavy button styling.
    Replaces 4 copy-pasted paginator blocks across views.

    Props:
      - paginator (LengthAwarePaginator, required)
      - class (string, default '') — additional classes on the wrapper
--}}
@props(['paginator', 'class' => ''])

@if($paginator->hasPages())
<nav class="flex items-center justify-between px-4 py-3 border-t max-sm:flex-wrap max-sm:gap-2 {{ $class }}" style="border-color: #e6e6e6;" role="navigation" aria-label="Pagination">
    {{-- Info text --}}
    <span class="text-xs max-sm:text-sm max-sm:w-full max-sm:text-center" style="color: #615d59;">
        Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
    </span>

    {{-- Page links --}}
    <div class="flex items-center gap-1 max-sm:w-full max-sm:justify-center max-sm:flex-wrap">
        {{-- Previous --}}
        @if($paginator->onFirstPage())
        <span class="px-2 py-1 text-xs max-sm:min-w-11 max-sm:min-h-11 max-sm:inline-flex max-sm:items-center max-sm:justify-center" style="color: #a39e98;" aria-hidden="true">‹</span>
        @else
        <a href="{{ $paginator->previousPageUrl() }}" class="px-2 py-1 text-xs rounded transition-colors hover:bg-black/5 max-sm:min-w-11 max-sm:min-h-11 max-sm:inline-flex max-sm:items-center max-sm:justify-center max-sm:text-sm" style="color: var(--color-navy);" aria-label="Previous page">‹</a>
        @endif

        {{-- Page numbers — show current ± 1 --}}
        @foreach($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
            @if($page === $paginator->currentPage())
            <span class="paginator-current px-2 py-1 text-xs font-semibold rounded max-sm:min-w-11 max-sm:min-h-11 max-sm:inline-flex max-sm:items-center max-sm:justify-center max-sm:text-sm" style="color: #1f1f1f; background-color: #f6f5f4;">{{ $page }}</span>
            @elseif($page >= $paginator->currentPage() - 1 && $page <= $paginator->currentPage() + 1)
            <a href="{{ $url }}" class="paginator-adjacent {{ $page === 1 ? 'paginator-first' : '' }} {{ $page === $paginator->lastPage() ? 'paginator-last' : '' }} px-2 py-1 text-xs rounded transition-colors hover:bg-black/5 max-sm:min-w-11 max-sm:min-h-11 max-sm:inline-flex max-sm:items-center max-sm:justify-center max-sm:text-sm" style="color: var(--color-navy);">{{ $page }}</a>
            @elseif($page === 1 || $page === $paginator->lastPage())
            {{-- Always show first and last page --}}
            <a href="{{ $url }}" class="paginator-edge {{ $page === 1 ? 'paginator-first' : 'paginator-last' }} px-2 py-1 text-xs rounded transition-colors hover:bg-black/5 max-sm:min-w-11 max-sm:min-h-11 max-sm:inline-flex max-sm:items-center max-sm:justify-center max-sm:text-sm" style="color: var(--color-navy);">{{ $page }}</a>
            @elseif($page === $paginator->currentPage() - 2 || $page === $paginator->currentPage() + 2)
            {{-- Ellipsis --}}
            <span class="paginator-ellipsis px-1 text-xs" style="color: #a39e98;" aria-hidden="true">…</span>
            @endif
        @endforeach

        {{-- Next --}}
        @if($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" class="px-2 py-1 text-xs rounded transition-colors hover:bg-black/5 max-sm:min-w-11 max-sm:min-h-11 max-sm:inline-flex max-sm:items-center max-sm:justify-center max-sm:text-sm" style="color: var(--color-navy);" aria-label="Next page">›</a>
        @else
        <span class="px-2 py-1 text-xs max-sm:min-w-11 max-sm:min-h-11 max-sm:inline-flex max-sm:items-center max-sm:justify-center" style="color: #a39e98;" aria-hidden="true">›</span>
        @endif
    </div>
</nav>
@endif
