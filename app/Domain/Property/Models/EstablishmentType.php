<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class EstablishmentType extends DomainModel
{
    protected $table = 'establishment_types';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function cities(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domain\Geographic\Models\City::class,
            'city_establishment_type',
            'establishment_type_id',
            'city_id'
        )->withTimestamps();
    }
}
