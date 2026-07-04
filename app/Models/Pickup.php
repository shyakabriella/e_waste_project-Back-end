<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pickup extends Model
{
    use HasFactory;

    protected $fillable = [
        'waste_listing_id',
        'institution_id',
        'assigned_staff_id',
        'driver_id',
        'pickup_code',
        'pickup_date',
        'pickup_time',
        'scheduled_at',
        'pickup_address',
        'district',
        'sector',
        'cell',
        'village',
        'latitude',
        'longitude',
        'status',
        'started_at',
        'arrived_at',
        'collected_at',
        'completed_at',
        'cancelled_at',
        'institution_confirmed',
        'institution_confirmed_by',
        'institution_confirmed_at',
        'staff_confirmed',
        'staff_confirmed_by',
        'staff_confirmed_at',
        'collected_weight_kg',
        'collected_quantity',
        'collection_notes',
        'cancellation_reason',
        'gps_tracking_enabled',
        'gps_tracking_started_at',
        'gps_tracking_stopped_at',
    ];

    protected function casts(): array
    {
        return [
            'pickup_date' => 'date',
            'scheduled_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'started_at' => 'datetime',
            'arrived_at' => 'datetime',
            'collected_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'institution_confirmed' => 'boolean',
            'institution_confirmed_at' => 'datetime',
            'staff_confirmed' => 'boolean',
            'staff_confirmed_at' => 'datetime',
            'collected_weight_kg' => 'decimal:2',
            'collected_quantity' => 'integer',
            'gps_tracking_enabled' => 'boolean',
            'gps_tracking_started_at' => 'datetime',
            'gps_tracking_stopped_at' => 'datetime',
        ];
    }

    public function wasteListing(): BelongsTo
    {
        return $this->belongsTo(WasteListing::class, 'waste_listing_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(User::class, 'institution_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function institutionConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'institution_confirmed_by');
    }

    public function staffConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_confirmed_by');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(PickupLocation::class, 'pickup_id');
    }

    public function qrTags(): HasMany
    {
        return $this->hasMany(QrTag::class, 'pickup_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'pickup_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'pickup_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'pickup_id');
    }
}