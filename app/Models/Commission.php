<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    use HasFactory;

    protected $fillable = [
        'waste_listing_id',
        'pickup_id',
        'payout_id',
        'institution_id',
        'commission_reference',
        'gross_amount',
        'commission_rate',
        'commission_amount',
        'institution_amount',
        'currency',
        'commission_type',
        'status',
        'confirmed_by',
        'confirmed_at',
        'description',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'institution_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    public function wasteListing(): BelongsTo
    {
        return $this->belongsTo(WasteListing::class, 'waste_listing_id');
    }

    public function pickup(): BelongsTo
    {
        return $this->belongsTo(Pickup::class, 'pickup_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class, 'payout_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(User::class, 'institution_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}