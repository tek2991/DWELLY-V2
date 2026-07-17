<?php

namespace App\Domain\Audit\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditCategory extends DomainModel
{
    protected $table = 'audit_categories';

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AuditItem::class)->orderBy('sort_order');
    }
}
