<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR — {{ ucfirst($batch->egg_size) }} {{ $batch->harvested_date->format('Y-m-d') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', Arial, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f5f6f8; }
        .card { background: white; border-radius: 12px; border: 1px solid #d9d9d9; padding: 32px; text-align: center; width: 320px; }
        .qr-container { margin: 16px auto; }
        .qr-container svg { width: 200px; height: 200px; }
        @media print { body { background: white; } .card { border: none; box-shadow: none; } }
    </style>
</head>
<body>
    <div class="card">
        <div class="qr-container" id="qrCode"></div>
    </div>

    <script src="/js/qrcode.min.js"></script>
    <script>
        var qr = qrcode(0, 'M');
        qr.addData('{{ $qrData }}');
        qr.make();
        document.getElementById('qrCode').innerHTML = qr.createSvgTag(4, 8);
    </script>
</body>
</html>
