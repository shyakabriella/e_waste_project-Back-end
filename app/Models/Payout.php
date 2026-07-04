<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'waste_listing_id',
        'pickup_id',
        'wallet_transaction_id',
        'payout_reference',
        'gross_amount',
        'commission_amount',
        'net_amount',
        'currency',
        'points',
        'payment_method',
        'payment_reference',
        'account_name',
        'account_number',
        'bank_name',
        'mobile_money_phone',
        'status',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
        'failure_reason',
        'cancellation_reason',
        'description',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'points' => 'integer',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
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

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}