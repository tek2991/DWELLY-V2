<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTaskDependency extends DomainModel
{
    protected $table = 'workflow_task_dependencies';

    public function taskTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkflowTaskTemplate::class, 'task_template_id');
    }

    public function dependsOnTaskTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkflowTaskTemplate::class, 'depends_on_task_template_id');
    }
}
