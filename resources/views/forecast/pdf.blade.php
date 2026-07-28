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
    .meta-strip { width: 100%; margin-bottom: 14px; font-size: 9px; color: #6B7280; }
    .meta-strip td { padding-right: 12px; }
    .meta-strip .label { font-weight: bold; color: #333333; }
    .section { margin-bottom: 18px; }
    .section-title { font-weight: bold; color: #102A4C; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; border-bottom: 1px solid #D9D9D9; padding-bottom: 4px; }
    table.data { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.data thead th { background: #102A4C; color: #ffffff; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; padding: 6px 8px; }
    table.data tbody td { font-size: 9px; padding: 5px 8px; border-bottom: 1px solid #F0F0F0; }
    table.data tbody tr.even { background: #F9F9F7; }
    .chart-container { text-align: center; margin: 16px 0; }
    .chart-container img { max-width: 100%; height: auto; max-height: 250px; }
    .sig-block { margin-top: 30px; }
    .sig-block td { width: 50%; vertical-align: top; font-size: 9px; color: #6B7280; }
    .sig-line { border-top: 1px solid #333333; width: 180px; margin-top: 20px; margin-bottom: 4px; }
    .footer { text-align: center; font-size: 8px; color: #9CA3AF; margin-top: 20px; border-top: 1px solid #D9D9D9; padding-top: 6px; }
</style>
</head>
<body>
@php
    $_logoSrc = '';
    $_logoPath = public_path('images/layrate-logo.png');
    if (file_exists($_logoPath)) {
        $_logoSrc = 'data:image/png;base64,' . base64_encode(file_get_contents($_logoPath));
    }
@endphp

<table class="letterhead">
    <tr>
        <td style="width:28px;padding-right:6px;">
            @if($_logoSrc)<img src="{{ $_logoSrc }}" alt="LayRate" style="width:28px;height:28px;">@endif
        </td>
        <td>
            <div class="brand-name">LayRate Poultry Farm</div>
            <div class="brand-sub">Production Forecast Report</div>
        </td>
        <td style="text-align:right;">
            <div class="doc-title">Forecast Report</div>
            <div class="doc-range">Generated: {{ now()->format('F j, Y') }}</div>
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
        <td><span class="label">Forecast date:</span> {{ now()->toDateString() }}</td>
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
                <td>{{ $f->target_date }}</td>
                <td>{{ number_format($f->predicted_egg_count ?? 0) }}</td>
                <td>{{ $f->confidence }}%</td>
            </tr>
            @empty
            <tr><td colspan="3" class="no-data">No forecast data available.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<table class="sig-block">
    <tr>
        <td>
            <div class="sig-line"></div>
            <div>Authorized Signature</div>
        </td>
        <td style="text-align:right;">
            <div>Date: {{ now()->format('F j, Y') }}</div>
        </td>
    </tr>
</table>

<div class="footer">
    LayRate Poultry Farm Management System &mdash; This report was generated automatically.
</div>
</body>
</html>
