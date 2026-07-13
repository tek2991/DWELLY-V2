<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyUtility extends DomainModel
{
    protected $table = 'property_utilities';

    protected $fillable = [
        'property_id',
        'utility_type_id',
        'paid_by',
        'amount',
        'effective_from',
        'effective_to',
        'details',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function utilityType(): BelongsTo
    {
        return $this->belongsTo(UtilityType::class);
    }
}
