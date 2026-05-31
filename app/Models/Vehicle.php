<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'driver_id',
        'driver_name',
        'name',
        'type',
        'placa',
        'marca',
        'modelo',
        'vin',
        'zona_operacion',
        'anio',
        'kilometraje',
        'nivel_combustible',
        'eficiencia',
        'color',
        'capacidad_slots',
        'capacidad_por_categoria',
        'zonas_asignadas',
        'activo',
        'status_override',
        'horario_disponibilidad',
        'fecha_ultimo_mantenimiento',
        'fecha_proximo_mantenimiento',
        'fecha_seguro',
        'fecha_itv',
        'equipamiento',
        'notas_mantenimiento',
        'ultimo_cumplimiento_inspeccion',
        'fecha_ultima_inspeccion',
        'indice_chofer',
        'puntos_observacion_chofer',
        'observaciones_inspeccion_acumuladas',
        'current_latitude',
        'current_longitude',
        'last_location_update',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'capacidad_por_categoria' => 'array',
        'zonas_asignadas' => 'array',
        'horario_disponibilidad' => 'array',
        'equipamiento' => 'array',
        'fecha_ultimo_mantenimiento' => 'date',
        'fecha_proximo_mantenimiento' => 'date',
        'fecha_seguro' => 'date',
        'fecha_itv' => 'date',
        'ultimo_cumplimiento_inspeccion' => 'decimal:1',
        'fecha_ultima_inspeccion' => 'datetime',
        'indice_chofer' => 'integer',
        'puntos_observacion_chofer' => 'integer',
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'last_location_update' => 'datetime',
        'capacidad_slots' => 'integer',
        'anio' => 'integer',
        'kilometraje' => 'integer',
        'nivel_combustible' => 'integer',
        'eficiencia' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class, 'vehicle_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(VehicleExpense::class, 'vehicle_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(VehicleService::class, 'vehicle_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(VehicleInspection::class, 'vehicle_id');
    }

    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
