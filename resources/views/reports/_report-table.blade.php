{{-- Shared by the preview and the printable document — keep both identical.
     Props: $rows (Collection|Paginator), $cageColorMap, $tableKey (optional —
     only needed by the printable view's client-side on-screen pagination JS).
     When printed, the <thead> column row repeats on every page; the running
     page header comes from the @page margin boxes in reports.blade.php. --}}
@php
$reasonColors = ['Disease' => '#721C24', 'Heat Stress' => '#856404', 'Injury' => '#856404', 'Predator' => '#721C24'];
// Columns with no value on any listed row (Farm Hens is filled only for
// imported historical rows) are left out; a '—' placeholder still counts.
$listed = method_exists($rows, 'getCollection') ? $rows->getCollection() : collect($rows);
$cols = array_values(array_filter(array_keys((array) $rows->first()), fn ($col) => $listed->contains(fn ($row) => ! in_array(((array) $row)[$col] ?? null, [null, ''], true))));
@endphp
<div class="overflow-x-auto mb-2">
    <table class="w-full" style="border-collapse:collapse" @if($tableKey ?? null) data-report-table="{{ $tableKey }}" @endif>
        <thead>
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
                @foreach($cols as $key)
                @php
                    $val = $arr[$key] ?? null;
                    $cC = $key === 'cage' ? ($cageColorMap[$val] ?? null) : null;
                    $rC = $key === 'reason' ? ($reasonColors[$val] ?? null) : null;
                    $style = $cC ? "color:{$cC};font-weight:600" : ($rC ? "color:{$rC}" : '');
                @endphp
                @if($key === 'record_type' && str_contains((string) $val, 'Historical'))
                    <td class="px-5 py-3.5 text-sm font-medium text-amber-800 bg-amber-50">{{ $val }}</td>
                @else
                <td class="px-5 py-3.5 text-sm {{ in_array($key, ['date','datetime']) ? 'font-mono' : '' }}"
                    style="{{ $style }}">{{ $val }}</td>
                @endif
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
