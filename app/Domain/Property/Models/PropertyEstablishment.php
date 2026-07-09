<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class PropertyEstablishment extends DomainModel
{
    protected $table = 'property_establishments';

    protected $fillable = [
        'property_id',
        'establishment_id',
        'distance_km',
        'travel_time_minutes',
        'remarks',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function establishment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }
}