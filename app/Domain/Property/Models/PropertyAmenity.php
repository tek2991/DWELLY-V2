<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class PropertyAmenity extends DomainModel
{
    protected $table = 'property_amenities';

    protected $fillable = [
        'property_id',
        'amenity_type_id',
        'notes',
    ];

    public function amenityType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AmenityType::class);
    }
}