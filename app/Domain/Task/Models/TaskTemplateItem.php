<?php

namespace App\Domain\Task\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTemplateItem extends DomainModel
{
    protected $table = 'task_template_items';

    protected $fillable = [
        'task_template_id',
        'title',
        'description',
        'is_mandatory',
        'sort_order',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'task_template_id');
    }
}
