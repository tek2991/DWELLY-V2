<?php

namespace App\Domain\Finance\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerPayout extends DomainModel
{
    protected $table = 'owner_payouts';

    protected $fillable = [
        'branch_id',
        'owner_id',
        'property_id',
        'transaction_id',
        'period_start',
        'period_end',
        'rent_collected',
        'management_fee',
        'advance_offset',
        'reserve_deduction',
        'amount',
        'status',
        'notes',
        'processed_at',
    ];

    protected $casts = [
        'rent_collected' => 'decimal:2',
        'management_fee' => 'decimal:2',
        'advance_offset' => 'decimal:2',
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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(\Tek2991\Accounting\Models\Transaction::class, 'transaction_id');
    }

    public function getPeriodFormattedAttribute(): ?string
    {
        if ($this->period_start && $this->period_end) {
            return $this->period_start->format('d M Y') . ' – ' . $this->period_end->format('d M Y');
        } elseif ($this->period_start) {
            return $this->period_start->format('d M Y');
        }
        return null;
    }
}
