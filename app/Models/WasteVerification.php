<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WasteVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'waste_listing_id',
        'verified_by',
        'waste_ai_analysis_id',
        'confirmed_category_id',
        'client_estimated_weight_kg',
        'ai_estimated_weight_kg',
        'verified_weight_kg',
        'client_quantity',
        'verified_quantity',
        'condition_status',
        'is_accepted',
        'is_hazardous',
        'status',
        'price_per_kg',
        'price_per_item',
        'verified_total_price',
        'currency',
        'verification_notes',
        'rejection_reason',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'client_estimated_weight_kg' => 'decimal:2',
            'ai_estimated_weight_kg' => 'decimal:2',
            'verified_weight_kg' => 'decimal:2',
            'client_quantity' => 'integer',
            'verified_quantity' => 'integer',
            'is_accepted' => 'boolean',
            'is_hazardous' => 'boolean',
            'price_per_kg' => 'decimal:2',
            'price_per_item' => 'decimal:2',
            'verified_total_price' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function wasteListing(): BelongsTo
    {
        return $this->belongsTo(WasteListing::class, 'waste_listing_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function aiAnalysis(): BelongsTo
    {
        return $this->belongsTo(WasteAiAnalysis::class, 'waste_ai_analysis_id');
    }

    public function confirmedCategory(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'confirmed_category_id');
    }
}