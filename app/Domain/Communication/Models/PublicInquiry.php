<?php

namespace App\Domain\Communication\Models;

use App\Domain\Property\Models\Property;
use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicInquiry extends DomainModel
{
    protected $table = 'public_inquiries';

    protected $fillable = [
        'type',
        'name',
        'phone',
        'email',
        'message',
        'property_id',
        'payload',
        'status',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
