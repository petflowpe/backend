<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingDocumentLine extends Model
{
    protected $fillable = [
        'billing_document_id',
        'item_type',
        'product_id',
        'description',
        'qty',
        'unit_price',
        'discount',
        'taxes',
        'line_total',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'taxes' => 'array',
    ];

    public function billingDocument(): BelongsTo
    {
        return $this->belongsTo(BillingDocument::class);
    }
}

