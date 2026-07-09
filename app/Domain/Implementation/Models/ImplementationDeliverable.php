<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImplementationDeliverable extends DomainModel
{
    protected $table = 'implementation_deliverables';

    protected $casts = [
        'validation_parameters' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ImplementationProject::class, 'project_id');
    }
}
