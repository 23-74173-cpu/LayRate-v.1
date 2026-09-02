<div id="unplacedCard" class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
    <div class="flex items-center justify-between px-4 py-2.5"
         style="background: #F0F4FF; border-bottom: 1px solid #CCDDFF;">
        <span class="flex items-center gap-3">
            <span class="text-sm font-semibold text-[#1D4E8F]">Unplaced</span>
            <span class="text-xs px-1.5 py-0.5 rounded-full bg-white/80 text-[#6B7280]">
                {{ $unplacedCount }} hen(s)
            </span>
        </span>
    </div>

    @if($unplacedHens->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead>
                <tr class="bg-[#FAFAFA] text-left font-semibold uppercase tracking-wider text-[#6B7280]">
                    <th class="px-4 py-2">ID</th>
                    <th class="px-4 py-2">Breed</th>
                    <th class="px-4 py-2">Age</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="unplacedBody">
                @foreach($unplacedHens as $hen)
                <tr class="border-t border-[#F0F0F0] hover:bg-[#FAFAFA] unplaced-row"
                    data-unplaced-index="{{ $loop->index }}">
                    <td class="px-4 py-2 font-mono text-[#6B7280]">{{ $hen->chicken_id ?? '—' }}</td>
                    <td class="px-4 py-2 text-[#333]">{{ $hen->breed }}</td>
                    <td class="px-4 py-2 text-[#6B7280]">{{ $hen->current_age_weeks }}w</td>
                    <td class="px-4 py-2 text-right">
                        <x-icon-button icon="crosshair" label="Cull hen" color="orange"
                            onclick="openCullModal('{{ $hen->id }}', '{{ $hen->chicken_id }} (unplaced)')" />
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($unplacedCount > 4)
    <div class="flex items-center justify-between px-4 py-2 border-t border-[#F0F0F0] bg-[#FAFAFA] text-xs text-[#6B7280]" id="unplacedPagination">
        <span id="unplacedRange"></span>
        <div class="flex items-center gap-2">
            <button type="button" id="unplacedPrev" onclick="unplacedPageMove(-1)"
                    class="px-2 py-1 rounded border border-[#D9D9D9] hover:bg-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
            <button type="button" id="unplacedNext" onclick="unplacedPageMove(1)"
                    class="px-2 py-1 rounded border border-[#D9D9D9] hover:bg-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
        </div>
    </div>
    @endif

    <script>
    (function() {
        var PAGE_SIZE = 4;
        var page = 0;
        var rows = document.querySelectorAll('.unplaced-row');
        var total = rows.length;
        var totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

        function render() {
            if (page >= totalPages) page = totalPages - 1;
            if (page < 0) page = 0;
            rows.forEach(function(row, i) {
                var p = Math.floor(i / PAGE_SIZE);
                row.classList.toggle('hidden', p !== page);
            });
            var range = document.getElementById('unplacedRange');
            var prev = document.getElementById('unplacedPrev');
            var next = document.getElementById('unplacedNext');
            if (range) {
                var start = page * PAGE_SIZE + 1;
                var end = Math.min((page + 1) * PAGE_SIZE, total);
                range.textContent = 'Showing ' + start + '\u2013' + end + ' of ' + total;
            }
            if (prev) prev.disabled = page <= 0;
            if (next) next.disabled = page >= totalPages - 1;
        }

        window.unplacedPageMove = function(dir) { page += dir; render(); };
        render();
    })();
    </script>
    @else
    <div class="px-4 py-3 text-xs text-[#9CA3AF] text-center">No unplaced hens.</div>
    @endif
</div>
