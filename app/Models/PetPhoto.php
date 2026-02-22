<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PetPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'pet_id',
        'path',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        $url = Storage::disk('public')->url($this->path);
        if (str_starts_with($url, 'http')) {
            return $url;
        }
        return rtrim(config('app.url', ''), '/') . '/' . ltrim($url, '/');
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }
}
