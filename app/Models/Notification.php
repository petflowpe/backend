<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'type',
        'priority',
        'category',
        'title',
        'message',
        'read',
        'action_required',
        'related_module',
        'related_id',
        'data'
    ];

    protected $casts = [
        'read' => 'boolean',
        'action_required' => 'boolean',
        'data' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
