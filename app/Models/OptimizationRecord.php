<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;
use App\Models\Vehicle;

class OptimizationRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'date',
        'appointments_count',
        'original_distance',
        'optimized_distance',
        'distance_saved',
        'time_saved',
        'fuel_saved',
        'efficiency',
        'cost_saved',
        'co2_saved',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
