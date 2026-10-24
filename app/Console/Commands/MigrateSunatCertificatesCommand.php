<?php

namespace App\Console\Commands;

use App\Services\CompanyCertificateService;
use Illuminate\Console\Command;

class MigrateSunatCertificatesCommand extends Command
{
    protected $signature = 'sunat:migrate-certificates
                            {--company= : Solo una empresa}
                            {--dry-run : Contar sin mover archivos}';

    protected $description = 'Mueve certificados SUNAT del disco público al privado (una carpeta por empresa)';

    public function handle(CompanyCertificateService $certificates): int
    {
        $companyId = $this->option('company') ? (int) $this->option('company') : null;
        $dryRun = (bool) $this->option('dry-run');

        $stats = $certificates->migrateFromPublic($companyId, $dryRun);
        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}Migrados: {$stats['migrated']} · Omitidos: {$stats['skipped']} · Fallidos: {$stats['failed']}");

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
