<turbo-frame id="feed-live-data">
    {{-- ── Metric Cards ── --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs tracking-wider text-[#6B7280] mb-2">AVG CP% THIS WEEK</div>
            <div class="text-3xl tracking-tight text-[#333333]">{{ number_format($avgCp, 1) }}%</div>
            <div class="text-xs text-[#6B7280] mt-1">within target</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs tracking-wider text-[#6B7280] mb-2">AVG FEED/CAGE/DAY</div>
            <div class="text-3xl tracking-tight text-[#333333]">{{ $avgFeedPerCage }} <span class="text-xl">kg</span></div>
            <div class="text-xs text-[#6B7280] mt-1">rolling 7 days</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs tracking-wider text-[#6B7280] mb-2">TOTAL FEED USED</div>
            <div class="text-3xl tracking-tight text-[#333333]">{{ number_format($totalFeedWeek, 1) }} <span class="text-xl">kg</span></div>
            <div class="text-xs text-[#6B7280] mt-1">last 7 days</div>
        </div>
    </div>

    {{-- ── Tabs ── --}}
    <div id="feed-tabs-nav" class="mb-5">
        <x-underline-tabs :tabs="[
            'batches'     => ['label' => 'Feed Batches',     'icon' => 'folder',   'onclick' => 'feedSwitchTab(\'batches\')'],
            'consumption' => ['label' => 'Daily Consumption','icon' => 'utensils','onclick' => 'feedSwitchTab(\'consumption\')'],
        ]" active="{{ request()->get('tab', 'batches') }}" />
    </div>

    <script>
        function feedSwitchTab(tab) {
            document.querySelectorAll('.tab-panel').forEach(el => el.classList.add('hidden'));
            document.getElementById('tab-'+tab).classList.remove('hidden');

            const nav = document.querySelector('#feed-tabs-nav');
            if (nav) {
                nav.querySelectorAll('button').forEach(btn => {
                    btn.classList.remove('border-[#002D5E]', 'text-[#002D5E]');
                    btn.classList.add('border-transparent', 'text-[#6B7280]');
                });
                const active = nav.querySelector('button[onclick*="'+tab+'"]');
                if (active) {
                    active.classList.remove('border-transparent', 'text-[#6B7280]');
                    active.classList.add('border-[#002D5E]', 'text-[#002D5E]');
                }
            }
        }
    </script>

    {{-- Feed Batches Panel --}}
    <div id="tab-batches" class="tab-panel">
        {{-- CP% Legend --}}
        <div class="flex items-center gap-4 text-xs mb-3" style="color: #615d59;">
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full" style="background:#1f6b3a"></span> Optimal (16–18%)
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full" style="background:#8a5a00"></span> Watch (&lt;16% or &gt;18%)
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full" style="background:#9b1c24"></span> Critical
            </span>
        </div>
        <div class="rounded-xl border overflow-hidden" style="background-color: #ffffff; border-color: #e6e6e6;">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Batch Code</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Date Received</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Crude Protein %</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Notes</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                    <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
                        <td class="px-5 py-3.5 text-sm font-medium text-[#333333]">{{ $batch->batch_code }}</td>
                        <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $batch->date_received->format('Y-m-d') }}</td>
                        <td class="px-5 py-3.5">
                            <span class="text-xs px-2.5 py-1 rounded-full" style="background:{{ $batch->cpColor }};color:{{ $batch->cpText }}">
                                {{ number_format($batch->crude_protein, 1) }}%
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-[#6B7280] max-w-[200px] truncate">{{ $batch->notes ?? '—' }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-1.5">
                                <button onclick="openEditBatch({{ $batch->id }}, {{ $batch->crude_protein }}, '{{ addslashes($batch->notes ?? '') }}')"
                                        class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #a39e98;" aria-label="Edit batch">
                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                </button>
                                <button onclick="deleteBatch({{ $batch->id }})"
                                        class="flex items-center gap-1 text-xs border border-[#D9D9D9] px-2.5 py-1.5 rounded hover:bg-red-50 text-[#6B7280]"
                                        aria-label="Delete batch">
                                    <i data-lucide="trash-2" class="w-3 h-3" style="color: #9b1c24;"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-[#6B7280]">No feed batches yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Daily Consumption Panel --}}
    <div id="tab-consumption" class="tab-panel hidden">
        <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Date</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Cage</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Batch</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Consumed (kg)</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($consumptionLogs as $log)
                    @php
                        $cColor = match($log->cage->cage_code) { 'CAGE-A'=>'#2D7D46','CAGE-B'=>'#1D4E8F','CAGE-C'=>'#C2703E','CAGE-D'=>'#6B4C8A',default=>'#6B7280' };
                    @endphp
                    <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
                        <td class="px-5 py-3 text-sm font-mono text-[#333333]">{{ $log->log_date->format('Y-m-d') }}</td>
                        <td class="px-5 py-3 text-sm font-medium" style="color:{{ $cColor }}">{{ $log->cage->cage_code }}</td>
                        <td class="px-5 py-3 text-sm text-[#333333]">{{ $log->feedBatch->batch_code }}</td>
                        <td class="px-5 py-3 text-sm text-[#333333]">{{ number_format($log->feed_consumed_kg, 2) }} kg</td>
                        <td class="px-5 py-3">
                            <form method="POST" action="{{ route('feed.consumption.destroy', $log) }}"
                                  data-confirm="Delete this consumption record?" data-confirm-action="Delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 rounded-full hover:bg-red-50 transition-colors" style="color: #a39e98;" aria-label="Delete consumption log">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-[#6B7280]">No consumption data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($consumptionLogs->hasPages())
            <div class="px-5 py-3 border-t border-[#D9D9D9] flex items-center justify-between text-xs text-[#6B7280]">
                <span>Showing {{ $consumptionLogs->firstItem() }}-{{ $consumptionLogs->lastItem() }} of {{ $consumptionLogs->total() }}</span>
                <div class="flex items-center gap-1">
                    @if($consumptionLogs->onFirstPage())
                    <span class="px-2 py-1 text-[#9CA3AF]">‹ Prev</span>
                    @else
                    <a href="{{ $consumptionLogs->previousPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">‹ Prev</a>
                    @endif
                    @foreach($consumptionLogs->getUrlRange(1, $consumptionLogs->lastPage()) as $page => $url)
                        @if($page == $consumptionLogs->currentPage())
                        <span class="px-2 py-1 font-medium text-[#002D5E]">{{ $page }}</span>
                        @elseif($page >= $consumptionLogs->currentPage() - 1 && $page <= $consumptionLogs->currentPage() + 1)
                        <a href="{{ $url }}" class="px-2 py-1 hover:text-[#002D5E]">{{ $page }}</a>
                        @endif
                    @endforeach
                    @if($consumptionLogs->hasMorePages())
                    <a href="{{ $consumptionLogs->nextPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">Next ›</a>
                    @else
                    <span class="px-2 py-1 text-[#9CA3AF]">Next ›</span>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</turbo-frame>
