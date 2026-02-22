<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pet extends Model
{
    public const MAX_PHOTOS = 5;
    use HasFactory;

    protected $fillable = [
        'client_id',
        'company_id',
        'name',
        'last_name',
        'species',
        'breed',
        'age',
        'weight',
        'size',
        'gender',
        'color',
        'microchip',
        'identification_type',
        'identification_number',
        'temperament',
        'behavior',
        'allergies',
        'medications',
        'photo',
        'fallecido',
        'sterilized',
        'sterilization_date',
        'birth_date',
        'last_vaccination_date',
        'next_vaccination_date',
        'last_deworming_date',
        'next_deworming_date',
        'insurance_company',
        'insurance_policy_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'fecha_registro',
        'fecha_ultima_visita',
        'notes',
    ];

    protected $casts = [
        'fallecido' => 'boolean',
        'sterilized' => 'boolean',
        'allergies' => 'array',
        'medications' => 'array',
        'behavior' => 'array',
        'birth_date' => 'date',
        'sterilization_date' => 'date',
        'last_vaccination_date' => 'date',
        'next_vaccination_date' => 'date',
        'last_deworming_date' => 'date',
        'next_deworming_date' => 'date',
        'fecha_registro' => 'date',
        'fecha_ultima_visita' => 'date',
        'age' => 'integer',
        'weight' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'pet_owners', 'pet_id', 'client_id')
            ->withTimestamps();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function vaccineRecords(): HasMany
    {
        return $this->hasMany(VaccineRecord::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PetPhoto::class)->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('fallecido', false);
    }

    public function scopeByClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }
}
