<?php

namespace App\Domain\Opportunity\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunitySnapshot extends DomainModel
{
    protected $table = 'opportunity_snapshots';

    // Disable default timestamps since we only have created_at
    public $timestamps = false;

    protected $casts = [
        'snapshot_data' => 'json',
        'created_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
