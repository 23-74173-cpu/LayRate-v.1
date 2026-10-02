{{-- Placeholder for the KPI row + table in egg-logging/_logs, sized to match. --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4 animate-pulse" aria-hidden="true">
    @for($i = 0; $i < 4; $i++)
    <div class="rounded-2xl border border-[#E6E6E6] bg-white p-4 min-h-[104px] flex items-start gap-4">
        <div class="w-14 h-14 rounded-2xl bg-gray-100 shrink-0"></div>
        <div class="flex-1 space-y-2 pt-1">
            <div class="h-3 w-16 rounded bg-gray-200"></div>
            <div class="h-6 w-12 rounded bg-gray-200"></div>
            <div class="h-3 w-24 rounded bg-gray-100"></div>
        </div>
    </div>
    @endfor
</div>
<div class="rounded-xl border overflow-hidden" style="background-color: #ffffff; border-color: #e6e6e6;">
    <div class="p-6">
        <x-skeleton variant="table" lines="6" />
    </div>
</div>
