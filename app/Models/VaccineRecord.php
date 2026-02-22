<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaccineRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'pet_id',
        'client_id',
        'company_id',
        'medical_record_id',
        'user_id',
        'name',
        'date',
        'next_due_date',
        'veterinarian',
        'lot',
        'manufacturer',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'next_due_date' => 'date',
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

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('next_due_date', '>=', now())
                    ->where('next_due_date', '<=', now()->addDays(30));
    }

    public function scopeOverdue($query)
    {
        return $query->where('next_due_date', '<', now());
    }
}
