<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class SettingsController extends Controller
{
    public function storeFarmLayout(Request $request)
    {
        $data = $request->validate([
            'rows' => 'required|integer|min:1|max:50',
            'cols' => 'required|integer|min:1|max:50',
        ]);

        Setting::set('farm_grid_rows', $data['rows']);
        Setting::set('farm_grid_cols', $data['cols']);

        if ($request->expectsJson() || $request->isJson()) {
            return response()->json(['success' => true, 'rows' => (int) $data['rows'], 'cols' => (int) $data['cols']]);
        }

        return redirect()->route('dashboard')->with('success', 'Farm layout configured.');
    }

    public function backupNow(Request $request)
    {
        $config = config('database.connections.' . config('database.default'));

        if ($config['driver'] !== 'mysql') {
            return back()->withErrors(['backup' => 'Backup currently only supports MySQL.']);
        }

        $backupDir = storage_path('app/private/backups');
        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0775, true, true);
        }
        // Ensure web server user can write
        @chmod($backupDir, 0775);
        @chgrp($backupDir, 'www-data');

        $timestamp = now()->format('Y-m-d_His');
        $filename = "layrate_backup_{$timestamp}.sql";
        $filepath = $backupDir . '/' . $filename;

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '3306';
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'] ?? '';

        $cmd = array_filter([
            'mysqldump',
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $username,
            '--password=' . $password,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--result-file=' . $filepath,
            $database,
        ]);

        $process = new Process($cmd);
        $process->setTimeout(300);
        $process->run();

        $routineWarning = str_contains($process->getErrorOutput(), 'Column count of mysql.proc');

        if (! $process->isSuccessful() && ! $routineWarning) {
            @unlink($filepath);
            return back()->withErrors(['backup' => 'mysqldump failed: ' . $process->getErrorOutput()]);
        }

        $size = filesize($filepath);
        if ($size === false || $size === 0) {
            @unlink($filepath);
            return back()->withErrors(['backup' => 'Backup file is empty.']);
        }

        // Retention: keep last 7
        $this->pruneOldBackups($backupDir, 7);

        return response()->download($filepath, $filename)->deleteFileAfterSend(true);
    }

    private function pruneOldBackups(string $directory, int $keep): void
    {
        $files = collect(File::files($directory))
            ->filter(fn ($f) => $f->getExtension() === 'sql')
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        if ($files->count() <= $keep) {
            return;
        }

        $toDelete = $files->slice($keep);
        foreach ($toDelete as $file) {
            File::delete($file->getPathname());
        }
    }
}
