{{-- Hen foot tags (PrintableTagsController::henFootTags): long strips that
     wrap around the leg. The shaded tab at the right end is the overlap for
     glue or tape. dompdf: plain tables, mm units. --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: {{ $layout['margin'] }}mm; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #111111; margin: 0; }
    .sheet { border-collapse: collapse; table-layout: fixed; }
    /* Row height = cell_h exactly: 1mm gap + 10mm strip (+ borders) + 1mm gap. */
    .cell { width: {{ $layout['cell_w'] }}mm; height: {{ $layout['cell_h'] - 2.6 }}mm; padding: 1mm 1.5mm; vertical-align: middle; }
    /* Auto table layout on purpose: dompdf's fixed layout ignores the tab's
       width and split the strip in half, cutting off the tag code. */
    .strip { width: {{ $layout['cell_w'] - 3 }}mm; height: 10mm; border-collapse: collapse; border: 0.3mm dashed #8a8a8a; }
    .main { padding: 0 2mm; vertical-align: middle; overflow: hidden; }
    .code { font-size: 11pt; font-weight: bold; letter-spacing: 0.3pt; line-height: 1.1; white-space: nowrap; }
    .meta { font-size: 6.3pt; color: #333333; margin-top: 0.4mm; white-space: nowrap; }
    .tab { width: 15mm; background: #ededed; border-left: 0.3mm dotted #8a8a8a; text-align: center; vertical-align: middle; font-size: 5.5pt; color: #777777; }
    .empty-cell { }
    .footer { position: fixed; bottom: -1mm; left: 0; right: 0; font-size: 6.5pt; color: #777777; }
    .none { padding: 20mm 0; text-align: center; font-size: 11pt; color: #555555; }
    .page-break { page-break-after: always; }
</style>
</head>
<body>
<div class="footer">
    LayRate · Hen foot tags · {{ $cage ? $cage->cage_code : 'all active cages' }} · {{ $total }} {{ Str::plural('hen', $total) }}
    · printed {{ $printedAt->format('m/d/Y g:i A') }} · cut along the dashed lines, wrap around the leg, glue the grey tab underneath
</div>

@if($total === 0)
    <div class="none">No active hens are placed {{ $cage ? 'in ' . $cage->cage_code : 'in any cage' }}.</div>
@endif

@foreach($pages as $pageTags)
<table class="sheet">
    @foreach($pageTags->chunk($layout['columns']) as $row)
    <tr>
        @foreach($row as $t)
        <td class="cell">
            <table class="strip">
                <tr>
                    <td class="main">
                        <div class="code">{{ $t->code }}</div>
                        <div class="meta">{{ $t->cage }} · slot {{ $t->slot }} · {{ $t->breed }}@if($t->id && $t->id !== $t->code) · {{ $t->id }}@endif</div>
                    </td>
                    <td class="tab">glue</td>
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
