<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\CompanyCertificateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Checklist rápido PE antes de abrir / tras deploy.
 */
class PeProductionHealthCommand extends Command
{
    protected $signature = 'pe:health {--company= : ID de empresa a inspeccionar}';

    protected $description = 'Verifica readiness producción PE (env, migrate, SUNAT endpoints, certificado)';

    public function handle(CompanyCertificateService $certificates): int
    {
        $ok = true;
        $this->info('=== PetFlow PE health ===');

        $checks = [
            'APP_ENV=production' => app()->environment('production'),
            'APP_DEBUG=false' => config('app.debug') === false,
            'APP_KEY set' => filled(config('app.key')),
            'DB conectada' => $this->dbOk(),
            'storage writable' => is_writable(storage_path()),
            'private disk writable' => is_writable(storage_path('app/private')) || @mkdir(storage_path('app/private'), 0775, true),
        ];

        foreach ($checks as $label => $pass) {
            $this->line(($pass ? '[OK] ' : '[FAIL] ') . $label);
            $ok = $ok && (bool) $pass;
        }

        try {
            Artisan::call('migrate:status');
            $statusOut = Artisan::output();
            $pending = str_contains($statusOut, 'Pending');
            $this->line(($pending ? '[FAIL] ' : '[OK] ') . 'Migraciones ' . ($pending ? 'con pendientes' : 'al día'));
            $ok = $ok && ! $pending;
        } catch (\Throwable $e) {
            $this->line('[WARN] migrate:status: ' . $e->getMessage());
        }

        $companyId = $this->option('company') ? (int) $this->option('company') : null;
        $company = $companyId
            ? Company::query()->find($companyId)
            : Company::query()->where('activo', true)->orderBy('id')->first();

        if (! $company) {
            $this->line('[FAIL] No hay empresa activa');
            $ok = false;
        } else {
            $this->line("[INFO] Empresa #{$company->id} {$company->razon_social} slug=" . ($company->booking_slug ?: '(vacío)'));
            $endpoint = $company->getInvoiceEndpoint();
            $this->line(($endpoint !== '' ? '[OK] ' : '[FAIL] ') . 'Invoice endpoint: ' . ($endpoint ?: '(vacío)'));
            $ok = $ok && $endpoint !== '';

            $hasCert = $certificates->exists($company);
            $this->line(($hasCert ? '[OK] ' : '[WARN] ') . 'Certificado digital ' . ($hasCert ? 'presente' : 'ausente'));

            $sol = strtoupper(trim((string) $company->usuario_sol));
            if ($company->modo_produccion && in_array($sol, ['DEMO', 'MODDATOS', ''], true)) {
                $this->line('[FAIL] modo_produccion=true pero usuario_sol parece demo');
                $ok = false;
            } else {
                $this->line('[OK] Credenciales SOL coherentes con el modo (o empresa en beta)');
            }
        }

        $this->line('[INFO] Schedule: sunat:poll-tickets · backup:private-data · backup:database');
        $this->line('[INFO] Cron: * * * * * cd /path && php artisan schedule:run');

        $this->newLine();
        if ($ok) {
            $this->info('RESULTADO: listo para PE (revisar WARN de certificado si aplica).');

            return self::SUCCESS;
        }

        $this->error('RESULTADO: hay fallos P0. Corregir antes de abrir.');

        return self::FAILURE;
    }

    private function dbOk(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
