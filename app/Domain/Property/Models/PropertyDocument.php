<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyDocument extends DomainModel
{
    protected $table = 'property_documents';

    protected $fillable = [
        'property_id',
        'property_room_id',
        'document_category',
        'title',
        'file_path',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(PropertyRoom::class, 'property_room_id');
    }
}
