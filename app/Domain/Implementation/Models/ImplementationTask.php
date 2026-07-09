<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImplementationTask extends DomainModel
{
    protected $table = 'implementation_tasks';

    public function project(): BelongsTo
    {
        return $this->belongsTo(ImplementationProject::class, 'project_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ImplementationProjectStage::class, 'stage_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'assignee_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(ImplementationTaskChecklist::class, 'task_id');
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(ImplementationTaskDependency::class, 'task_id');
    }
}
