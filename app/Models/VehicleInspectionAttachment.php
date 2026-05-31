<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class VehicleInspectionAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'inspection_id',
        'path',
        'data_url',
        'original_name',
        'mime_type',
        'size',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(VehicleInspection::class, 'inspection_id');
    }

    public function getUrlAttribute(): string
    {
        if (is_string($this->data_url) && str_starts_with($this->data_url, 'data:')) {
            return $this->data_url;
        }

        return Storage::disk('public')->url($this->path);
    }
}
