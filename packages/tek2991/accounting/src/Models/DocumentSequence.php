<?php

namespace Tek2991\Accounting\Models;

use App\Models\Branch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSequence extends Model
{
    protected $fillable = [
        'branch_id',
        'document_type',
        'prefix',
        'next_number',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'document_sequences';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
