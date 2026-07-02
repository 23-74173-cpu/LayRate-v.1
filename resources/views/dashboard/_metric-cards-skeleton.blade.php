<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    @for($i = 0; $i < 4; $i++)
    <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
        <x-skeleton variant="metric" />
    </div>
    @endfor
</div>
