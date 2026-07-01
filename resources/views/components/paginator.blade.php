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
<nav class="flex items-center justify-between px-4 py-3 border-t {{ $class }}" style="border-color: #e6e6e6;" role="navigation" aria-label="Pagination">
    {{-- Info text --}}
    <span class="text-xs" style="color: #615d59;">
        Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
    </span>

    {{-- Page links --}}
    <div class="flex items-center gap-1">
        {{-- Previous --}}
        @if($paginator->onFirstPage())
        <span class="px-2 py-1 text-xs" style="color: #a39e98;" aria-hidden="true">‹</span>
        @else
        <a href="{{ $paginator->previousPageUrl() }}" class="px-2 py-1 text-xs rounded transition-colors hover:bg-black/5" style="color: #0075de;" aria-label="Previous page">‹</a>
        @endif

        {{-- Page numbers — show current ± 1 --}}
        @foreach($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
            @if($page === $paginator->currentPage())
            <span class="px-2 py-1 text-xs font-semibold rounded" style="color: #1f1f1f; background-color: #f6f5f4;">{{ $page }}</span>
            @elseif($page >= $paginator->currentPage() - 1 && $page <= $paginator->currentPage() + 1)
            <a href="{{ $url }}" class="px-2 py-1 text-xs rounded transition-colors hover:bg-black/5" style="color: #0075de;">{{ $page }}</a>
            @elseif($page === 1 || $page === $paginator->lastPage())
            {{-- Always show first and last page --}}
            <a href="{{ $url }}" class="px-2 py-1 text-xs rounded transition-colors hover:bg-black/5" style="color: #0075de;">{{ $page }}</a>
            @elseif($page === $paginator->currentPage() - 2 || $page === $paginator->currentPage() + 2)
            {{-- Ellipsis --}}
            <span class="px-1 text-xs" style="color: #a39e98;" aria-hidden="true">…</span>
            @endif
        @endforeach

        {{-- Next --}}
        @if($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" class="px-2 py-1 text-xs rounded transition-colors hover:bg-black/5" style="color: #0075de;" aria-label="Next page">›</a>
        @else
        <span class="px-2 py-1 text-xs" style="color: #a39e98;" aria-hidden="true">›</span>
        @endif
    </div>
</nav>
@endif
