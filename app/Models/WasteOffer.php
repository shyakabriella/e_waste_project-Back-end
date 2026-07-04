<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WasteOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'waste_listing_id',
        'offered_by',
        'offered_to',
        'offer_amount',
        'currency',
        'offer_type',
        'status',
        'responded_by',
        'responded_at',
        'message',
        'response_note',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'offer_amount' => 'decimal:2',
            'responded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function wasteListing(): BelongsTo
    {
        return $this->belongsTo(WasteListing::class, 'waste_listing_id');
    }

    public function offeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'offered_by');
    }

    public function offeredTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'offered_to');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}