<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImplementationProjectStage extends DomainModel
{
    protected $table = 'implementation_project_stages';

    protected $casts = [
        'is_mandatory' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ImplementationProject::class, 'project_id');
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(ImplementationProjectPhase::class, 'phase_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ImplementationTask::class, 'stage_id');
    }
}
