<turbo-frame id="dashboard-cage-overview">
    <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xs font-semibold tracking-[0.05em] uppercase" style="color: #615d59;">Cage Overview</h3>
            <span class="text-xs" style="color: #a39e98;">{{ $cages->count() }} cage{{ $cages->count() !== 1 ? 's' : '' }} · {{ $cages->filter(fn($cg) => !is_null($cg->location_row))->count() }} placed</span>
        </div>

        {{-- Farm Layout Grid --}}
        <style>
            @media (max-width: 639px) { .cage-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; } }
        </style>
        <div class="grid gap-2 cage-grid" style="grid-template-columns: repeat({{ $gridCols }}, minmax(0, 1fr));">
            @for($r = 0; $r < $gridRows; $r++)
                @for($c = 0; $c < $gridCols; $c++)
                @php
                    $placedCage = $cages->firstWhere(fn($cg) => $cg->location_row === $r && $cg->location_column === $c);
                @endphp
                @if($placedCage)
                <div class="farm-tile min-h-[5rem] rounded-lg border-2 p-3 flex flex-col justify-between cursor-pointer transition-all hover:shadow-md relative"
                     style="border-color: {{ $placedCage->color }}; background-color: {{ $placedCage->colorSoft }};"
                     data-cage-code="{{ $placedCage->cage_code }}"
                     data-breed="{{ $placedCage->breed }}"
                     data-hens="{{ $placedCage->hen_count }}"
                     data-hdep="{{ number_format($placedCage->today_hdep, 1) }}"
                     data-eggs="{{ $placedCage->today_eggs }}"
                     data-sensor="{{ $placedCage->has_sensor ? 'Yes' : 'No' }}"
                     onclick="openStatsModal(this)">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold" style="color: {{ $placedCage->color }};">{{ $placedCage->cage_code }}</span>
                        <span class="text-xs px-1.5 py-0.5 rounded-full absolute -top-2 -right-2 z-10" style="background-color: {{ $placedCage->color }}; color: #ffffff;">{{ number_format($placedCage->today_hdep, 0) }}%</span>
                    </div>
                    <div class="text-xs truncate" style="color: #615d59;">{{ Str::limit($placedCage->breed, 16) }}</div>
                </div>
                @else
                <div class="min-h-[5rem] rounded-lg border p-3 flex items-center justify-center" style="border-color: #e6e6e6; background-color: #f9fafb;">
                    <span class="text-xs" style="color: #d1d5db;">{{ $r + 1 }}-{{ $c + 1 }}</span>
                </div>
                @endif
                @endfor
            @endfor
        </div>

        {{-- Unplaced Cages --}}
        @php $unplaced = $cages->filter(fn($cg) => is_null($cg->location_row)); @endphp
        @if($unplaced->count() > 0)
        <div class="mt-6 pt-4 border-t" style="border-color: #e6e6e6;">
            <h4 class="text-xs font-semibold tracking-[0.05em] uppercase mb-3" style="color: #615d59;">Unplaced Cages</h4>
            <div class="flex flex-wrap gap-3">
                @foreach($unplaced as $uc)
                <div class="farm-tile min-h-[3.5rem] rounded-lg border-2 px-4 py-2 flex flex-col justify-center cursor-pointer transition-all hover:shadow-md relative"
                     style="border-color: {{ $uc->color }}; background-color: {{ $uc->colorSoft }};"
                     data-cage-code="{{ $uc->cage_code }}"
                     data-breed="{{ $uc->breed }}"
                     data-hens="{{ $uc->hen_count }}"
                     data-hdep="{{ number_format($uc->today_hdep, 1) }}"
                     data-eggs="{{ $uc->today_eggs }}"
                     data-sensor="{{ $uc->has_sensor ? 'Yes' : 'No' }}"
                     onclick="openStatsModal(this)">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold" style="color: {{ $uc->color }};">{{ $uc->cage_code }}</span>
                        <span class="text-xs px-1.5 py-0.5 rounded-full whitespace-nowrap absolute -top-2 -right-2 z-10" style="background-color: {{ $uc->color }}; color: #ffffff;">{{ number_format($uc->today_hdep, 0) }}%</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</turbo-frame>
