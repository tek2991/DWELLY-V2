<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tek2991\Accounting\Concerns\CompanyOwned;
use Tek2991\Accounting\Enums\FiscalPeriodStatus;

class FiscalPeriod extends Model
{
    use CompanyOwned;

    protected $fillable = [
        'company_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'closing_profit_loss',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => FiscalPeriodStatus::class,
        'closed_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'fiscal_periods';
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(config('accounting.user_model'), 'closed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FiscalPeriodEvent::class);
    }

    public function getIsLockedAttribute(): bool
    {
        return $this->status !== FiscalPeriodStatus::Open;
    }

    public function isSoftClosed(): bool
    {
        return $this->status === FiscalPeriodStatus::SoftClosed;
    }
}
