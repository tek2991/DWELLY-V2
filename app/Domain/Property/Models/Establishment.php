<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class Establishment extends DomainModel
{
    protected $table = 'establishments';

    protected $fillable = [
        'name',
        'establishment_type_id',
        'address',
        'city',
        'latitude',
        'longitude',
        'google_place_id',
    ];
    public function establishmentType()
    {
        return $this->belongsTo(EstablishmentType::class, 'establishment_type_id');
    }
}