<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'business_name',
        'document_type',
        'document_number',
        'supplier_type',
        'specialty',
        'professional_license',
        'clinic_name',
        'fee_rate',
        'email',
        'phone',
        'contact_name',
        'bank_name',
        'bank_account',
        'billing_email',
        'credit_days',
        'accounting_account_code',
        'address',
        'notes',
        'logo',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
        'credit_days' => 'integer',
        'fee_rate' => 'decimal:2',
    ];

    public function isExternalDoctor(): bool
    {
        $type = (string) ($this->supplier_type ?? '');

        return in_array($type, ['Médico Externo', 'Honorarios'], true);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}

