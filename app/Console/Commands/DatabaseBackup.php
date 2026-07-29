<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Process;
use Symfony\Component\Process\Process as SymfonyProcess;

class DatabaseBackup extends Command
{
    protected $signature = 'db:backup {--retention= : Number of backups to keep (default: 7)}';

    protected $description = 'Dump the database to a timestamped .sql file in storage/app/private/backups';

    public function handle(): int
    {
        $config = config('database.connections.' . config('database.default'));

        if ($config['driver'] !== 'mysql') {
            $this->error('Backup currently only supports MySQL. Configured driver: ' . $config['driver']);
            return self::FAILURE;
        }

        $backupDir = storage_path('app/private/backups');
        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0775, true, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $filename = "layrate_backup_{$timestamp}.sql";
        $filepath = $backupDir . '/' . $filename;

        $this->info("Creating database backup: {$filename}");

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

        $process = new SymfonyProcess($cmd);
        $process->setTimeout(300);
        $process->run();

        // Exit code 2 from mysqldump/mariadb-dump means routine dump failed
        // (schema mismatch) but the table data was dumped successfully.
        $routineWarning = str_contains($process->getErrorOutput(), 'Column count of mysql.proc');

        if (! $process->isSuccessful() && ! $routineWarning) {
            $this->error('mysqldump failed: ' . $process->getErrorOutput());
            @unlink($filepath);
            return self::FAILURE;
        }

        if ($routineWarning) {
            $this->warn('Note: Stored routines could not be dumped (schema mismatch). Table data is intact.');
        }

        $size = filesize($filepath);
        if ($size === false || $size === 0) {
            $this->error('Backup file is empty — mysqldump produced no output.');
            @unlink($filepath);
            return self::FAILURE;
        }

        $this->info("Backup created: {$filename} (" . number_format($size) . " bytes)");

        // Retention: keep last N backups
        $retention = (int) ($this->option('retention') ?? 7);
        $this->pruneOldBackups($backupDir, $retention);

        $this->info("Done. Kept last {$retention} backups.");

        return self::SUCCESS;
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
            $this->line("  Pruned old backup: {$file->getFilename()}");
        }
    }
}
