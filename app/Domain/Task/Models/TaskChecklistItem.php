<?php

namespace App\Domain\Task\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklistItem extends DomainModel
{
    protected $table = 'task_checklist_items';

    protected $fillable = [
        'task_id',
        'title',
        'description',
        'is_mandatory',
        'is_completed',
        'completed_at',
        'completed_by_id',
        'sort_order',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }
}
