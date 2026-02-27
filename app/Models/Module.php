<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'active',
        'order',
        'config',
    ];

    protected $casts = [
        'active' => 'boolean',
        'order' => 'integer',
        'config' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
