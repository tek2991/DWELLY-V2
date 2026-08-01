<?php

namespace App\Domain\Maintenance\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MaintenanceRequestItem extends DomainModel implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('issue_photos');
        $this->addMediaCollection('repaired_photos');
    }

    protected $table = 'maintenance_request_items';

    protected $fillable = [
        'maintenance_request_id',
        'itemable_type',
        'itemable_id',
        'issue_description',
        'repair_action',
        'estimated_cost',
        'actual_cost',
        'status',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
    ];

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }
}
