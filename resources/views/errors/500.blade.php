<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Something Went Wrong · LayRate</title>
<link rel="icon" href="/favicon-32x32.png">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #fafaf9; color: #1f1f1f; min-height: 100vh; min-height: 100dvh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .card { text-align: center; max-width: 28rem; }
    .icon { width: 4rem; height: 4rem; margin: 0 auto 1rem; border-radius: 9999px; background: #fbe4e6; display: flex; align-items: center; justify-content: center; }
    .eyebrow { font-size: 11px; font-weight: 600; letter-spacing: .125em; text-transform: uppercase; color: #a39e98; margin-bottom: .25rem; }
    h1 { font-size: 1.5rem; font-weight: 600; margin-bottom: .5rem; }
    p { font-size: .875rem; color: #6B7280; margin-bottom: 1.5rem; line-height: 1.5; }
    .ref { font-size: 11px; color: #a39e98; margin-bottom: 1.5rem; }
    .btn { display: inline-flex; align-items: center; gap: .5rem; padding: .625rem 1.5rem; border-radius: .5rem; font-size: .875rem; font-weight: 500; text-decoration: none; }
    .btn-primary { background: #002D5E; color: #fff; }
    .btn-secondary { color: #1f1f1f; border: 1px solid #e6e6e6; margin-left: .75rem; background: #fff; }
</style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9b1c24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
        </div>
        <p class="eyebrow">Error 500</p>
        <h1>Something Went Wrong</h1>
        <p>An unexpected problem occurred on the server. Your data is safe — please try again, and contact your administrator if this keeps happening.</p>
        <p class="ref">Reference: {{ now()->format('Ymd-His') }}</p>
        <a class="btn btn-primary" href="/">Go to Dashboard</a><a class="btn btn-secondary" href="javascript:history.back()">Go Back</a>
    </div>
</body>
</html>
