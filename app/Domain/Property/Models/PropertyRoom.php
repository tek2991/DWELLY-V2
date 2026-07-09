<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class PropertyRoom extends DomainModel
{
    protected $table = 'property_rooms';

    protected $fillable = [
        'property_id',
        'room_type_id',
        'count',
    ];

    public function roomType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}