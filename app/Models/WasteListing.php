<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WasteListing extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'waste_category_id',
        'title',
        'description',
        'quantity',
        'estimated_weight_kg',
        'ai_estimated_weight_kg',
        'verified_weight_kg',
        'ai_detected_item',
        'ai_detected_category',
        'ai_waste_nature',
        'ai_is_e_waste',
        'ai_confidence',
        'ai_analysis_note',
        'expected_price',
        'final_price',
        'currency',
        'pickup_address',
        'district',
        'sector',
        'cell',
        'village',
        'latitude',
        'longitude',
        'status',
        'verified_by',
        'verified_at',
        'verification_notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'estimated_weight_kg' => 'decimal:2',
            'ai_estimated_weight_kg' => 'decimal:2',
            'verified_weight_kg' => 'decimal:2',
            'ai_is_e_waste' => 'boolean',
            'ai_confidence' => 'decimal:2',
            'expected_price' => 'decimal:2',
            'final_price' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'verified_at' => 'datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(User::class, 'institution_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'waste_category_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(WastePhoto::class, 'waste_listing_id');
    }

    public function primaryPhoto(): HasOne
    {
        return $this->hasOne(WastePhoto::class, 'waste_listing_id')->where('is_primary', true);
    }

    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(WasteAiAnalysis::class, 'waste_listing_id');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(WasteVerification::class, 'waste_listing_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(WasteOffer::class, 'waste_listing_id');
    }

    public function acceptedOffer(): HasOne
    {
        return $this->hasOne(WasteOffer::class, 'waste_listing_id')->where('status', 'accepted');
    }

    public function pickups(): HasMany
    {
        return $this->hasMany(Pickup::class, 'waste_listing_id');
    }

    public function latestPickup(): HasOne
    {
        return $this->hasOne(Pickup::class, 'waste_listing_id')->latestOfMany();
    }

    public function qrTags(): HasMany
    {
        return $this->hasMany(QrTag::class, 'waste_listing_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'waste_listing_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'waste_listing_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'waste_listing_id');
    }
}