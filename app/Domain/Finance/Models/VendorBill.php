<?php

namespace App\Domain\Finance\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorBill extends DomainModel
{
    protected $table = 'vendor_bills';

    protected $casts = [
        'amount' => 'decimal:2',
        'bill_date' => 'date',
        'due_date' => 'date',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'vendor_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
