<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    use BelongsToCompany;

    public const ACTIVE = 'active';
    public const CONSUMED = 'consumed';
    public const RELEASED = 'released';

    protected $fillable = [
        'company_id',
        'product_id',
        'area_id',
        'quantity',
        'source_type',
        'source_id',
        'status',
        'released_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'released_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
