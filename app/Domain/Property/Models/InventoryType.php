<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class InventoryType extends DomainModel
{
    protected $table = 'inventory_types';

    protected $fillable = ['name'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
