<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Geographic\Models\Locality;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Property extends DomainModel
{
    protected $table = 'properties';

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

    public function implementationProjects(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Domain\Implementation\Models\ImplementationProject::class, 'entity');
    }

    public function mou(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Mou\Models\Mou::class, 'mou_id');
    }
}