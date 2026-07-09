<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ImplementationFinding extends DomainModel
{
    protected $table = 'implementation_findings';

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ImplementationProject::class, 'project_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo('related');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'reported_by');
    }
}
