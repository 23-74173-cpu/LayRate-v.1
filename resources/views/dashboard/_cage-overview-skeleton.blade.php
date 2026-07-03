<div>
    <div class="h-7 bg-gray-200 rounded w-48 mb-4 animate-pulse"></div>
    <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            @for($i = 0; $i < 8; $i++)
            <div class="min-h-[5rem] rounded-lg border p-3" style="border-color: #e6e6e6; background-color: #f9fafb;">
                <x-skeleton variant="text" lines="2" />
            </div>
            @endfor
        </div>
    </div>
</div>
