<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Concerns\BelongsToCompany;
class Zone extends Model
{
    use BelongsToCompany;

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

    public function coverageRules(): HasMany
    {
        return $this->hasMany(VehicleCoverageRule::class, 'zone_id');
    }
}
