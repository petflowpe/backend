<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Certificados SUNAT: disco privado, una carpeta por empresa.
 * Compatibilidad: si el archivo aún está en public o el campo guarda PEM crudo, se lee igual.
 */
class CompanyCertificateService
{
    private const DISK = 'local'; // storage/app/private

    public function relativePath(Company $company): string
    {
        return 'certificado/' . $company->id . '/certificado.pem';
    }

    public function store(Company $company, UploadedFile $file): string
    {
        $path = $this->relativePath($company);
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory(dirname($path));
        $disk->put($path, file_get_contents($file->getRealPath()));

        // Evitar que quede una copia pública descargable del mismo archivo.
        $this->deletePublicLegacy($company);

        return $path;
    }

    /**
     * Contenido PEM completo (clave + certificado). Lanza si no hay archivo usable.
     */
    public function contents(Company $company): string
    {
        $resolved = $this->resolvePath($company);
        if ($resolved) {
            $content = @file_get_contents($resolved);
            if ($content !== false && trim($content) !== '') {
                return $content;
            }
        }

        $raw = (string) ($company->certificado_pem ?? '');
        if ($this->looksLikePem($raw)) {
            return $raw;
        }

        throw new InvalidArgumentException(
            'No hay certificado digital configurado para la empresa #' . $company->id
            . ' (' . ($company->razon_social ?? '') . '). Suba el .pem en Configuración SUNAT.'
        );
    }

    public function exists(Company $company): bool
    {
        try {
            $this->contents($company);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Ruta absoluta del archivo PEM si existe en disco; null si solo hay PEM en BD.
     */
    public function absolutePath(Company $company): ?string
    {
        return $this->resolvePath($company);
    }

    /**
     * Mueve certificados legacy del disco público al privado.
     *
     * @return array{migrated: int, skipped: int, failed: int}
     */
    public function migrateFromPublic(?int $companyId = null, bool $dryRun = false): array
    {
        $stats = ['migrated' => 0, 'skipped' => 0, 'failed' => 0];
        $query = Company::query()->when($companyId, fn ($q) => $q->whereKey($companyId));

        foreach ($query->cursor() as $company) {
            try {
                $private = $this->relativePath($company);
                if (Storage::disk(self::DISK)->exists($private)) {
                    $stats['skipped']++;
                    continue;
                }

                $source = $this->findPublicSource($company);
                if (! $source) {
                    $stats['skipped']++;
                    continue;
                }

                if ($dryRun) {
                    $stats['migrated']++;
                    continue;
                }

                $content = file_get_contents($source);
                if ($content === false || trim($content) === '') {
                    $stats['failed']++;
                    continue;
                }

                Storage::disk(self::DISK)->makeDirectory(dirname($private));
                Storage::disk(self::DISK)->put($private, $content);
                $company->update(['certificado_pem' => $private]);
                @unlink($source);
                $stats['migrated']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::warning('No se pudo migrar certificado SUNAT', [
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    private function resolvePath(Company $company): ?string
    {
        $stored = (string) ($company->certificado_pem ?? '');
        $candidates = [];

        if ($stored !== '' && ! $this->looksLikePem($stored)) {
            $normalized = ltrim(str_replace('\\', '/', $stored), '/');
            $candidates[] = Storage::disk(self::DISK)->path($normalized);
            $candidates[] = Storage::disk('public')->path($normalized);
            if (str_starts_with($normalized, 'storage/app/')) {
                $candidates[] = storage_path(substr($normalized, strlen('storage/')));
            }
            if (is_file($stored)) {
                $candidates[] = $stored;
            }
        }

        $candidates[] = Storage::disk(self::DISK)->path($this->relativePath($company));
        $candidates[] = Storage::disk('public')->path('certificado/certificado.pem');
        $candidates[] = storage_path('app/public/certificado/certificado.pem');

        foreach (array_unique($candidates) as $path) {
            if (is_string($path) && is_file($path) && filesize($path) > 0) {
                return $path;
            }
        }

        return null;
    }

    private function findPublicSource(Company $company): ?string
    {
        $stored = (string) ($company->certificado_pem ?? '');
        $candidates = [];
        if ($stored !== '' && ! $this->looksLikePem($stored)) {
            $normalized = ltrim(str_replace('\\', '/', $stored), '/');
            $candidates[] = Storage::disk('public')->path($normalized);
        }
        $candidates[] = Storage::disk('public')->path('certificado/certificado.pem');
        $candidates[] = storage_path('app/public/certificado/certificado.pem');

        foreach (array_unique($candidates) as $path) {
            if (is_file($path) && filesize($path) > 0) {
                return $path;
            }
        }

        return null;
    }

    private function deletePublicLegacy(Company $company): void
    {
        $paths = [
            Storage::disk('public')->path('certificado/certificado.pem'),
            storage_path('app/public/certificado/certificado.pem'),
        ];
        $stored = (string) ($company->certificado_pem ?? '');
        if ($stored !== '' && ! $this->looksLikePem($stored)) {
            $paths[] = Storage::disk('public')->path(ltrim(str_replace('\\', '/', $stored), '/'));
        }
        foreach (array_unique($paths) as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function looksLikePem(string $value): bool
    {
        return str_contains($value, '-----BEGIN ') && str_contains($value, '-----END ');
    }
}
