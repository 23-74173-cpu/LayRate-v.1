{{-- Same document markup as the printable view (letterhead, meta strip,
     table styling, signature block) via the shared reports/_letterhead,
     _meta-strip, _report-table, and _signature-block partials — the two must
     stay visually identical apart from pagination (this preview keeps its own
     server-side pagination; the printable view shows every row). --}}
@if($type === 'all')
<div class="bg-white rounded-lg border border-[#D9D9D9] p-8">
    <div class="flex justify-end mb-4 no-print">
        {{-- data-turbo="false": force a real hard navigation. A Turbo Drive
             visit here doesn't reliably re-run this page's chart-init script
             before the user sees the page, leaving the printable doc's chart
             canvas blank until a manual reload — a hard load never has that
             race. --}}
        <x-button :href="route('reports', array_merge(request()->query(), ['full' => 1]))" data-turbo="false">
            <i data-lucide="file-text" class="w-4 h-4"></i> View Printable Report
        </x-button>
    </div>

    @include('reports._letterhead', ['type' => $type, 'from' => $from, 'to' => $to])
    @php $recordCount = collect($sections)->sum(fn($s) => $s['rows']->total()); @endphp
    @include('reports._meta-strip', ['cageId' => $cageId, 'recordCount' => $recordCount])

    @foreach($sections as $section)
    <div class="mb-10 {{ !$loop->first ? 'pt-6 border-t border-[#D9D9D9]' : '' }}">
        <h2 class="text-sm font-bold text-[#102A4C] uppercase tracking-wide mb-4">{{ $section['label'] }}</h2>

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
        @include('reports._report-table', ['rows' => $section['rows'], 'cageColorMap' => $cageColorMap])
        <x-paginator :paginator="$section['rows']" />
        @else
        <div class="text-center text-sm text-[#6B7280] py-6">No data found for the selected filters.</div>
        @endif
    </div>
    @endforeach

    @include('reports._signature-block')
</div>
@elseif($rows->isNotEmpty())
<div class="bg-white rounded-lg border border-[#D9D9D9] p-8">
    <div class="flex justify-end mb-4 no-print">
        {{-- data-turbo="false": see rationale above. --}}
        <x-button :href="route('reports', array_merge(request()->query(), ['full' => 1]))" data-turbo="false">
            <i data-lucide="file-text" class="w-4 h-4"></i> View Printable Report
        </x-button>
    </div>

    @include('reports._letterhead', ['type' => $type, 'from' => $from, 'to' => $to])
    @include('reports._meta-strip', ['cageId' => $cageId, 'recordCount' => $rows->total()])

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

    @include('reports._report-table', ['rows' => $rows, 'cageColorMap' => $cageColorMap])
    <x-paginator :paginator="$rows" />

    @include('reports._signature-block')
</div>
@else
<div class="bg-white rounded-lg border border-[#D9D9D9] p-10 text-center text-sm text-[#6B7280]">
    No data found for the selected filters.
</div>
@endif
