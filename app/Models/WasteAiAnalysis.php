<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WasteAiAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'waste_listing_id',
        'waste_photo_id',
        'ai_provider',
        'ai_model',
        'detected_item',
        'detected_category_id',
        'detected_category_name',
        'waste_nature',
        'is_e_waste',
        'is_hazardous',
        'quantity_detected',
        'estimated_weight_kg',
        'min_estimated_weight_kg',
        'max_estimated_weight_kg',
        'confidence',
        'analysis_note',
        'analysis_result',
        'status',
        'error_message',
        'analyzed_at',
        'verified_by_staff',
        'verified_by',
        'verified_at',
        'staff_corrected_category_id',
        'staff_corrected_weight_kg',
        'staff_feedback',
    ];

    protected function casts(): array
    {
        return [
            'is_e_waste' => 'boolean',
            'is_hazardous' => 'boolean',
            'quantity_detected' => 'integer',
            'estimated_weight_kg' => 'decimal:2',
            'min_estimated_weight_kg' => 'decimal:2',
            'max_estimated_weight_kg' => 'decimal:2',
            'confidence' => 'decimal:2',
            'analysis_result' => 'array',
            'analyzed_at' => 'datetime',
            'verified_by_staff' => 'boolean',
            'verified_at' => 'datetime',
            'staff_corrected_weight_kg' => 'decimal:2',
        ];
    }

    public function wasteListing(): BelongsTo
    {
        return $this->belongsTo(WasteListing::class, 'waste_listing_id');
    }

    public function wastePhoto(): BelongsTo
    {
        return $this->belongsTo(WastePhoto::class, 'waste_photo_id');
    }

    public function detectedCategory(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'detected_category_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function staffCorrectedCategory(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'staff_corrected_category_id');
    }
}