<?php

namespace App\Domain\Geographic\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffGeographicAccess extends DomainModel
{
    protected $table = 'staff_geographic_access';

    protected $fillable = [
        'user_id',
        'area_type',
        'area_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
