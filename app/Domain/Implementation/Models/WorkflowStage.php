<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStage extends DomainModel
{
    protected $table = 'workflow_stages';

    protected $casts = [
        'is_mandatory' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(WorkflowPhase::class, 'phase_id');
    }

    public function taskTemplates(): HasMany
    {
        return $this->hasMany(WorkflowTaskTemplate::class, 'stage_id');
    }
}
