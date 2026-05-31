<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleInspectionTemplateCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'name',
        'sort_order',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(VehicleInspectionTemplate::class, 'template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VehicleInspectionTemplateItem::class, 'category_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
