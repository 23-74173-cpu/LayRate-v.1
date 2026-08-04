<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
    @for($i = 0; $i < 5; $i++)
    <div class="rounded-2xl border p-5" style="background-color: #ffffff; border-color: #e6e6e6;">
        <x-skeleton variant="metric" />
    </div>
    @endfor
</div>
