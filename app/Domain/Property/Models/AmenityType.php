<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class AmenityType extends DomainModel
{
    protected $table = 'amenity_types';

    protected $fillable = ['name'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
