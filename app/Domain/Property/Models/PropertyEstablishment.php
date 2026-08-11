<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class PropertyEstablishment extends DomainModel
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
        $name = $this->establishment?->name;
        if ($name) {
            $activity->properties = $activity->properties->merge(['item_name' => $name]);
        }
    }

    protected $table = 'property_establishments';

    protected $fillable = [
        'property_id',
        'establishment_id',
        'distance_km',
        'travel_time_minutes',
        'remarks',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function establishment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }
}