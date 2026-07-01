<?php

namespace App\Domain\Finance\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerPayout extends DomainModel
{
    protected $table = 'owner_payouts';

    protected $casts = [
        'rent_collected' => 'decimal:2',
        'management_fee' => 'decimal:2',
        'reserve_deduction' => 'decimal:2',
        'amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'processed_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'owner_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
