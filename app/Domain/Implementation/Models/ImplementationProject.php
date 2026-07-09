<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ImplementationProject extends DomainModel
{
    protected $table = 'implementation_projects';

    protected $casts = [
        'target_completion_date' => 'date',
        'progress' => 'decimal:2',
    ];

    public function workflowTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'manager_id');
    }

    public function variables(): HasMany
    {
        return $this->hasMany(ImplementationProjectVariable::class, 'project_id');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(ImplementationProjectPhase::class, 'project_id')->orderBy('order');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ImplementationProjectStage::class, 'project_id')->orderBy('order');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(ImplementationDeliverable::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ImplementationTask::class, 'project_id');
    }

    public function approvalSteps(): HasMany
    {
        return $this->hasMany(ImplementationApprovalStep::class, 'project_id')->orderBy('step_order');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(ImplementationFinding::class, 'project_id');
    }
}
