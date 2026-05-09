<?php

namespace App\Billing\Contracts;

use App\Models\BillingDocument;
use App\Models\CompanyTaxProfile;

interface BillingProvider
{
    /**
     * Envía el documento al proveedor/autoridad y retorna un resultado normalizado.
     *
     * Debe ser idempotente usando $idempotencyKey.
     */
    public function submit(BillingDocument $document, CompanyTaxProfile $profile, string $idempotencyKey): array;

    /**
     * Consulta el estado fiscal.
     */
    public function checkStatus(string $externalId, CompanyTaxProfile $profile): array;
}

