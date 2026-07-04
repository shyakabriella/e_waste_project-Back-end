<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WasteCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'waste_nature',
        'is_e_waste',
        'is_hazardous',
        'average_weight_kg',
        'min_weight_kg',
        'max_weight_kg',
        'price_per_kg',
        'price_per_item',
        'currency',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_e_waste' => 'boolean',
            'is_hazardous' => 'boolean',
            'average_weight_kg' => 'decimal:2',
            'min_weight_kg' => 'decimal:2',
            'max_weight_kg' => 'decimal:2',
            'price_per_kg' => 'decimal:2',
            'price_per_item' => 'decimal:2',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function wasteListings(): HasMany
    {
        return $this->hasMany(WasteListing::class, 'waste_category_id');
    }

    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(WasteAiAnalysis::class, 'detected_category_id');
    }

    public function staffCorrectedAiAnalyses(): HasMany
    {
        return $this->hasMany(WasteAiAnalysis::class, 'staff_corrected_category_id');
    }

    public function wasteVerifications(): HasMany
    {
        return $this->hasMany(WasteVerification::class, 'confirmed_category_id');
    }
}