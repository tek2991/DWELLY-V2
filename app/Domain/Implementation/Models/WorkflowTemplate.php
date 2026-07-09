<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowTemplate extends DomainModel
{
    protected $table = 'workflow_templates';

    public function variables(): HasMany
    {
        return $this->hasMany(WorkflowVariable::class);
    }

    public function phases(): HasMany
    {
        return $this->hasMany(WorkflowPhase::class)->orderBy('order');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class)->orderBy('order');
    }

    public function workPackages(): HasMany
    {
        return $this->hasMany(WorkflowWorkPackage::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(WorkflowDeliverable::class);
    }

    public function taskTemplates(): HasMany
    {
        return $this->hasMany(WorkflowTaskTemplate::class);
    }

    public function approvalSteps(): HasMany
    {
        return $this->hasMany(WorkflowApprovalStep::class)->orderBy('step_order');
    }
}
