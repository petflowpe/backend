<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database
                            {--keep=14 : Días a conservar}';

    protected $description = 'Dump MySQL/MariaDB a storage/app/backups (si el driver es mysql)';

    public function handle(): int
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'mysql') {
            $this->warn("Driver actual es '{$driver}'; backup:database solo soporta mysql.");

            return self::SUCCESS;
        }

        $host = (string) config("database.connections.{$connection}.host", '127.0.0.1');
        $port = (string) config("database.connections.{$connection}.port", '3306');
        $database = (string) config("database.connections.{$connection}.database");
        $username = (string) config("database.connections.{$connection}.username");
        $password = (string) config("database.connections.{$connection}.password");

        if ($database === '') {
            $this->error('DB_DATABASE vacío.');

            return self::FAILURE;
        }

        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $stamp = now()->format('Ymd_His');
        $outFile = $backupDir . DIRECTORY_SEPARATOR . "db_{$stamp}.sql";

        $mysqldump = $this->resolveMysqldump();
        if ($mysqldump === null) {
            $this->error('mysqldump no encontrado en PATH.');

            return self::FAILURE;
        }

        $args = [
            $mysqldump,
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $username,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--result-file=' . $outFile,
            $database,
        ];

        $process = new Process($args);
        $process->setTimeout(600);
        if ($password !== '') {
            $process->setEnv(['MYSQL_PWD' => $password]);
        }
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('mysqldump falló: ' . $process->getErrorOutput());
            if (is_file($outFile)) {
                @unlink($outFile);
            }

            return self::FAILURE;
        }

        $this->info('Backup DB: ' . $outFile);

        $keepDays = max(1, (int) $this->option('keep'));
        $cutoff = now()->subDays($keepDays)->getTimestamp();
        foreach (File::files($backupDir) as $old) {
            if (str_starts_with($old->getFilename(), 'db_') && $old->getMTime() < $cutoff) {
                File::delete($old->getPathname());
                $this->line('Eliminado: ' . $old->getFilename());
            }
        }

        return self::SUCCESS;
    }

    private function resolveMysqldump(): ?string
    {
        $candidates = ['mysqldump', 'mysqldump.exe'];
        foreach ($candidates as $bin) {
            $process = Process::fromShellCommandline(
                PHP_OS_FAMILY === 'Windows' ? "where {$bin}" : "command -v {$bin}"
            );
            $process->run();
            if ($process->isSuccessful()) {
                $line = trim(explode("\n", $process->getOutput())[0] ?? '');
                if ($line !== '') {
                    return $line;
                }
            }
        }

        return null;
    }
}
