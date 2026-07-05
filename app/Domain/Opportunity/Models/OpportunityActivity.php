<?php

namespace App\Domain\Opportunity\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Opportunity\Enums\OpportunityActivityType;

class OpportunityActivity extends DomainModel
{
    protected $table = 'opportunity_activities';

    protected $casts = [
        'activity_type' => OpportunityActivityType::class,
        'metadata' => 'json',
        'performed_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
