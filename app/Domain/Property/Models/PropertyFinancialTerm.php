<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyFinancialTerm extends DomainModel
{
    protected $table = 'property_financial_terms';

    protected $fillable = [
        'property_id',
        'mou_id',
        'pricing_model',
        'fee_percentage',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected $casts = [
        'fee_percentage' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function mou(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Mou\Models\Mou::class, 'mou_id');
    }
}
