<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    @for($i = 0; $i < 2; $i++)
    <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
        <x-skeleton variant="card" />
    </div>
    @endfor
</div>
