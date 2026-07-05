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

    // PropertyType, BhkType, etc. would be added here linking to reference data models.
}