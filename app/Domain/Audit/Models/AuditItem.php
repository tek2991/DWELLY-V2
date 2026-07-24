<?php

namespace App\Domain\Audit\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Audit\Enums\ItemCondition;
use App\Domain\Audit\Enums\ItemStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AuditItem extends DomainModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'audit_items';

    protected $casts = [
        'snapshot_data' => 'array',
        'condition' => ItemCondition::class,
        'status' => ItemStatus::class,
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AuditCategory::class, 'audit_category_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function evidence()
    {
        return $this->hasMany(AuditEvidence::class, 'audit_item_id');
    }

    public function reviews()
    {
        return $this->hasMany(AuditItemReview::class, 'audit_item_id');
    }

    public function revisions()
    {
        return $this->hasMany(AuditItemRevision::class, 'audit_item_id');
    }

    public function isApproved(): bool
    {
        return $this->status === ItemStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === ItemStatus::REJECTED;
    }

    public function isEditable(): bool
    {
        // Not editable if already approved
        if ($this->isApproved()) {
            return false;
        }

        // If the entire audit is draft, pending review, in review, approved, or completed, items are locked
        $auditStatus = $this->category?->audit?->status;
        if (in_array($auditStatus, [\App\Domain\Audit\Enums\AuditStatus::DRAFT, \App\Domain\Audit\Enums\AuditStatus::PENDING_REVIEW, \App\Domain\Audit\Enums\AuditStatus::IN_REVIEW, \App\Domain\Audit\Enums\AuditStatus::APPROVED, \App\Domain\Audit\Enums\AuditStatus::COMPLETED])) {
            return false;
        }

        // If audit is partially approved (returned to inspector), only rejected items are editable
        if ($auditStatus === \App\Domain\Audit\Enums\AuditStatus::PARTIALLY_APPROVED) {
            return $this->isRejected();
        }

        // Otherwise (draft, in progress), it's editable
        return true;
    }
}
