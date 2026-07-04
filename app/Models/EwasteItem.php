<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EwasteItem extends Model
{
    protected $fillable = [
        'name',
        'ai_class_name',
        'category_name',
        'avg_weight_kg',
        'min_weight_kg',
        'max_weight_kg',
        'price_per_kg',
        'price_per_item',
        'is_batch',
        'is_hazardous',
        'requires_staff_verification',
        'status',
        'notes',
    ];

    protected $casts = [
        'avg_weight_kg' => 'float',
        'min_weight_kg' => 'float',
        'max_weight_kg' => 'float',
        'price_per_kg' => 'float',
        'price_per_item' => 'float',
        'is_batch' => 'boolean',
        'is_hazardous' => 'boolean',
        'requires_staff_verification' => 'boolean',
    ];
}
