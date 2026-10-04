<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Concerns\BelongsToCompany;
class Service extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'category',
        'area',
        'active',
        'pricing_by_size',
        'pricing',
        'breed_exceptions',
        'required_products',
    ];

    protected $casts = [
        'active' => 'boolean',
        'pricing_by_size' => 'boolean',
        'pricing' => 'array',
        'breed_exceptions' => 'array',
        'required_products' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'service_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
