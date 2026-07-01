<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR — {{ $batch->egg_size }} Batch #{{ $batch->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', Arial, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f5f6f8; }
        .card { background: white; border-radius: 12px; border: 1px solid #d9d9d9; padding: 32px; text-align: center; width: 320px; }
        .header { margin-bottom: 16px; }
        .header h1 { font-size: 18px; color: #102a4c; margin-bottom: 4px; }
        .header p { font-size: 12px; color: #6b7280; }
        .qr-container { margin: 16px auto; }
        .qr-container svg { width: 200px; height: 200px; }
        .details { margin-top: 16px; text-align: left; }
        .detail-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
        .detail-row .label { color: #6b7280; }
        .detail-row .value { color: #333333; font-weight: 500; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
        .fresh { background: #e8f5ec; color: #1f6b3a; }
        .aging { background: #fdf3e0; color: #8a5a00; }
        .old { background: #fbe4e6; color: #9b1c24; }
        @media print { body { background: white; } .card { border: none; box-shadow: none; } }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>LayRate Poultry Farm</h1>
            <p>Egg Stock Batch Label</p>
        </div>

        <div class="qr-container" id="qrCode"></div>

        <div class="details">
            <div class="detail-row">
                <span class="label">Size</span>
                <span class="value">{{ ucfirst($batch->egg_size) }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Count</span>
                <span class="value">{{ number_format($batch->count) }} eggs</span>
            </div>
            <div class="detail-row">
                <span class="label">Trays</span>
                <span class="value">{{ (int) ceil($batch->count / 30) }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Harvested</span>
                <span class="value">{{ $batch->harvested_date->format('Y-m-d') }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Source</span>
                <span class="value">{{ $batch->cage?->cage_code ?? '—' }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Freshness</span>
                <span class="value"><span class="badge {{ $batch->freshness_status }}">{{ ucfirst($batch->freshness_status) }}</span></span>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/qrcode.min.js') }}"></script>
    <script>
        var qr = qrcode(0, 'M');
        qr.addData('{{ $qrData }}');
        qr.make();
        document.getElementById('qrCode').innerHTML = qr.createSvgTag(4, 8);
    </script>
</body>
</html>
