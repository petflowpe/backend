<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBatch extends Model
{
    use BelongsToCompany;

    public const NO_BATCH = 'SIN-LOTE';

    protected $fillable = [
        'company_id',
        'product_id',
        'area_id',
        'batch_number',
        'expiry_date',
        'quantity_initial',
        'quantity_available',
        'unit_cost',
        'received_at',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity_initial' => 'decimal:3',
        'quantity_available' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'received_at' => 'datetime',
    ];

    protected $appends = ['expiry_status', 'days_to_expiry'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(now()->startOfDay());
    }

    public function getDaysToExpiryAttribute(): ?int
    {
        return $this->expiry_date ? (int) now()->startOfDay()->diffInDays($this->expiry_date, false) : null;
    }

    /** expired | expiring (≤30 días) | ok | none (sin fecha) */
    public function getExpiryStatusAttribute(): string
    {
        $days = $this->days_to_expiry;
        if ($days === null) {
            return 'none';
        }

        return $days < 0 ? 'expired' : ($days <= 30 ? 'expiring' : 'ok');
    }
}
