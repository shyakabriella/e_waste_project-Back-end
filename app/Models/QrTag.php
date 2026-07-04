<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'waste_listing_id',
        'pickup_id',
        'qr_code',
        'qr_image_path',
        'qr_type',
        'status',
        'created_by',
        'printed_by',
        'printed_at',
        'scanned_by',
        'scanned_at',
        'scan_purpose',
        'scan_latitude',
        'scan_longitude',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
            'scanned_at' => 'datetime',
            'scan_latitude' => 'decimal:7',
            'scan_longitude' => 'decimal:7',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}