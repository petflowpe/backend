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
        'anio',
        'color',
        'capacidad_slots',
        'capacidad_por_categoria',
        'zonas_asignadas',
        'activo',
        'horario_disponibilidad',
        'fecha_ultimo_mantenimiento',
        'fecha_proximo_mantenimiento',
        'equipamiento',
        'notas_mantenimiento',
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
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'last_location_update' => 'datetime',
        'capacidad_slots' => 'integer',
        'anio' => 'integer',
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

    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
