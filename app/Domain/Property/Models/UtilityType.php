<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class UtilityType extends DomainModel
{
    protected $table = 'utility_types';

    protected $fillable = ['name', 'slug', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
