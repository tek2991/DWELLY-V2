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
}
