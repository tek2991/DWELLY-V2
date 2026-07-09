<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowTaskTemplate extends DomainModel
{
    protected $table = 'workflow_task_templates';

    protected $casts = [
        'is_mandatory' => 'boolean',
        'assignment_rule' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkflowWorkPackage::class, 'work_package_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'stage_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(WorkflowTaskChecklist::class, 'task_template_id');
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(WorkflowTaskDependency::class, 'task_template_id');
    }
}
