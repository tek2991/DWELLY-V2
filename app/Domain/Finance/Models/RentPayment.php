<?php

namespace App\Domain\Finance\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Party\Models\Party;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentPayment extends DomainModel
{
    protected $table = 'rent_payments';

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'date',
    ];

    public function tenancyAgreement(): BelongsTo
    {
        return $this->belongsTo(TenancyAgreement::class, 'tenancy_agreement_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'tenant_id');
    }
}
