{{-- Shared by the preview and the printable document — keep both identical.
     Props: $rows (Collection|Paginator), $cageColorMap, $tableKey (optional —
     only needed by the printable view's client-side on-screen pagination JS) --}}
@php
$reasonColors = ['Disease' => '#721C24', 'Heat Stress' => '#856404', 'Injury' => '#856404', 'Predator' => '#721C24'];
@endphp
<div class="overflow-x-auto mb-2">
    <table class="w-full" style="border-collapse:collapse" @if($tableKey ?? null) data-report-table="{{ $tableKey }}" @endif>
        <thead>
            <tr style="background:#102A4C;color:#ffffff;">
                @foreach(array_keys((array) $rows->first()) as $col)
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
