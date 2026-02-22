<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'pet_id',
        'client_id',
        'company_id',
        'appointment_id',
        'user_id',
        'date',
        'type',
        'title',
        'description',
        'diagnosis',
        'treatment',
        'prescription',
        'attachments',
        'weight',
        'temperature',
        'vital_signs',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'prescription' => 'array',
        'attachments' => 'array',
        'vital_signs' => 'array',
        'weight' => 'decimal:2',
        'temperature' => 'decimal:2',
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vaccineRecords(): HasMany
    {
        return $this->hasMany(VaccineRecord::class);
    }

    public function scopeByPet($query, $petId)
    {
        return $query->where('pet_id', $petId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
