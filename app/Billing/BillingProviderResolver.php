<?php

namespace App\Billing;

use App\Billing\Contracts\BillingProvider;
use App\Billing\Providers\CO\DianStubProvider;
use App\Models\CompanyTaxProfile;

class BillingProviderResolver
{
    public function resolve(CompanyTaxProfile $profile): BillingProvider
    {
        // MVP: CO DIAN stub. A futuro: resolver por country_code + provider_slug.
        return match (strtolower($profile->provider_slug ?? 'dian_stub')) {
            'dian_stub' => new DianStubProvider(),
            default => new DianStubProvider(),
        };
    }
}

