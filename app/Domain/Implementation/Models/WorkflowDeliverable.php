<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowDeliverable extends DomainModel
{
    protected $table = 'workflow_deliverables';

    protected $casts = [
        'validation_parameters' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkflowWorkPackage::class, 'work_package_id');
    }
}
