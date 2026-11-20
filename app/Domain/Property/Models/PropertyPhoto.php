<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class PropertyPhoto extends DomainModel
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }
    protected $table = 'property_photos';

    protected $fillable = [
        'property_id',
        'property_room_id',
        'file_path',
        'title',
        'is_featured',
        'is_visible',
        'order_column',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(PropertyRoom::class, 'property_room_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function getUrlAttribute(): ?string
    {
        if (blank($this->file_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
    }

    public function getImagePathAttribute(): ?string
    {
        return $this->file_path;
    }

    public function getIsCoverAttribute(): bool
    {
        return (bool) $this->is_featured;
    }
}
