<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'company_id',
        'quantity_sold',
        'total_revenue',
        'total_cost',
        'last_sale_date',
        'sale_count',
    ];

    protected $casts = [
        'quantity_sold' => 'decimal:3',
        'total_revenue' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'last_sale_date' => 'date',
        'sale_count' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getProfitAttribute(): float
    {
        return $this->total_revenue - $this->total_cost;
    }

    public function getProfitMarginAttribute(): float
    {
        if ($this->total_revenue == 0) {
            return 0;
        }
        return (($this->total_revenue - $this->total_cost) / $this->total_revenue) * 100;
    }
}

