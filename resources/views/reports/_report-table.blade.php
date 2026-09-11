{{-- Shared by the preview and the printable document — keep both identical.
     Props: $rows (Collection|Paginator), $cageColorMap, $tableKey (optional —
     only needed by the printable view's client-side on-screen pagination JS),
     $printHeader (optional array ['type'=>, 'from'=>, 'to'=>] — when given,
     repeats the brand letterhead as an extra <thead> row so it survives page
     breaks when browser-printed; browsers repeat a real <thead> on every
     printed page, but never repeat a plain block element the same way, which
     is why the letterhead used to disappear after page 1. Hidden on screen
     via .no-screen — print-only, so the on-screen view isn't left showing
     the letterhead twice.) --}}
@php
$reasonColors = ['Disease' => '#721C24', 'Heat Stress' => '#856404', 'Injury' => '#856404', 'Predator' => '#721C24'];
$cols = array_keys((array) $rows->first());
@endphp
<div class="overflow-x-auto mb-2">
    <table class="w-full" style="border-collapse:collapse" @if($tableKey ?? null) data-report-table="{{ $tableKey }}" @endif>
        <thead>
            @if($printHeader ?? null)
            <tr class="print-repeat-header">
                <th colspan="{{ count($cols) }}" style="padding:8px 0;border:none;background:#fff;">
                    @include('reports._letterhead', ['type' => $printHeader['type'], 'from' => $printHeader['from'], 'to' => $printHeader['to']])
                </th>
            </tr>
            @endif
            <tr style="background:#E5E7EB;color:#000000;">
                @foreach($cols as $col)
                <th class="px-5 py-3 text-left text-xs tracking-widest uppercase font-medium whitespace-nowrap">{{ strtoupper(str_replace('_', ' ', $col)) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            @php $arr = (array) $row; @endphp
            <tr class="{{ $loop->even ? 'bg-[#F9F9F7]' : 'bg-white' }}">
                @foreach($arr as $key => $val)
                @php
                    $cC = $key === 'cage' ? ($cageColorMap[$val] ?? null) : null;
                    $rC = $key === 'reason' ? ($reasonColors[$val] ?? null) : null;
                    $style = $cC ? "color:{$cC};font-weight:600" : ($rC ? "color:{$rC}" : '');
                @endphp
                <td class="px-5 py-3.5 text-sm {{ in_array($key, ['date','datetime']) ? 'font-mono' : '' }}"
                    style="{{ $style }}">{{ $val }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
