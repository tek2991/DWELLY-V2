<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class PropertyAmenity extends DomainModel
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public function tapActivity(\Spatie\Activitylog\Models\Activity $activity, string $eventName)
    {
        $name = $this->amenityType?->name;
        if ($name) {
            $activity->properties = $activity->properties->merge(['item_name' => $name]);
        }
    }

    protected $table = 'property_amenities';

    protected $fillable = [
        'property_id',
        'amenity_type_id',
        'notes',
    ];

    public function amenityType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AmenityType::class);
    }
}