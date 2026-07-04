<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'pickup_id',
        'driver_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'heading',
        'altitude',
        'status',
        'is_current',
        'source',
        'device_id',
        'battery_level',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy' => 'decimal:2',
            'speed' => 'decimal:2',
            'heading' => 'decimal:2',
            'altitude' => 'decimal:2',
            'is_current' => 'boolean',
            'battery_level' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function pickup(): BelongsTo
    {
        return $this->belongsTo(Pickup::class, 'pickup_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}