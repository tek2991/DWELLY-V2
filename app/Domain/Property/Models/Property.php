<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Geographic\Models\Locality;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Property extends DomainModel
{
    use LogsActivity;

    protected $table = 'properties';

    protected $casts = [
        'is_listed' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public function localityRef(): BelongsTo
    {
        return $this->belongsTo(Locality::class, 'locality_id');
    }

    public function getCityIdAttribute(): ?string
    {
        if ($this->locality_id) {
            $locality = Locality::find($this->locality_id);
            if ($locality?->city_id) {
                return $locality->city_id;
            }
        }

        $mouCityId = $this->mous()->latest()->first()?->legal_terms['city_id'] ?? null;
        if ($mouCityId) {
            return $mouCityId;
        }

        if ($this->city) {
            return \App\Domain\Geographic\Models\City::where('name', 'LIKE', $this->city)->value('id');
        }

        return null;
    }

    public function agreements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domain\Agreement\Models\TenancyAgreement::class);
    }

    public function tenancyAgreements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->agreements();
    }

    public function rooms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyRoom::class);
    }

    public function inventories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyInventory::class);
    }

    public function amenities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyAmenity::class);
    }

    public function establishments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyEstablishment::class);
    }

    public function pricingVersions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyPricingVersion::class);
    }

    public function financialTerms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyFinancialTerm::class);
    }

    public function photos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyPhoto::class);
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyDocument::class);
    }

    public function utilities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyUtility::class);
    }



    public function mous(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domain\Mou\Models\Mou::class, 'property_id');
    }

    public function furnishingType(): BelongsTo
    {
        return $this->belongsTo(FurnishingType::class, 'furnishing_type_id');
    }

    public function onboardingProject(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OnboardingProject::class, 'property_id');
    }

    public function audits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domain\Audit\Models\Audit::class);
    }

    public function maintenanceRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domain\Maintenance\Models\MaintenanceRequest::class);
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            \App\Domain\Party\Models\Party::class,
            \App\Domain\Mou\Models\Mou::class,
            'property_id',
            'id',
            'id',
            'party_id'
        );
    }

    public function isLockedDuringOnboarding(): bool
    {
        return $this->mous()->where('type', \App\Domain\Mou\Enums\MouType::ONBOARDING)->exists() 
            && $this->onboardingProject?->status !== 'Activated';
    }
}