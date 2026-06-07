<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\Concerns\BelongsToCompany;
class CompanyTaxProfile extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'country_code',
        'tax_id',
        'tax_id_dv',
        'legal_name',
        'trade_name',
        'email',
        'address_line',
        'city',
        'state',
        'postal_code',
        'currency_code_default',
        'locale_default',
        'environment',
        'provider_slug',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

