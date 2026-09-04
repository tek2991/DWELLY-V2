<?php

namespace App\Domain\Task\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Task\Enums\TaskCategory;
use App\Domain\Task\Enums\TaskPriority;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskTemplate extends DomainModel
{
    use SoftDeletes;

    protected $table = 'task_templates';

    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'category',
        'default_priority',
        'default_sla_hours',
        'description',
        'is_active',
    ];

    protected $casts = [
        'category' => TaskCategory::class,
        'default_priority' => TaskPriority::class,
        'default_sla_hours' => 'integer',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaskTemplateItem::class, 'task_template_id')->orderBy('sort_order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'template_id');
    }
}
