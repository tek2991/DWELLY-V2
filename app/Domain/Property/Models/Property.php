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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

    public function agreements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domain\Agreement\Models\TenancyAgreement::class);
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



    public function mou(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Mou\Models\Mou::class, 'mou_id');
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
}