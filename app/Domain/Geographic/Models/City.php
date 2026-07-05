<?php

namespace App\Domain\Geographic\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends DomainModel
{
    protected $table = 'cities';

    protected $fillable = [
        'district_id',
        'name',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function localities(): HasMany
    {
        return $this->hasMany(Locality::class);
    }
}
