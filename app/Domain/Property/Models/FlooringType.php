<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class FlooringType extends DomainModel
{
    protected $table = 'flooring_types';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
