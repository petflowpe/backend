<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\Concerns\BelongsToCompany;
class VehicleMaintenance extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'vehicle_maintenances';

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'type',
        'status',
        'description',
        'date',
        'cost',
        'workshop_ruc',
        'workshop_name',
        'workshop_address',
        'workshop_phone',
        'next_due',
        'account_code',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'next_due' => 'date',
        'cost' => 'decimal:2',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

