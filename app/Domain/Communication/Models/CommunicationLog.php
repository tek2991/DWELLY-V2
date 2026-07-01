<?php

namespace App\Domain\Communication\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Party\Models\Party;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationLog extends DomainModel
{
    protected $table = 'communication_logs';

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class);
    }
}
