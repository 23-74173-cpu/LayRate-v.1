<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR — {{ ucfirst($batch->egg_size) }} {{ $batch->harvested_date->format('m/d/Y') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', Arial, sans-serif; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 16px; min-height: 100vh; background: #f5f6f8; color: #111; padding: 16px; }
        .card { background: white; border-radius: 12px; border: 1px solid #d9d9d9; padding: 24px; text-align: center; width: 320px; max-width: 100%; }
        .qr-container { margin: 0 auto 12px; }
        .qr-container svg { width: 200px; height: 200px; }
        .size { font-size: 22px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .batch { font-size: 12px; color: #555; margin-top: 2px; }
        .facts { margin-top: 12px; text-align: left; font-size: 13px; border-top: 1px solid #e5e5e5; }
        .facts div { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; border-bottom: 1px solid #f0f0f0; }
        .facts span:first-child { color: #666; }
        .facts span:last-child { font-weight: 600; text-align: right; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; }
        .btn { display: inline-flex; align-items: center; gap: 8px; border-radius: 9999px; padding: 10px 18px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; border: 1px solid #002d5e; }
        .btn-primary { background: #002d5e; color: #fff; }
        .btn-secondary { background: #fff; color: #002d5e; }
        @media print {
            body { background: white; min-height: 0; padding: 0; }
            .card { border: 1px dashed #999; box-shadow: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="qr-container" id="qrCode"></div>
        <div class="size">{{ ucfirst($batch->egg_size) }}</div>
        <div class="batch">LayRate egg stock · batch #{{ $batch->id }}</div>
        <div class="facts">
            <div><span>Eggs</span><span>{{ number_format($batch->count) }} · {{ (int) ceil($batch->count / 30) }} {{ Str::plural('tray', (int) ceil($batch->count / 30)) }}</span></div>
            <div><span>Harvested</span><span>{{ $batch->harvested_date->format('m/d/Y') }}</span></div>
            <div><span>Cage</span><span>{{ $batch->cage?->cage_code ?? '—' }}</span></div>
            <div><span>Fresh until</span><span>{{ $freshUntil }}</span></div>
            <div><span>Old after</span><span>{{ $oldAfter }}</span></div>
        </div>
    </div>

    <div class="actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
        <a class="btn btn-secondary" href="{{ route('eggs.stocks.labels-pdf', ['ids' => [$batch->id]]) }}" target="_blank">Download PDF</a>
    </div>

    <script src="/js/qrcode.min.js"></script>
    <script>
        var qr = qrcode(0, 'M');
        qr.addData(@json($qrData));
        qr.make();
        document.getElementById('qrCode').innerHTML = qr.createSvgTag(4, 8);
    </script>
</body>
</html>
