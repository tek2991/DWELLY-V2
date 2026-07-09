<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImplementationTaskChecklist extends DomainModel
{
    protected $table = 'implementation_task_checklists';

    protected $casts = [
        'is_mandatory' => 'boolean',
        'is_completed' => 'boolean',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ImplementationTask::class, 'task_id');
    }
}
