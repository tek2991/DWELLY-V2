<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Geographic\Models\Region;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Property extends DomainModel
{
    protected $table = 'properties';

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function agreements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domain\Agreement\Models\TenancyAgreement::class);
    }

    // PropertyType, BhkType, etc. would be added here linking to reference data models.
}