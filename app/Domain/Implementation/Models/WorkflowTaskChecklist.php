<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTaskChecklist extends DomainModel
{
    protected $table = 'workflow_task_checklists';

    protected $casts = [
        'is_mandatory' => 'boolean',
    ];

    public function taskTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkflowTaskTemplate::class, 'task_template_id');
    }
}
