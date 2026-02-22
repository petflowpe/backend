<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'color',
        'districts',
        'coverage',
        'demand',
        'coordinates',
        'active',
    ];

    protected $casts = [
        'districts' => 'array',
        'coordinates' => 'array',
        'active' => 'boolean',
        'demand' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class, 'zone_id');
    }
}
