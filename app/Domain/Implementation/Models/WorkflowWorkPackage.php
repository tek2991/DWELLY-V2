<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowWorkPackage extends DomainModel
{
    protected $table = 'workflow_work_packages';

    protected $casts = [
        'is_mandatory' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(WorkflowDeliverable::class, 'work_package_id');
    }

    public function taskTemplates(): HasMany
    {
        return $this->hasMany(WorkflowTaskTemplate::class, 'work_package_id');
    }
}
