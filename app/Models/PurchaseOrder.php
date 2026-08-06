<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

use App\Models\Concerns\BelongsToCompany;

class PurchaseOrder extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'supplier_id',
        'default_area_id',
        'order_number',
        'order_date',
        'delivery_date',
        'status',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'total',
        'subtotal',
        'igv_rate',
        'igv_amount',
        'prices_include_igv',
        'invoice_number',
        'invoice_date',
        'invoice_total',
        'invoice_attachment_path',
        'invoice_attachment_name',
        'kardex_registered',
        'payment_status',
        'amount_paid',
        'paid_at',
        'notes',
        'cancelled_at',
        'cancellation_reason',
        'email_sent_at',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'delivery_date' => 'date',
        'invoice_date' => 'date',
        'paid_at' => 'datetime',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'igv_rate' => 'decimal:2',
        'igv_amount' => 'decimal:2',
        'invoice_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'kardex_registered' => 'boolean',
        'prices_include_igv' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payable(): HasOne
    {
        return $this->hasOne(PurchasePayable::class);
    }

    public function defaultArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'default_area_id');
    }

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
