{{-- Egg stock QR labels (PrintableTagsController::eggStockLabels). dompdf:
     plain tables, mm units, no flex/grid. Dashed borders are the cut lines. --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: {{ $layout['margin'] }}mm; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #111111; margin: 0; }
    .sheet { border-collapse: collapse; table-layout: fixed; }
    .cell { width: {{ $layout['cell_w'] }}mm; height: {{ $layout['cell_h'] }}mm; border: 0.3mm dashed #9a9a9a; padding: 0; vertical-align: top; overflow: hidden; }
    .label { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .qr { width: 30mm; padding: 2mm 0 0 1.5mm; vertical-align: top; }
    .qr img { width: 29mm; height: 29mm; }
    .info { padding: 2.2mm 2mm 0 1.5mm; vertical-align: top; }
    .head { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .brand { font-size: 5.5pt; color: #666666; letter-spacing: 0.3pt; text-transform: uppercase; padding: 0; white-space: nowrap; }
    .batch { width: 9mm; text-align: right; font-size: 7pt; font-weight: bold; color: #111111; padding: 0; white-space: nowrap; }
    .size { font-size: 12.5pt; font-weight: bold; text-transform: uppercase; margin-top: 0.8mm; line-height: 1; white-space: nowrap; }
    .count { font-size: 8pt; font-weight: bold; margin-top: 1mm; }
    .line { font-size: 7pt; margin-top: 0.7mm; }
    .k { color: #555555; }
    .fresh { font-size: 6.5pt; margin-top: 1.2mm; padding-top: 0.8mm; border-top: 0.2mm solid #cccccc; }
    .empty-cell { border: none; }
    .footer { position: fixed; bottom: -1mm; left: 0; right: 0; font-size: 6.5pt; color: #777777; }
    .none { padding: 20mm 0; text-align: center; font-size: 11pt; color: #555555; }
    .page-break { page-break-after: always; }
</style>
</head>
<body>
<div class="footer">
    LayRate · Egg stock QR labels · {{ $total }} {{ Str::plural('label', $total) }}
    @if($range !== 'all') · harvested in the last {{ $range }} days @endif
    · printed {{ $printedAt->format('m/d/Y g:i A') }} · cut along the dashed lines
</div>

@if($total === 0)
    <div class="none">No egg stock batches match. Add stock first, or choose "All batches".</div>
@endif

@foreach($pages as $pageLabels)
<table class="sheet">
    @foreach($pageLabels->chunk($layout['columns']) as $row)
    <tr>
        @foreach($row as $l)
        <td class="cell">
            <table class="label">
                <tr>
                    <td class="qr"><img src="{{ $l->qr }}" alt=""></td>
                    <td class="info">
                        <table class="head"><tr><td class="brand">LayRate egg stock</td><td class="batch">#{{ $l->id }}</td></tr></table>
                        <div class="size">{{ $l->size }}</div>
                        <div class="count">{{ number_format($l->count) }} eggs · {{ $l->trays }} {{ Str::plural('tray', $l->trays) }}</div>
                        <div class="line"><span class="k">Harvested</span> {{ $l->harvested }}</div>
                        <div class="line"><span class="k">Cage</span> {{ $l->cage }}</div>
                        <div class="fresh"><span class="k">Fresh until</span> {{ $l->fresh_until }}<br><span class="k">Old after</span> {{ $l->old_after }}</div>
                    </td>
                </tr>
            </table>
        </td>
        @endforeach
        @for($i = $row->count(); $i < $layout['columns']; $i++)
        <td class="cell empty-cell"></td>
        @endfor
    </tr>
    @endforeach
</table>
@if(! $loop->last)<div class="page-break"></div>@endif
@endforeach
</body>
</html>
