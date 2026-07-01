<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Payment extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('financial')
            ->setDescriptionForEvent(fn(string $eventName) => "This payment has been {$eventName}");
    }

    protected $fillable = [
        'branch_id',
        'paymentable_type',
        'paymentable_id',
        'transaction_id',
        'payment_account_id',
        'amount',
        'payment_date',
        'reference',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'payments';
    }

    // Amount Accessors/Mutators (Minor Units)
    protected function amount(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }

    // Relationships
    public function paymentable(): MorphTo { return $this->morphTo(); }
    public function paymentAccount(): BelongsTo { return $this->belongsTo(Account::class, 'payment_account_id'); }
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class, 'transaction_id'); }
}
