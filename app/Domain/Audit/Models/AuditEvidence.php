<?php

namespace App\Domain\Audit\Models;

use App\Domain\Audit\Enums\EvidenceStatus;
use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AuditEvidence extends DomainModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'audit_evidence';

    protected $casts = [
        'annotation_json' => 'array',
        'status' => EvidenceStatus::class,
    ];

    public function auditItem(): BelongsTo
    {
        return $this->belongsTo(AuditItem::class, 'audit_item_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');
        $this->addMediaCollection('videos');
    }
}
