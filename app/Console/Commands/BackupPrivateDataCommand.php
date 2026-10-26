<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupPrivateDataCommand extends Command
{
    protected $signature = 'backup:private-data
                            {--keep=14 : Días a conservar}';

    protected $description = 'Copia storage/app/private (XML/CDR/certificados) a storage/app/backups';

    public function handle(): int
    {
        $source = storage_path('app/private');
        if (! is_dir($source)) {
            $this->warn('No existe storage/app/private; nada que respaldar.');

            return self::SUCCESS;
        }

        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $stamp = now()->format('Ymd_His');
        $zipPath = $backupDir . DIRECTORY_SEPARATOR . "private_{$stamp}.zip";

        if (! class_exists(ZipArchive::class)) {
            $this->error('Extensión ZipArchive no disponible.');

            return self::FAILURE;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('No se pudo crear el ZIP.');

            return self::FAILURE;
        }

        $files = File::allFiles($source);
        foreach ($files as $file) {
            $relative = ltrim(str_replace($source, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $zip->addFile($file->getPathname(), str_replace('\\', '/', $relative));
        }
        $zip->close();

        $this->info('Backup creado: ' . $zipPath);

        $keepDays = max(1, (int) $this->option('keep'));
        $cutoff = now()->subDays($keepDays)->getTimestamp();
        foreach (File::files($backupDir) as $old) {
            if (str_starts_with($old->getFilename(), 'private_') && $old->getMTime() < $cutoff) {
                File::delete($old->getPathname());
                $this->line('Eliminado: ' . $old->getFilename());
            }
        }

        // Marca en disco local para monitoreo
        Storage::disk('local')->put('backups/.last_private_backup', $stamp);

        return self::SUCCESS;
    }
}
