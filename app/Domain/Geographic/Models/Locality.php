<?php

namespace App\Domain\Geographic\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Locality extends DomainModel
{
    protected $table = 'localities';

    protected $fillable = [
        'city_id',
        'name',
        'slug',
        'pincode',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Locality $locality) {
            if (empty($locality->slug)) {
                $baseSlug = Str::slug($locality->name);
                $slug = $baseSlug;
                $counter = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . (++$counter);
                }
                $locality->slug = $slug;
            }
        });
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(\App\Domain\Property\Models\Property::class, 'locality_id');
    }
}
