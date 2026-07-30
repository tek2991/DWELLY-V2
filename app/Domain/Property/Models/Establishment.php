<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Establishment extends DomainModel
{
    protected $table = 'establishments';

    protected $fillable = [
        'name',
        'establishment_type_id',
        'address',
        'city_id',
        'latitude',
        'longitude',
        'google_place_id',
    ];

    public function establishmentType(): BelongsTo
    {
        return $this->belongsTo(EstablishmentType::class, 'establishment_type_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Geographic\Models\City::class, 'city_id');
    }
}