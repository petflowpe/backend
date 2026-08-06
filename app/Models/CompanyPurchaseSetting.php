<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPurchaseSetting extends Model
{
    protected $fillable = [
        'company_id',
        'approval_threshold',
        'price_alert_percent',
        'delivery_alert_days',
        'default_igv_rate',
        'prices_include_igv',
    ];

    protected $casts = [
        'approval_threshold' => 'decimal:2',
        'price_alert_percent' => 'decimal:2',
        'delivery_alert_days' => 'integer',
        'default_igv_rate' => 'decimal:2',
        'prices_include_igv' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function forCompany(int $companyId): self
    {
        return static::firstOrCreate(
            ['company_id' => $companyId],
            [
                'approval_threshold' => 0,
                'price_alert_percent' => 10,
                'delivery_alert_days' => 2,
                'default_igv_rate' => 18,
                'prices_include_igv' => true,
            ]
        );
    }
}
