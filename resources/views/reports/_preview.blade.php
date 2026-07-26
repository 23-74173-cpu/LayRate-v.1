@if($rows->isNotEmpty())
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
