<?php

namespace App\Domain\Audit\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditItemReview extends DomainModel
{
    protected $table = 'audit_item_reviews';

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function auditItem(): BelongsTo
    {
        return $this->belongsTo(AuditItem::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
