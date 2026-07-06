<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $cage->cage_code }} — Cage Label</title>
    <link rel="icon" href="/favicon.ico">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Inter, -apple-system, system-ui, "Segoe UI", Helvetica, Arial, sans-serif;
            display: flex; flex-direction: column; align-items: center;
            padding: 2rem; color: #1f1f1f; background: #f6f5f4;
        }
        .label {
            background: #ffffff; border: 4px solid {{ $cage->color }};
            border-radius: 16px; padding: 3rem 4rem; text-align: center;
            width: 100%; max-width: 640px;
        }
        .code { font-size: 96px; font-weight: 800; letter-spacing: -2px; color: {{ $cage->color }}; line-height: 1.1; }
        .meta { margin-top: 1.5rem; display: flex; justify-content: center; gap: 2.5rem; font-size: 20px; color: #31302e; }
        .meta strong { display: block; font-size: 32px; }
        .breed { margin-top: 1rem; font-size: 18px; color: #615d59; }
        .toolbar { margin-bottom: 1.5rem; display: flex; gap: 0.75rem; }
        .toolbar button, .toolbar a {
            font: inherit; font-size: 14px; padding: 0.5rem 1.25rem; border-radius: 9999px;
            border: 1px solid #e6e6e6; background: #ffffff; color: #1f1f1f; cursor: pointer; text-decoration: none;
        }
        .toolbar button.primary { background: #0075de; border-color: #0075de; color: #ffffff; }
        @media print {
            body { background: #ffffff; padding: 0; min-height: 100vh; justify-content: center; }
            .toolbar { display: none; }
            .label { border-width: 6px; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('cages.index') }}">← Back to Cages</a>
        <button type="button" class="primary" onclick="window.print()">Print label</button>
    </div>

    <div class="label">
        <div class="code">{{ $cage->cage_code }}</div>
        <div class="meta">
            <span><strong>{{ $cage->rows }}×{{ $cage->slots_per_row }}</strong> layout</span>
            <span><strong>{{ $cage->cageSlots->count() }}</strong> slots</span>
            <span><strong>{{ $cage->total_capacity }}</strong> capacity</span>
        </div>
        <div class="breed">
            {{ $cage->hens->first()?->breed ?? 'No hens placed' }}
            @if($cage->hens->count() > 0) · {{ $cage->hens->count() }} hens @endif
        </div>
    </div>
</body>
</html>
