<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImplementationTaskDependency extends DomainModel
{
    protected $table = 'implementation_task_dependencies';

    public function task(): BelongsTo
    {
        return $this->belongsTo(ImplementationTask::class, 'task_id');
    }

    public function dependsOnTask(): BelongsTo
    {
        return $this->belongsTo(ImplementationTask::class, 'depends_on_task_id');
    }
}
