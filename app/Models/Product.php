<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'service_id',
        'category_id',
        'brand_id',
        'supplier_id',
        'area_id',
        'code',
        'sku',
        'name',
        'description',
        'item_type',
        'unit',
        'currency',
        'cost_price',
        'unit_price',
        'stock',
        'min_stock',
        'max_stock',
        'tax_affection',
        'igv_rate',
        'isc_rate',
        'icbper_rate',
        'active',
        'metadata',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'igv_rate' => 'decimal:2',
        'stock' => 'decimal:3',
        'min_stock' => 'decimal:3',
        'max_stock' => 'decimal:3',
        'rating' => 'decimal:2',
        'sold_count' => 'integer',
        'last_restocked_at' => 'date',
        'active' => 'boolean',
        'metadata' => 'array',
        'images' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unitRelation(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function brandRelation(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function supplierRelation(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function productSale(): HasOne
    {
        return $this->hasOne(ProductSale::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getMarginAttribute(): float
    {
        if ($this->unit_price == 0) {
            return 0;
        }
        return (($this->unit_price - ($this->cost_price ?? 0)) / $this->unit_price) * 100;
    }

    public function getProfitAttribute(): float
    {
        return $this->unit_price - ($this->cost_price ?? 0);
    }

    public function isLowStock(): bool
    {
        if ($this->min_stock === null) {
            return false;
        }
        return $this->stock <= $this->min_stock;
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock', '<=', 'min_stock')
            ->whereNotNull('min_stock');
    }
}


