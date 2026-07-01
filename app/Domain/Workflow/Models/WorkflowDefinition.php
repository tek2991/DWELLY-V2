<?php

namespace App\Domain\Workflow\Models;

use App\Domain\Shared\Models\DomainModel;

class WorkflowDefinition extends DomainModel
{
    protected $table = 'workflow_definitions';

    protected $casts = [
        'schema_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function instances()
    {
        return $this->hasMany(WorkflowInstance::class);
    }
}
