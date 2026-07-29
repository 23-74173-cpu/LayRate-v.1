<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Georgia, 'Times New Roman', serif; color: #333333; font-size: 11px; }
    .letterhead { width: 100%; margin-bottom: 4px; }
    .letterhead td { vertical-align: top; }
    .brand-name { font-weight: bold; color: #102A4C; font-size: 13px; }
    .brand-sub { color: #6B7280; font-size: 9px; }
    .doc-title { text-align: right; font-weight: bold; color: #102A4C; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
    .doc-range { text-align: right; color: #6B7280; font-size: 9px; margin-top: 2px; }
    hr.rule { border: none; border-top: 3px solid #102A4C; margin: 10px 0; }
    .meta-strip { width: 100%; margin-bottom: 14px; font-size: 9px; color: #000000; }
    .meta-strip td { padding-right: 12px; }
    .meta-strip .label { font-weight: bold; color: #000000; }
    .section { margin-bottom: 18px; }
    .section-title { font-weight: bold; color: #102A4C; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; border-bottom: 1px solid #D9D9D9; padding-bottom: 4px; }
    table.data { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.data thead th { background: #E5E7EB; color: #000000; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; padding: 6px 8px; }
    table.data tbody td { font-size: 9px; padding: 5px 8px; border-bottom: 1px solid #F0F0F0; }
    table.data tbody tr.even { background: #F9F9F7; }
    .no-data { padding: 14px; text-align: center; color: #6B7280; font-size: 10px; }
    .chart-container { text-align: center; margin: 16px 0; }
    .chart-container img { max-width: 100%; height: auto; max-height: 250px; }
    .signature-block { width: 100%; margin-top: 40px; padding-top: 12px; border-top: 1px solid #D9D9D9; }
    .signature-block td { width: 50%; padding-right: 40px; font-size: 9px; color: #6B7280; }
    .sig-line { border-bottom: 1px solid #333333; margin: 30px 0 4px 0; }
    .footer { text-align: center; font-size: 8px; color: #9CA3AF; margin-top: 20px; border-top: 1px solid #D9D9D9; padding-top: 6px; }
</style>
</head>
<body>
@php
    $_targetDates = $forecasts->pluck('target_date')->sort();
    $_rangeLabel = $_targetDates->isNotEmpty()
        ? \Illuminate\Support\Carbon::parse($_targetDates->first())->format('Y-m-d') . ' — ' . \Illuminate\Support\Carbon::parse($_targetDates->last())->format('Y-m-d')
        : 'No forecast dates';
@endphp

<table class="letterhead">
    <tr>
        <td style="width:40px;vertical-align:middle;">
            <div style="width:32px;height:32px;background:#102A4C;color:#fff;font-size:14px;font-weight:bold;text-align:center;line-height:32px;border-radius:4px;">L</div>
        </td>
        <td>
            <div class="brand-name">LayRate Poultry Farm</div>
            <div class="brand-sub">Farm Monitor System</div>
        </td>
        <td style="text-align:right;">
            <div class="doc-title">Forecast Report</div>
            <div class="doc-range">{{ $_rangeLabel }}</div>
        </td>
    </tr>
</table>
<hr class="rule">

<table class="meta-strip">
    <tr>
        <td><span class="label">Scope:</span> {{ ucfirst($scope) }}</td>
        @if ($cageCode)<td><span class="label">Cage:</span> {{ $cageCode }}</td>@endif
        @if ($breed)<td><span class="label">Breed:</span> {{ $breed }}</td>@endif
        <td><span class="label">Horizon:</span> {{ $horizon }} days</td>
        <td><span class="label">Generated:</span> {{ now()->format('F j, Y  H:i') }}</td>
        <td><span class="label">Prepared by:</span> {{ auth()->user()->name }}</td>
    </tr>
</table>

@if ($chartImage)
<div class="section">
    <div class="section-title">Forecast Trend</div>
    <div class="chart-container">
        <img src="{{ $chartImage }}" alt="Forecast chart">
    </div>
</div>
@endif

<div class="section">
    <div class="section-title">Forecast Data</div>
    <table class="data">
        <thead>
            <tr>
                <th>Target Date</th>
                <th>Predicted Egg Count</th>
                <th>Confidence</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($forecasts as $i => $f)
            <tr class="{{ $i % 2 === 0 ? 'even' : '' }}">
                <td>{{ \Illuminate\Support\Carbon::parse($f->target_date)->format('Y-m-d') }}</td>
                <td>{{ number_format($f->predicted_egg_count ?? 0) }}</td>
                <td>{{ $f->confidence }}%</td>
            </tr>
            @empty
            <tr><td colspan="3" class="no-data">No forecast data available.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

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

<div class="footer">
    LayRate Poultry Farm Management System &mdash; This report was generated automatically.
</div>
</body>
</html>
