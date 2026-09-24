{{-- Shared by the preview and the printable document — keep both identical.
     Props: $cageId, $recordCount --}}
<div class="grid grid-cols-4 gap-4 mb-6 text-xs text-black">
    <div><span class="font-medium text-black">Cage:</span> {{ $cageId === 'all' ? 'All Cages' : $cageId }}</div>
    <div><span class="font-medium text-black">Generated:</span> {{ \App\Services\ReportingDateService::now()->format('m/d/Y g:i A') }}</div>
    <div><span class="font-medium text-black">Prepared by:</span> {{ auth()->user()->name }}</div>
    <div><span class="font-medium text-black">Records:</span> {{ $recordCount }}</div>
</div>
