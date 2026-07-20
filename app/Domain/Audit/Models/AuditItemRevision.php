<?php

namespace App\Domain\Audit\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditItemRevision extends DomainModel
{
    protected $table = 'audit_item_revisions';

    protected $casts = [
        'snapshot_data' => 'array',
    ];

    public function auditItem(): BelongsTo
    {
        return $this->belongsTo(AuditItem::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
