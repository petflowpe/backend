<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'pet_id',
        'company_id',
        'branch_id',
        'vehicle_id',
        'user_id',
        'service_id',
        'service_type',
        'service_name',
        'service_category',
        'date',
        'time',
        'duration',
        'address',
        'district',
        'province',
        'department',
        'latitude',
        'longitude',
        'status',
        'price',
        'discount',
        'total',
        'payment_status',
        'payment_method',
        'notes',
        'cancellation_reason',
        'client_category',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        // Recurrencia
        'is_recurring',
        'recurrence_series_id',
        'recurrence_type',
        'recurrence_occurrences',
        'recurrence_days',
        'recurrence_fixed_time',
        'parent_appointment_id',
        // Notificaciones
        'reminder_sent',
        'reminder_sent_at',
        'confirmation_sent',
        'confirmation_sent_at',
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'duration' => 'integer',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'is_recurring' => 'boolean',
        'recurrence_fixed_time' => 'boolean',
        'recurrence_days' => 'array',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
        'confirmation_sent' => 'boolean',
        'confirmation_sent_at' => 'datetime',
        'service_id' => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AppointmentItem::class);
    }

    public function parentAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'parent_appointment_id');
    }

    public function childAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'parent_appointment_id');
    }

    public function seriesAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'recurrence_series_id', 'recurrence_series_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pendiente');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'Confirmada');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'Completada');
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
