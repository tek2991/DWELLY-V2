<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyPhoto extends DomainModel
{
    protected $table = 'property_photos';

    protected $fillable = [
        'property_id',
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

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
