<?php

namespace App\Domain\Agreement\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Party\Models\Party;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TenancyRole extends DomainModel
{
    protected $table = 'tenancy_roles';

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(TenancyAgreement::class, 'tenancy_agreement_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function riskSnapshot(): HasOne
    {
        return $this->hasOne(TenantRiskSnapshot::class);
    }
}
