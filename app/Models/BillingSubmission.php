<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingSubmission extends Model
{
    protected $fillable = [
        'billing_document_id',
        'provider_slug',
        'idempotency_key',
        'request_payload',
        'response_payload',
        'external_id',
        'status',
        'accepted_at',
        'last_checked_at',
        'error_code',
        'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'accepted_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function billingDocument(): BelongsTo
    {
        return $this->belongsTo(BillingDocument::class);
    }
}

