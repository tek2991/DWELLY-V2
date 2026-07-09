<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class RoomType extends DomainModel
{
    protected $table = 'room_types';

    protected $fillable = ['name'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
