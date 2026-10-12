param([string]$PhpBinary = 'php')
$repoRoot = Split-Path -Parent $PSScriptRoot
$artisanPath = Join-Path $repoRoot 'artisan'
$logPath = Join-Path $repoRoot 'storage/logs/queue-worker-local.log'
$hash = [System.Security.Cryptography.SHA256]::Create()
$workerId = [BitConverter]::ToString($hash.ComputeHash([Text.Encoding]::UTF8.GetBytes($repoRoot))).Replace('-', '')
$hash.Dispose()
$mutex = [Threading.Mutex]::new($false, 'Local\LayRateQueue_' + $workerId)
$ownsMutex = $false
try {
    try { $ownsMutex = $mutex.WaitOne(0) } catch [Threading.AbandonedMutexException] { $ownsMutex = $true }
    if (-not $ownsMutex) { exit 0 }
    Set-Location -LiteralPath $repoRoot
    $phpPath = (Get-Command $PhpBinary -ErrorAction Stop).Source
    while ($true) {
        & $phpPath $artisanPath queue:work --sleep=3 --tries=1 --timeout=330 --max-time=3600 *>> $logPath
        Start-Sleep -Seconds 3
    }
} finally {
    if ($ownsMutex) { $mutex.ReleaseMutex() }
    $mutex.Dispose()
}
