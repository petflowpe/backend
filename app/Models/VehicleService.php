<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleService extends Model
{
    use HasFactory;

    protected $table = 'vehicle_services';

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'type',
        'description',
        'due_date',
        'priority',
        'estimated_cost',
        'status',
        'completed_at',
        'completed_by',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'estimated_cost' => 'decimal:2',
        'completed_at' => 'datetime',
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

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}

