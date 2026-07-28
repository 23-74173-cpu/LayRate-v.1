<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    /* dompdf has limited CSS support (no flexbox/grid) — this view intentionally
       uses plain block/table layout instead of the Tailwind classes the screen
       and print views use. */
    body { font-family: Georgia, 'Times New Roman', serif; color: #333333; font-size: 11px; }
    .letterhead { width: 100%; margin-bottom: 4px; }
    .letterhead td { vertical-align: top; }
    .brand-name { font-weight: bold; color: #102A4C; font-size: 13px; }
    .brand-sub { color: #6B7280; font-size: 9px; }
    .doc-title { text-align: right; font-weight: bold; color: #102A4C; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
    .doc-range { text-align: right; color: #6B7280; font-size: 9px; margin-top: 2px; }
    hr.rule { border: none; border-top: 3px solid #102A4C; margin: 10px 0; }
    .meta-strip { width: 100%; margin-bottom: 14px; font-size: 9px; color: #6B7280; }
    .meta-strip td { padding-right: 12px; }
    .meta-strip .label { font-weight: bold; color: #333333; }
    .section { margin-bottom: 18px; }
    .section-title { font-weight: bold; color: #102A4C; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; border-bottom: 1px solid #D9D9D9; padding-bottom: 4px; }
    table.pills { width: 100%; margin-bottom: 12px; }
    table.pills td { width: 25%; border: 1px solid #D9D9D9; text-align: center; padding: 6px; }
    .pill-label { font-size: 8px; text-transform: uppercase; color: #6B7280; }
    .pill-value { font-size: 14px; font-weight: bold; color: #333333; }
    table.data { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.data thead th { background: #102A4C; color: #ffffff; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; padding: 6px 8px; }
    table.data tbody td { font-size: 9px; padding: 5px 8px; border-bottom: 1px solid #F0F0F0; }
    table.data tbody tr.even { background: #F9F9F7; }
    .no-data { padding: 14px; text-align: center; color: #6B7280; font-size: 10px; }
    .signature-block { width: 100%; margin-top: 40px; padding-top: 12px; border-top: 1px solid #D9D9D9; }
    .signature-block td { width: 50%; padding-right: 40px; font-size: 9px; color: #6B7280; }
    .sig-line { border-bottom: 1px solid #333333; margin: 30px 0 4px 0; }
</style>
</head>
<body>
    @php $cageColorMap = \App\Models\Cage::getColorMap(); @endphp
    @php $reasonColors = ['Disease' => '#721C24', 'Heat Stress' => '#856404', 'Injury' => '#856404', 'Predator' => '#721C24']; @endphp

    <table class="letterhead">
        <tr>
            <td style="width:40px;vertical-align:middle;">
                <img src="{{ public_path('images/layrate-logo-mark.png') }}" style="width:32px;height:32px;">
            </td>
            <td>
                <div class="brand-name">LayRate Poultry Farm</div>
                <div class="brand-sub">Farm Monitor System</div>
            </td>
            <td>
                <div class="doc-title">{{ $type === 'all' ? 'All Reports' : ucfirst($type) . ' Report' }}</div>
                <div class="doc-range">{{ $from && $to ? "{$from} — {$to}" : 'All time' }}</div>
            </td>
        </tr>
    </table>
    <hr class="rule">

    <table class="meta-strip">
        <tr>
            <td><span class="label">Cage:</span> {{ $cageId === 'all' ? 'All Cages' : $cageId }}</td>
            <td><span class="label">Generated:</span> {{ now()->format('F j, Y  H:i') }}</td>
            <td><span class="label">Prepared by:</span> {{ auth()->user()->name }}</td>
            <td><span class="label">Records:</span> {{ collect($sections)->sum(fn($s) => $s['rows']->count()) }}</td>
        </tr>
    </table>

    @foreach($sections as $section)
    <div class="section" @if(!$loop->first) style="page-break-before: always;" @endif>
        @if($type === 'all')
        <div class="section-title">{{ $section['label'] }}</div>
        @endif

        @if(($section['summary'] ?? null) !== null)
        @php $pills = (array) $section['summary']; @endphp
        <table class="pills">
            <tr>
                @foreach($pills as $key => $value)
                <td>
                    <div class="pill-label">{{ strtoupper(str_replace('_', ' ', $key)) }}</div>
                    <div class="pill-value">{{ $value }}</div>
                </td>
                @endforeach
            </tr>
        </table>
        @endif

        @if(!empty($chartImages) && isset($chartImages[$section['type']]))
        <img src="{{ $chartImages[$section['type']] }}" style="width:100%;height:auto;margin-bottom:12px;">
        @endif

        @if($section['rows']->isNotEmpty())
        <table class="data">
            <thead>
                <tr>
                    @foreach(array_keys((array) $section['rows']->first()) as $col)
                    <th>{{ strtoupper(str_replace('_', ' ', $col)) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($section['rows'] as $row)
                @php $arr = (array) $row; @endphp
                <tr class="{{ $loop->even ? 'even' : '' }}">
                    @foreach($arr as $key => $val)
                    @php
                        $cC = $key === 'cage' ? ($cageColorMap[$val] ?? null) : null;
                        $rC = $key === 'reason' ? ($reasonColors[$val] ?? null) : null;
                        $style = $cC ? "color:{$cC};font-weight:bold" : ($rC ? "color:{$rC}" : '');
                    @endphp
                    <td style="{{ $style }}">{{ $val }}</td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="no-data">No data found for the selected filters.</div>
        @endif
    </div>
    @endforeach

    <table class="signature-block">
        <tr>
            <td>
                <div class="sig-line"></div>
                Prepared by: {{ auth()->user()->name }}<br>
                Signature / Date
            </td>
            <td>
                <div class="sig-line"></div>
                Noted by: Name / Position<br>
                Signature / Date
            </td>
        </tr>
    </table>
</body>
</html>
