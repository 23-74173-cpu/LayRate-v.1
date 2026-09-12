<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Too Many Requests · LayRate</title>
<link rel="icon" href="/favicon-32x32.png">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #fafaf9; color: #1f1f1f; min-height: 100vh; min-height: 100dvh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .card { text-align: center; max-width: 28rem; }
    .icon { width: 4rem; height: 4rem; margin: 0 auto 1rem; border-radius: 9999px; background: #fdf3e0; display: flex; align-items: center; justify-content: center; }
    .eyebrow { font-size: 11px; font-weight: 600; letter-spacing: .125em; text-transform: uppercase; color: #a39e98; margin-bottom: .25rem; }
    h1 { font-size: 1.5rem; font-weight: 600; margin-bottom: .5rem; }
    p { font-size: .875rem; color: #6B7280; margin-bottom: 1.5rem; line-height: 1.5; }
    .btn { display: inline-flex; align-items: center; gap: .5rem; padding: .625rem 1.5rem; border-radius: .5rem; font-size: .875rem; font-weight: 500; text-decoration: none; }
    .btn-primary { background: #002D5E; color: #fff; }
    .btn-secondary { color: #1f1f1f; border: 1px solid #e6e6e6; margin-left: .75rem; background: #fff; }
</style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#8a5a00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14"/><path d="M5 2h14"/><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"/><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/></svg>
        </div>
        <p class="eyebrow">Error 429</p>
        <h1>Slow Down a Little</h1>
        <p>You've made too many requests in a short time. Wait about a minute, then try again.</p>
        <a class="btn btn-primary" href="javascript:location.reload()">Try Again</a><a class="btn btn-secondary" href="/">Go to Dashboard</a>
    </div>
</body>
</html>
