<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowPhase extends DomainModel
{
    protected $table = 'workflow_phases';

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class, 'phase_id')->orderBy('order');
    }
}
