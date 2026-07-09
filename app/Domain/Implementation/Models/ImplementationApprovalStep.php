<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImplementationApprovalStep extends DomainModel
{
    protected $table = 'implementation_approval_steps';

    protected $casts = [
        'actioned_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ImplementationProject::class, 'project_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'assigned_to');
    }

    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'actioned_by');
    }
}
