<?php

namespace App\Domain\Finance\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends DomainModel
{
    protected $table = 'journal_entries';

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }
}
