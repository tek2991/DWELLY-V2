<?php

namespace App\Domain\Party\Models;

use App\Domain\Party\Enums\VendorOnboardingStatus;
use App\Domain\Shared\Models\DomainModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorProfile extends DomainModel
{
    protected $table = 'vendor_profiles';

    public $timestamps = false;

    protected $fillable = [
        'party_id',
        'vendor_trade_id',
        'gstin',
        'service_regions',
        'rating',
        'total_jobs_completed',
        'is_preferred',
        'onboarding_status',
        'verification_notes',
        'verified_at',
        'verified_by_id',
    ];

    protected $casts = [
        'onboarding_status' => VendorOnboardingStatus::class,
        'service_regions' => 'array',
        'is_preferred' => 'boolean',
        'rating' => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(VendorTrade::class, 'vendor_trade_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    public function isVerified(): bool
    {
        return $this->onboarding_status === VendorOnboardingStatus::VERIFIED;
    }
}