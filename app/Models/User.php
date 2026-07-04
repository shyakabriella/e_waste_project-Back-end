<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Notifiable;
    use HasFactory, Notifiable, HasApiTokens;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_INSTITUTION = 'institution';
    public const ROLE_ENVIROSERVE_STAFF = 'enviroserve_staff';
    public const ROLE_DRIVER = 'driver';
    public const ROLE_FINANCE_OFFICER = 'finance_officer';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'phone',
        'address',
        'institution_name',
        'institution_type',
        'district',
        'sector',
        'cell',
        'village',
        'staff_code',
        'staff_position',
        'wallet_balance',
        'points_balance',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'wallet_balance' => 'decimal:2',
            'points_balance' => 'integer',
        ];
    }

    public function roleRecord(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'slug');
    }

    public function wasteListings(): HasMany
    {
        return $this->hasMany(WasteListing::class, 'institution_id');
    }

    public function createdWasteCategories(): HasMany
    {
        return $this->hasMany(WasteCategory::class, 'created_by');
    }

    public function uploadedWastePhotos(): HasMany
    {
        return $this->hasMany(WastePhoto::class, 'uploaded_by');
    }

    public function verifiedWasteListings(): HasMany
    {
        return $this->hasMany(WasteListing::class, 'verified_by');
    }

    public function wasteVerifications(): HasMany
    {
        return $this->hasMany(WasteVerification::class, 'verified_by');
    }

    public function offersMade(): HasMany
    {
        return $this->hasMany(WasteOffer::class, 'offered_by');
    }

    public function offersReceived(): HasMany
    {
        return $this->hasMany(WasteOffer::class, 'offered_to');
    }

    public function respondedOffers(): HasMany
    {
        return $this->hasMany(WasteOffer::class, 'responded_by');
    }

    public function pickupsAsInstitution(): HasMany
    {
        return $this->hasMany(Pickup::class, 'institution_id');
    }

    public function assignedPickups(): HasMany
    {
        return $this->hasMany(Pickup::class, 'assigned_staff_id');
    }

    public function driverPickups(): HasMany
    {
        return $this->hasMany(Pickup::class, 'driver_id');
    }

    public function pickupLocationsAsDriver(): HasMany
    {
        return $this->hasMany(PickupLocation::class, 'driver_id');
    }

    public function createdQrTags(): HasMany
    {
        return $this->hasMany(QrTag::class, 'created_by');
    }

    public function scannedQrTags(): HasMany
    {
        return $this->hasMany(QrTag::class, 'scanned_by');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'user_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'user_id');
    }

    public function commissionsAsInstitution(): HasMany
    {
        return $this->hasMany(Commission::class, 'institution_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    public function updatedSystemSettings(): HasMany
    {
        return $this->hasMany(SystemSetting::class, 'updated_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isInstitution(): bool
    {
        return $this->role === self::ROLE_INSTITUTION;
    }

    public function isEnviroserveStaff(): bool
    {
        return $this->role === self::ROLE_ENVIROSERVE_STAFF;
    }

    public function isDriver(): bool
    {
        return $this->role === self::ROLE_DRIVER;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }
}