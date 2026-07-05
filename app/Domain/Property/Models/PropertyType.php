<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class PropertyType extends DomainModel
{
    protected $table = 'property_types';

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
