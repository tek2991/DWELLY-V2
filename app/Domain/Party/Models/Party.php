<?php

namespace App\Domain\Party\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Geographic\Models\Region;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Party extends DomainModel
{
    protected $table = 'parties';

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function individual(): HasOne
    {
        return $this->hasOne(PartyIndividual::class);
    }

    public function organization(): HasOne
    {
        return $this->hasOne(PartyOrganization::class);
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
}