<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleInspectionResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'inspection_id',
        'template_item_id',
        'category_name',
        'item_label',
        'passed',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'passed' => 'boolean',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(VehicleInspection::class, 'inspection_id');
    }
}
