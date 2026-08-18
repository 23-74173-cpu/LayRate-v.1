<div class="space-y-4">
    <div>
        <div class="h-4 w-20 rounded mb-2" style="background-color: #e6e6e6;"></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @for($i = 0; $i < 4; $i++)
            <div class="rounded-2xl border p-5" style="background-color: #ffffff; border-color: #e6e6e6;">
                <x-skeleton variant="metric" />
            </div>
            @endfor
        </div>
    </div>
    <div>
        <div class="h-4 w-32 rounded mb-2" style="background-color: #e6e6e6;"></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @for($i = 0; $i < 3; $i++)
            <div class="rounded-2xl border p-5" style="background-color: #ffffff; border-color: #e6e6e6;">
                <x-skeleton variant="metric" />
            </div>
            @endfor
        </div>
    </div>
</div>
