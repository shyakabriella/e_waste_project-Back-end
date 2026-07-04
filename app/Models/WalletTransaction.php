<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'waste_listing_id',
        'pickup_id',
        'transaction_reference',
        'type',
        'amount',
        'currency',
        'points',
        'balance_before',
        'balance_after',
        'points_before',
        'points_after',
        'payment_method',
        'payment_reference',
        'status',
        'title',
        'description',
        'admin_note',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'points' => 'integer',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'points_before' => 'integer',
            'points_after' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function wasteListing(): BelongsTo
    {
        return $this->belongsTo(WasteListing::class, 'waste_listing_id');
    }

    public function pickup(): BelongsTo
    {
        return $this->belongsTo(Pickup::class, 'pickup_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}