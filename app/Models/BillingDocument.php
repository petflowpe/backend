<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingDocument extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'client_id',
        'document_type',
        'issue_datetime',
        'currency_code',
        'number_prefix',
        'number',
        'totals',
        'tax_breakdown',
        'payload_snapshot',
        'status',
        'status_fiscal',
    ];

    protected $casts = [
        'issue_datetime' => 'datetime',
        'totals' => 'array',
        'tax_breakdown' => 'array',
        'payload_snapshot' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillingDocumentLine::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(BillingSubmission::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(BillingArtifact::class);
    }
}

