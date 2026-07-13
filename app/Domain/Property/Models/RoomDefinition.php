<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomDefinition extends DomainModel
{
    protected $table = 'room_definitions';

    protected $fillable = [
        'room_type_id',
        'name',
        'slug',
        'description',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function propertyRooms(): HasMany
    {
        return $this->hasMany(PropertyRoom::class);
    }
}
