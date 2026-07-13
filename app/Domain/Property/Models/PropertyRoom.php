<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class PropertyRoom extends DomainModel
{
    protected $table = 'property_rooms';

    protected $fillable = [
        'property_id',
        'room_definition_id',
        'custom_name',
        'floor',
        'area',
        'description',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function property(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomDefinition(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RoomDefinition::class);
    }
}