<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $cage->cage_code }} — Cage Label</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #1f1f1f; padding: 24px; }
        .letterhead { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid {{ $cage->color }}; }
        .letterhead h1 { font-size: 28px; font-weight: 700; color: {{ $cage->color }}; }
        .letterhead .meta { font-size: 13px; color: #615d59; }
        .label-grid { display: grid; gap: 4px; }
        .label-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background-color: {{ $cage->colorSoft }}; border: 1.5px solid {{ $cage->color }}; border-radius: 8px 8px 0 0; }
        .label-header h2 { font-size: 16px; font-weight: 600; color: #1f1f1f; }
        .label-header .stats { display: flex; gap: 24px; font-size: 13px; color: #615d59; }
        .slot-grid { display: grid; gap: 6px; padding: 16px; border: 1.5px solid #e6e6e6; border-top: none; border-radius: 0 0 8px 8px; }
        .slot-card { border-radius: 6px; padding: 10px; border: 1px solid #e6e6e6; display: flex; flex-direction: column; gap: 4px; }
        .slot-card.header { background-color: #f6f5f4; text-align: center; font-weight: 600; font-size: 12px; color: #615d59; }
        .slot-card.occupied { background-color: #f6f5f4; }
        .slot-card.sensor { border-color: #2a9d6a; background-color: #d6f0e3; }
        .slot-card.empty { background-color: #ffffff; }
        .slot-number { font-size: 12px; font-weight: 600; color: #31302e; }
        .slot-occupancy { font-size: 13px; font-weight: 500; color: #1f1f1f; }
        .slot-max { font-size: 11px; color: #a39e98; }
        .slot-badge { display: inline-block; font-size: 9px; font-weight: 600; text-transform: uppercase; padding: 1px 5px; border-radius: 3px; letter-spacing: 0.03em; }
        .slot-badge.sensor-badge { background-color: #2a9d6a; color: #fff; }
        .breed-table { margin-top: 20px; width: 100%; border-collapse: collapse; font-size: 12px; }
        .breed-table th { text-align: left; padding: 6px 8px; background-color: #f6f5f4; font-weight: 600; color: #615d59; border-bottom: 1.5px solid #e6e6e6; }
        .breed-table td { padding: 6px 8px; border-bottom: 1px solid #e6e6e6; color: #31302e; }
        .footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #e6e6e6; display: flex; justify-content: space-between; font-size: 11px; color: #a39e98; }
        .footer .signature { margin-top: 8px; font-size: 12px; color: #1f1f1f; }
        @media print {
            @page { margin: 12mm; }
            body { padding: 0; }
            .no-print { display: none; }
        }
        .no-print { margin-top: 16px; }
        .no-print button { padding: 8px 20px; font-size: 14px; font-weight: 500; border: none; border-radius: 6px; background-color: #0075de; color: #fff; cursor: pointer; }
        .no-print button:hover { opacity: 0.85; }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Print this label</button>
        <button onclick="window.close()" style="background-color: #615d59; margin-left: 8px;">Close</button>
    </div>

    <div class="letterhead">
        <div>
            <h1>{{ $cage->cage_code }}</h1>
            <div class="meta">
                {{ $cage->formatted_location }} &middot;
                {{ $cage->rows }}&times;{{ $cage->slots_per_row }} grid &middot;
                {{ $cage->total_capacity }} total capacity
            </div>
        </div>
    </div>

    <div class="label-grid">
        <div class="label-header">
            <h2>Slot Configuration</h2>
            <div class="stats">
                <span>{{ $cage->cageSlots->count() }} slots</span>
                <span>{{ $cage->cageSlots->where('current_occupancy', '>', 0)->count() }} occupied</span>
                <span>{{ $cage->cageSlots->filter(fn($s) => $s->hasBreakbeam())->count() }} sensors</span>
            </div>
        </div>
        <div class="slot-grid" style="grid-template-columns: 36px repeat({{ $cage->slots_per_row }}, 1fr);">
            @for($r = 1; $r <= $cage->rows; $r++)
                <div class="slot-card header" style="display: flex; align-items: center; justify-content: center; padding: 6px;">R{{ $r }}</div>
                @for($c = 1; $c <= $cage->slots_per_row; $c++)
                    @php
                        $slot = $cage->cageSlots->firstWhere('row_number', $r) && $cage->cageSlots->firstWhere('column_number', $c)
                            ? $cage->cageSlots->first(fn($s) => $s->row_number === $r && $s->column_number === $c)
                            : null;
                        if (!$slot) {
                            $slot = $cage->cageSlots->firstWhere('slot_number', ($r - 1) * $cage->slots_per_row + $c);
                        }
                        $isSensor = $slot?->hasBreakbeam() ?? false;
                        $occ = $slot?->current_occupancy ?? 0;
                    @endphp
                    <div class="slot-card {{ $occ > 0 ? 'occupied' : 'empty' }} {{ $isSensor ? 'sensor' : '' }}" style="padding: 8px;">
                        <div class="slot-number">
                            #{{ $slot?->slot_number ?? '-' }}
                            @if($isSensor)
                                <span class="slot-badge sensor-badge">S</span>
                            @endif
                        </div>
                        <div class="slot-occupancy">
                            {{ $occ }}<span class="slot-max">/{{ $cage->max_chickens_per_slot }}</span>
                        </div>
                    </div>
                @endfor
            @endfor
        </div>
    </div>

    @if($hensBySlot->isNotEmpty())
    <table class="breed-table">
        <thead>
            <tr>
                <th>Slot</th>
                <th>Chicken ID</th>
                <th>Breed</th>
                <th>Age (weeks)</th>
                <th>Flock Age</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cage->cageSlots as $slot)
                @php
                    $slotHens = $hensBySlot->get($slot->id, collect());
                @endphp
                @if($slotHens->isNotEmpty())
                    @foreach($slotHens as $hen)
                    <tr>
                        <td>{{ $slot->row_number }}-{{ $slot->column_number }}</td>
                        <td>{{ $hen->chicken_id }}</td>
                        <td>{{ $hen->breed }}</td>
                        <td>{{ $hen->current_age_weeks }}</td>
                        <td>{{ $hen->flock_age_weeks }}</td>
                    </tr>
                    @endforeach
                @else
                    <tr>
                        <td>{{ $slot->row_number }}-{{ $slot->column_number }}</td>
                        <td colspan="4" style="color: #a39e98;">Empty</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        <div>
            <div>Printed {{ now()->format('F j, Y \a\t g:i A') }}</div>
            <div class="signature">___________________________</div>
            <div style="font-size: 11px; color: #a39e98;">Farm Manager Signature</div>
        </div>
        <div>
            <div>LayRate Poultry Farm Management</div>
        </div>
    </div>
</body>
</html>
