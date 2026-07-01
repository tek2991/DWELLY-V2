<?php

namespace App\Domain\Agreement\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantRiskSnapshot extends DomainModel
{
    protected $table = 'tenant_risk_snapshots';

    protected $casts = [
        'background_verified' => 'boolean',
        'monthly_income' => 'decimal:2',
        'raw_report_data' => 'array',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(TenancyRole::class, 'tenancy_role_id');
    }
}
