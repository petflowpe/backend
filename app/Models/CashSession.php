<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Concerns\BelongsToCompany;
class CashSession extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'branch_id',
        'vehicle_id',
        'user_id',
        'opening_amount',
        'closing_amount',
        'opened_at',
        'closed_at',
        'status',
        'expected_cash',
        'difference',
        'notes',
    ];

    protected $casts = [
        'opening_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}


