<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleInspectionTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'vehicle_type',
        'active',
        'created_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(VehicleInspectionTemplateCategory::class, 'template_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
