<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class FurnishingType extends DomainModel
{
    protected $table = 'furnishing_types';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'inventory_validation_rule',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
