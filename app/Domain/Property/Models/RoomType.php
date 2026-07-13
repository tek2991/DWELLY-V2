<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class RoomType extends DomainModel
{
    protected $table = 'room_types';

    protected $fillable = ['name', 'slug', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function roomDefinitions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RoomDefinition::class);
    }
}
