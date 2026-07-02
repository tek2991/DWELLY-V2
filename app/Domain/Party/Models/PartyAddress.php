<?php

namespace App\Domain\Party\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyAddress extends DomainModel
{
    protected $table = 'party_addresses';

    protected $fillable = [
        'party_id',
        'type',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'pincode',
        'country',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
