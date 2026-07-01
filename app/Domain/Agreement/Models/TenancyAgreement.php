<?php

namespace App\Domain\Agreement\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Property\Models\Property;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Party\Models\Party;

class TenancyAgreement extends DomainModel
{
    protected $table = 'tenancy_agreements';

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'rent_amount' => 'decimal:2',
        'security_deposit' => 'decimal:2',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(TenancyRole::class);
    }

    public function tenants()
    {
        return $this->belongsToMany(Party::class, 'tenancy_roles')
                    ->withPivot(['role_type', 'is_primary']);
    }
}
