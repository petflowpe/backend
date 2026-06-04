<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientReview extends Model
{
    protected $fillable = [
        'company_id',
        'client_id',
        'appointment_id',
        'client_name',
        'pet_name',
        'rating',
        'comment',
        'service_name',
        'staff_name',
        'staff_response',
        'verified',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'rating' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
