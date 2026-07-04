<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WastePhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'waste_listing_id',
        'uploaded_by',
        'photo_path',
        'original_name',
        'mime_type',
        'file_size',
        'storage_disk',
        'photo_type',
        'is_primary',
        'sort_order',
        'is_ai_processed',
        'ai_processed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'is_ai_processed' => 'boolean',
            'ai_processed_at' => 'datetime',
        ];
    }

    public function wasteListing(): BelongsTo
    {
        return $this->belongsTo(WasteListing::class, 'waste_listing_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(WasteAiAnalysis::class, 'waste_photo_id');
    }
}