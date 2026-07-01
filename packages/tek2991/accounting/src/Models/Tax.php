<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Tax extends Model
{
    use SoftDeletes;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('catalog')
            ->setDescriptionForEvent(fn(string $eventName) => "This tax group has been {$eventName}");
    }

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'taxes';
    }

    public function components(): HasMany
    {
        return $this->hasMany(TaxComponent::class, 'tax_id');
    }

    /**
     * Get the total rate of all components combined.
     */
    public function getTotalRateAttribute(): float
    {
        return (float) $this->components->sum('rate');
    }
}
