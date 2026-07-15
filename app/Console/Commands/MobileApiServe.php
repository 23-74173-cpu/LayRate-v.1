<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class MobileApiServe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mobile-api:serve
                            {--host=0.0.0.0 : The host address to serve the mobile API on}
                            {--port=8000 : The port to serve the mobile API on}
                            {--no-install : Skip installing Python dependencies if missing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the LayRate mobile API Flask server';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $host = $this->option('host');
        $port = (int) $this->option('port');
        $projectPath = base_path('mobile-api');
        $appPath = $projectPath . DIRECTORY_SEPARATOR . 'app.py';

        if (!is_dir($projectPath)) {
            $this->error('mobile-api directory not found at: ' . $projectPath);
            return self::FAILURE;
        }

        if (!file_exists($appPath)) {
            $this->error('mobile-api app.py not found at: ' . $appPath);
            return self::FAILURE;
        }

        $pythonBinary = $this->resolvePythonBinary($projectPath);

        if (!$this->option('no-install')) {
            $this->ensureDependencies($projectPath, $pythonBinary);
        }

        $this->info("Starting LayRate mobile API on http://{$host}:{$port}");
        $this->info("Press Ctrl+C to stop.");

        $env = [
            'FLASK_HOST' => $host,
            'FLASK_PORT' => (string) $port,
            'FLASK_DEBUG' => '0',
            'PYTHONUNBUFFERED' => '1',
        ];

        // Preserve Windows system variables required by Python subprocesses.
        foreach (['SYSTEMROOT', 'SYSTEMDRIVE', 'WINDIR', 'PATH', 'USERPROFILE', 'TEMP', 'TMP'] as $key) {
            $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key) ?? null;
            if ($value !== null && $value !== '') {
                $env[$key] = $value;
            }
        }

        $command = [$pythonBinary, $appPath];
        $process = new Process($command, $projectPath, $env, null, null);
        $process->setTimeout(null);
        $process->start();

        foreach ($process as $type => $data) {
            $lines = explode("\n", rtrim($data, "\n"));
            foreach ($lines as $line) {
                if ($line === '') {
                    continue;
                }
                if ($type === $process::ERR) {
                    $this->warn($line);
                } else {
                    $this->line($line);
                }
            }
        }

        $exitCode = $process->getExitCode() ?? self::FAILURE;

        if ($exitCode !== 0) {
            $this->error('Mobile API server stopped unexpectedly.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Resolve the Python interpreter to use.
     *
     * Prefers the project virtual environment, then falls back to system python.
     */
    private function resolvePythonBinary(string $projectPath): string
    {
        $candidates = [
            $projectPath . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe',
            $projectPath . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python',
            $projectPath . DIRECTORY_SEPARATOR . 'venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe',
            $projectPath . DIRECTORY_SEPARATOR . 'venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        $configured = config('services.mobile_api.python_binary', env('MOBILE_API_PYTHON_BINARY', 'python3'));

        if (str_contains($configured, DIRECTORY_SEPARATOR) && file_exists($configured)) {
            return $configured;
        }

        return $configured;
    }

    /**
     * Ensure Python dependencies are installed in the project virtual environment.
     */
    private function ensureDependencies(string $projectPath, string $pythonBinary): void
    {
        try {
            $check = new Process([$pythonBinary, '-c', 'import flask, flask_cors, bcrypt'], $projectPath);
            $check->setTimeout(30);
            $check->run();

            if ($check->isSuccessful()) {
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('Mobile API dependency check failed', ['error' => $e->getMessage()]);
        }

        $this->warn('Mobile API Python dependencies missing. Installing...');

        // Create a virtual environment if one does not exist.
        $venvPath = $projectPath . DIRECTORY_SEPARATOR . '.venv';
        if (!is_dir($venvPath)) {
            $this->info('Creating Python virtual environment...');
            $createVenv = new Process([$this->findSystemPython(), '-m', 'venv', '.venv'], $projectPath);
            $createVenv->setTimeout(120);
            $createVenv->run();

            if (!$createVenv->isSuccessful()) {
                throw new RuntimeException('Failed to create virtual environment: ' . $createVenv->getErrorOutput());
            }

            // Re-resolve python binary after creating venv.
            $pythonBinary = $this->resolvePythonBinary($projectPath);
        }

        $install = new Process([$pythonBinary, '-m', 'pip', 'install', '-r', 'requirements.txt'], $projectPath);
        $install->setTimeout(300);
        $install->run();

        if (!$install->isSuccessful()) {
            throw new RuntimeException('Failed to install mobile-api dependencies: ' . $install->getErrorOutput());
        }

        $this->info('Mobile API dependencies installed.');
    }

    /**
     * Find a system Python interpreter for creating the virtual environment.
     */
    private function findSystemPython(): string
    {
        foreach (['python3', 'python'] as $cmd) {
            $check = new Process([$cmd, '--version']);
            $check->run();
            if ($check->isSuccessful()) {
                return $cmd;
            }
        }

        throw new RuntimeException('No system python or python3 command found.');
    }
}
