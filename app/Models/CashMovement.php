<?php

namespace App\Models;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'user_id',
        'cash_session_id',
        'type',
        'amount',
        'description',
        'payment_method',
        'reference',
        'movement_date',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'movement_date' => 'datetime',
        'metadata' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cashSession()
    {
        return $this->belongsTo(CashSession::class);
    }
}
