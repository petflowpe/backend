<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleCoverageRule extends Model
{
    protected $fillable = [
        'company_id',
        'vehicle_id',
        'zone_id',
        'districts',
        'days',
        'start_time',
        'end_time',
        'priority',
        'max_daily_appointments',
        'active',
        'notes',
    ];

    protected $casts = [
        'districts' => 'array',
        'days' => 'array',
        'active' => 'boolean',
        'priority' => 'integer',
        'max_daily_appointments' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
