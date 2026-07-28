{{-- Shared by the preview and the printable document — keep both identical.
     Props: $cageId, $recordCount --}}
<div class="grid grid-cols-4 gap-4 mb-6 text-xs text-[#6B7280]">
    <div><span class="font-medium text-[#333333]">Cage:</span> {{ $cageId === 'all' ? 'All Cages' : $cageId }}</div>
    <div><span class="font-medium text-[#333333]">Generated:</span> {{ now()->format('F j, Y  H:i') }}</div>
    <div><span class="font-medium text-[#333333]">Prepared by:</span> {{ auth()->user()->name }}</div>
    <div><span class="font-medium text-[#333333]">Records:</span> {{ $recordCount }}</div>
</div>
