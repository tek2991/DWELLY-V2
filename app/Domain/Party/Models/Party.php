<?php

namespace App\Domain\Party\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Geographic\Models\Locality;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Party extends DomainModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'parties';

    protected $casts = [
        'is_tax_registered' => 'boolean',
    ];

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

    public function individual(): HasOne
    {
        return $this->hasOne(PartyIndividual::class);
    }

    public function organization(): HasOne
    {
        return $this->hasOne(PartyOrganization::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(PartyAddress::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(PartyBankAccount::class);
    }

    public function ownerProfile(): HasOne
    {
        return $this->hasOne(OwnerProfile::class);
    }

    public function tenantProfile(): HasOne
    {
        return $this->hasOne(TenantProfile::class);
    }

    public function vendorProfile(): HasOne
    {
        return $this->hasOne(VendorProfile::class);
    }
    
    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }
    
    public function hasRole(\App\Domain\Party\Enums\BusinessRole $role): bool
    {
        return match ($role) {
            \App\Domain\Party\Enums\BusinessRole::OWNER => $this->ownerProfile()->exists(),
            \App\Domain\Party\Enums\BusinessRole::TENANT => $this->tenantProfile()->exists(),
            \App\Domain\Party\Enums\BusinessRole::VENDOR => $this->vendorProfile()->exists(),
            \App\Domain\Party\Enums\BusinessRole::STAFF => $this->staffProfile()->exists(),
        };
    }
    
    public function enableRole(\App\Domain\Party\Enums\BusinessRole $role, array $attributes = []): void
    {
        if ($this->hasRole($role)) {
            return;
        }

        match ($role) {
            \App\Domain\Party\Enums\BusinessRole::OWNER => $this->ownerProfile()->create($attributes),
            \App\Domain\Party\Enums\BusinessRole::TENANT => $this->tenantProfile()->create($attributes),
            \App\Domain\Party\Enums\BusinessRole::VENDOR => $this->vendorProfile()->create($attributes),
            \App\Domain\Party\Enums\BusinessRole::STAFF => $this->staffProfile()->create($attributes),
        };
        
        // Synchronous provisioning
        app(\App\Domain\Finance\Services\AccountingProvisioningService::class)->ensurePartyAccountingReady($this);
        
        event(new \App\Domain\Party\Events\PartyRoleEnabled($this, $role));
    }
    
    public function disableRole(\App\Domain\Party\Enums\BusinessRole $role): void
    {
        if (!$this->hasRole($role)) {
            return;
        }

        match ($role) {
            \App\Domain\Party\Enums\BusinessRole::OWNER => $this->ownerProfile()->delete(),
            \App\Domain\Party\Enums\BusinessRole::TENANT => $this->tenantProfile()->delete(),
            \App\Domain\Party\Enums\BusinessRole::VENDOR => $this->vendorProfile()->delete(),
            \App\Domain\Party\Enums\BusinessRole::STAFF => $this->staffProfile()->delete(),
        };
        
        event(new \App\Domain\Party\Events\PartyRoleDisabled($this, $role));
    }
}