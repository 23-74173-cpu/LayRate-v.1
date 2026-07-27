@if($type === 'all')
<div class="space-y-5">
    <div class="bg-white rounded-lg border border-[#D9D9D9] px-5 py-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-[#333333]">All Reports</h2>
            <p class="text-xs text-[#6B7280] mt-0.5">
                {{ $from && $to ? "{$from} — {$to}" : 'All time' }} &middot; {{ $cageId === 'all' ? 'All Cages' : $cageId }} &middot; {{ collect($sections)->sum(fn($s) => $s['rows']->total()) }} record(s) across 5 report types
            </p>
        </div>
        <x-button :href="route('reports', array_merge(request()->query(), ['full' => 1]))">
            <i data-lucide="file-text" class="w-4 h-4"></i> View Printable Report
        </x-button>
    </div>

    @foreach($sections as $section)
    <div class="bg-white rounded-lg border border-[#D9D9D9]">
        <div class="px-5 py-4 border-b border-[#D9D9D9]">
            <h3 class="text-sm font-semibold text-[#333333]">{{ $section['label'] }}</h3>
            <p class="text-xs text-[#6B7280] mt-0.5">{{ $section['rows']->total() }} record(s)</p>
        </div>
        <div class="p-5">
            @include('reports._summary-pills', ['type' => $section['type'], 'summary' => $section['summary']])

            @if($charts)
            <div class="mb-6">
                <div id="chart-{{ $section['type'] }}-wrap" class="relative w-full h-[220px]">
                    <canvas id="chart-{{ $section['type'] }}" class="report-chart-canvas"></canvas>
                    <img id="chart-{{ $section['type'] }}-img" class="report-chart-img">
                </div>
                <div id="chart-{{ $section['type'] }}-empty" class="h-[80px] hidden items-center justify-center text-sm text-[#6B7280]">No chart data for this selection.</div>
            </div>
            @endif

            @if($section['rows']->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-[#e6e6e6] bg-[#f9f9f7]">
                            @foreach(array_keys((array) $section['rows']->first()) as $col)
                            <th class="px-5 py-3 text-left text-xs tracking-wider text-[#6B7280] uppercase font-medium whitespace-nowrap">
                                {{ strtoupper(str_replace('_', ' ', $col)) }}
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['rows'] as $row)
                        <tr class="border-b border-[#F0F0F0]">
                            @foreach((array) $row as $key => $val)
                            <td class="px-5 py-3 text-sm text-[#333333] {{ in_array($key, ['date','datetime']) ? 'font-mono' : '' }}">
                                {{ $val }}
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center text-sm text-[#6B7280] py-6">No data found for the selected filters.</div>
            @endif
        </div>
        <x-paginator :paginator="$section['rows']" />
    </div>
    @endforeach
</div>
@elseif($rows->isNotEmpty())
<div class="bg-white rounded-lg border border-[#D9D9D9]">
    <div class="px-5 py-4 border-b border-[#D9D9D9] flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-[#333333]">Report Preview</h2>
            <p id="report-preview-meta" class="text-xs text-[#6B7280] mt-0.5">
                {{ ucfirst(str_replace('_', ' ', $type)) }} &middot; {{ $from && $to ? "{$from} — {$to}" : 'All time' }} &middot; {{ $cageId === 'all' ? 'All Cages' : $cageId }} &middot; {{ $rows->total() }} record(s)
            </p>
        </div>
        <x-button :href="route('reports', array_merge(request()->query(), ['full' => 1]))">
            <i data-lucide="file-text" class="w-4 h-4"></i> View Printable Report
        </x-button>
    </div>
    <div class="p-5">
        @include('reports._summary-pills')

        @if($charts ?? false)
        <div class="mb-6">
            <div id="chart-{{ $type }}-wrap" class="relative w-full h-[260px]">
                <canvas id="chart-{{ $type }}" class="report-chart-canvas"></canvas>
                <img id="chart-{{ $type }}-img" class="report-chart-img">
            </div>
            <div id="chart-{{ $type }}-empty" class="h-[100px] hidden items-center justify-center text-sm text-[#6B7280]">No chart data for this selection.</div>
        </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-[#e6e6e6] bg-[#f9f9f7]">
                        @foreach(array_keys((array) $rows->first()) as $col)
                        <th class="px-5 py-3 text-left text-xs tracking-wider text-[#6B7280] uppercase font-medium whitespace-nowrap">
                            {{ strtoupper(str_replace('_', ' ', $col)) }}
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    <tr class="border-b border-[#F0F0F0]">
                        @foreach((array) $row as $key => $val)
                        <td class="px-5 py-3 text-sm text-[#333333] {{ in_array($key, ['date','datetime']) ? 'font-mono' : '' }}">
                            {{ $val }}
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <x-paginator :paginator="$rows" />
</div>
@else
<div class="bg-white rounded-lg border border-[#D9D9D9] p-10 text-center text-sm text-[#6B7280]">
    No data found for the selected filters.
</div>
@endif
