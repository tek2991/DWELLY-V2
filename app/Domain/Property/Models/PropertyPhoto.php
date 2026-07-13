<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyPhoto extends DomainModel
{
    protected $table = 'property_photos';

    protected $fillable = [
        'property_id',
        'property_room_id',
        'file_path',
        'title',
        'is_featured',
        'is_visible',
        'order_column',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(PropertyRoom::class, 'property_room_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
