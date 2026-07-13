<?php

namespace App\Domain\Opportunity\Models;

use App\Domain\Shared\Models\DomainModel;

class FinancialModel extends DomainModel
{
    protected $table = 'financial_models';

    protected $fillable = [
        'code',
        'name',
        'description',
        'fee_collection',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
