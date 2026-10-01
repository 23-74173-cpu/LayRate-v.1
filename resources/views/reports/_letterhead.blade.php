{{-- Shared by the preview and the printable document — keep both identical.
     Props: $type, $from, $to --}}
<div class="flex items-start justify-between mb-1">
    <div class="flex items-center gap-3">
        <img src="/images/layrate-logo.png" alt="LayRate logo" width="663" height="885" class="h-10 w-auto object-contain shrink-0">
        <div>
            <div class="font-bold text-navy leading-tight">LayRate Poultry Farm</div>
            <div class="text-xs text-[#6B7280]">Farm Monitor System</div>
        </div>
    </div>
    <div class="text-right">
        <div class="text-sm font-bold text-navy uppercase tracking-widest">{{ $type === 'all' ? 'All Reports' : ucfirst($type) . ' Report' }}</div>
        <div class="text-xs text-[#6B7280] mt-0.5">{{ $from && $to ? \App\Services\ReportingDateService::displayDate($from) . ' — ' . \App\Services\ReportingDateService::displayDate($to) : 'All time' }}</div>
    </div>
</div>
<hr style="border:none;border-top:3px solid var(--color-navy);margin:12px 0">
