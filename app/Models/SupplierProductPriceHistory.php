<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProductPriceHistory extends Model
{
    protected $table = 'supplier_product_price_history';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'product_id',
        'purchase_order_id',
        'unit_cost',
        'previous_unit_cost',
        'variation_percent',
        'price_alert',
        'recorded_at',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:4',
        'previous_unit_cost' => 'decimal:4',
        'variation_percent' => 'decimal:2',
        'price_alert' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
