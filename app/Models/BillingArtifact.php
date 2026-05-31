<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingArtifact extends Model
{
    protected $fillable = [
        'billing_document_id',
        'billing_submission_id',
        'type',
        'path',
        'hash',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function billingDocument(): BelongsTo
    {
        return $this->belongsTo(BillingDocument::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(BillingSubmission::class, 'billing_submission_id');
    }
}

