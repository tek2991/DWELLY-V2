<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class EstablishmentType extends DomainModel
{
    protected $table = 'establishment_types';

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
