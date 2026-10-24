<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\CompanyCertificateService;
use App\Services\GreenterService;
use Illuminate\Console\Command;

class ValidateCertificate extends Command
{
    protected $signature = 'sunat:validate-certificate {--company=1 : ID de la empresa}';
    protected $description = 'Valida el certificado digital configurado para SUNAT';

    public function handle(CompanyCertificateService $certificates): int
    {
        $companyId = $this->option('company');
        $company = Company::find($companyId);

        if (! $company) {
            $this->error("No se encontró la empresa con ID: {$companyId}");

            return self::FAILURE;
        }

        $this->info("Validando certificado para empresa: {$company->razon_social}");
        $this->info("RUC: {$company->ruc}");
        $this->line('Ruta: ' . ($certificates->absolutePath($company) ?? '(PEM en BD o no encontrado)'));

        try {
            $pem = $certificates->contents($company);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->validatePemStructure($pem);

        try {
            new GreenterService($company);
            $this->info('Certificado cargado correctamente en Greenter');
        } catch (\Exception $e) {
            $this->error('Error al cargar certificado en Greenter: ' . $e->getMessage());
            $this->suggestSolutions();

            return self::FAILURE;
        }

        $this->showCertificateInfo($pem);
        $this->info('Certificado válido y listo para usar con SUNAT');

        return self::SUCCESS;
    }

    private function validatePemStructure(string $pem): void
    {
        $this->info('Validando estructura PEM...');

        if (str_contains($pem, '-----BEGIN PRIVATE KEY-----') || str_contains($pem, '-----BEGIN RSA PRIVATE KEY-----')) {
            $this->info('  Clave privada encontrada');
        } else {
            $this->error('  Clave privada no encontrada');
        }

        if (str_contains($pem, '-----BEGIN CERTIFICATE-----')) {
            $this->info('  Certificado encontrado');
        } else {
            $this->error('  Certificado no encontrado');
        }

        if (str_contains($pem, "\r\n")) {
            $this->warn('  Detectados saltos de línea Windows (\\r\\n)');
        }
    }

    private function showCertificateInfo(string $pem): void
    {
        $this->info('Información del certificado:');

        if (! preg_match('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $pem, $matches)) {
            return;
        }

        $certData = base64_decode(preg_replace('/\s+/', '', $matches[1]));
        $certInfo = openssl_x509_parse($certData);
        if (! $certInfo) {
            return;
        }

        $this->info('  Válido desde: ' . date('Y-m-d H:i:s', $certInfo['validFrom_time_t']));
        $this->info('  Válido hasta: ' . date('Y-m-d H:i:s', $certInfo['validTo_time_t']));
        $this->info('  Emisor: ' . ($certInfo['issuer']['CN'] ?? 'N/A'));
        $this->info('  Sujeto: ' . ($certInfo['subject']['CN'] ?? 'N/A'));

        if (time() > $certInfo['validTo_time_t']) {
            $this->error('  CERTIFICADO VENCIDO');
        } else {
            $this->info('  Certificado vigente');
        }
    }

    private function suggestSolutions(): void
    {
        $this->warn("\nPosibles soluciones:");
        $this->warn('1. Verifica que el certificado esté en formato PEM correcto');
        $this->warn('2. Asegúrate que incluya tanto la clave privada como el certificado');
        $this->warn('3. Ejecuta: php artisan sunat:migrate-certificates');
        $this->warn('4. Si usas PKCS#12 (.pfx): openssl pkcs12 -in certificado.pfx -out certificado.pem -nodes');
    }
}
