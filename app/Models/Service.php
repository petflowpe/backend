<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;

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
}
