<?php

namespace App\Billing\Providers\CO;

use App\Billing\Contracts\BillingProvider;
use App\Models\BillingDocument;
use App\Models\CompanyTaxProfile;
use Illuminate\Support\Str;

class DianStubProvider implements BillingProvider
{
    /**
     * Modo:
     * - accepted: simula aceptación inmediata
     * - rejected: simula rechazo
     * - sent: simula enviado (pendiente de aceptación)
     */
    private function mode(): string
    {
        return strtolower((string) env('DIAN_STUB_MODE', 'accepted'));
    }

    public function submit(BillingDocument $document, CompanyTaxProfile $profile, string $idempotencyKey): array
    {
        $externalId = 'DIAN-' . Str::upper(Str::random(12));
        $mode = $this->mode();

        return match ($mode) {
            'rejected' => [
                'externalId' => $externalId,
                'status' => 'rejected',
                'message' => 'Documento rechazado (stub)',
                'providerResponse' => [
                    'code' => 'DIAN_STUB_REJECTED',
                ],
            ],
            'sent' => [
                'externalId' => $externalId,
                'status' => 'sent',
                'message' => 'Documento enviado (stub)',
                'providerResponse' => [
                    'code' => 'DIAN_STUB_SENT',
                ],
            ],
            default => [
                'externalId' => $externalId,
                'status' => 'accepted',
                'message' => 'Documento aceptado (stub)',
                'providerResponse' => [
                    'code' => 'DIAN_STUB_ACCEPTED',
                ],
            ],
        };
    }

    public function checkStatus(string $externalId, CompanyTaxProfile $profile): array
    {
        // En stub, el estado depende del modo configurado.
        $mode = $this->mode();
        $status = match ($mode) {
            'rejected' => 'rejected',
            'sent' => 'sent',
            default => 'accepted',
        };

        return [
            'externalId' => $externalId,
            'status' => $status,
            'message' => 'Estado obtenido (stub)',
            'providerResponse' => [
                'code' => 'DIAN_STUB_STATUS',
            ],
        ];
    }
}

