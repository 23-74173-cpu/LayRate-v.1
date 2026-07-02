<div class="space-y-5">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4"><x-skeleton variant="metric" /></div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4"><x-skeleton variant="metric" /></div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4"><x-skeleton variant="metric" /></div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        @for($i = 0; $i < 4; $i++)
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4"><x-skeleton variant="card" /></div>
        @endfor
    </div>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4"><x-skeleton variant="card" /><div class="mt-4 h-24 bg-gray-200 rounded animate-pulse"></div></div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4"><x-skeleton variant="card" /><div class="mt-4 h-24 bg-gray-200 rounded animate-pulse"></div></div>
    </div>
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-4"><x-skeleton variant="table" lines="3" /></div>
</div>
