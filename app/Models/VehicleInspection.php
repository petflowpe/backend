<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Concerns\BelongsToCompany;
class VehicleInspection extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'template_id',
        'inspected_at',
        'odometer',
        'driver_name',
        'supervisor_name',
        'compliance_percent',
        'status',
        'observations',
        'driver_signature',
        'supervisor_signature',
        'created_by',
    ];

    protected $casts = [
        'inspected_at' => 'datetime',
        'compliance_percent' => 'decimal:2',
        'odometer' => 'integer',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(VehicleInspectionTemplate::class, 'template_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(VehicleInspectionResult::class, 'inspection_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(VehicleInspectionAttachment::class, 'inspection_id');
    }
}
