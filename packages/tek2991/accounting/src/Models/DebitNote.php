<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Tek2991\Accounting\Concerns\CompanyOwned;
use Tek2991\Accounting\Enums\DebitNoteStatus;

class DebitNote extends Model
{
    use CompanyOwned;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('financial')
            ->setDescriptionForEvent(fn(string $eventName) => "This debit note has been {$eventName}");
    }

    protected $fillable = [
        'company_id',
        'contact_id',
        'bill_id',
        'transaction_id',
        'debit_note_number',
        'status',
        'issue_date',
        'reason',
        'notes',
        'subtotal',
        'tax_total',
        'grand_total',
        'applied_amount',
        'balance_remaining',
    ];

    protected $casts = [
        'status' => DebitNoteStatus::class,
        'issue_date' => 'date',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'debit_notes';
    }

    // Amount Accessors/Mutators (Minor Units)
    protected function subtotal(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function taxTotal(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function grandTotal(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function appliedAmount(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function balanceRemaining(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }

    // Relationships
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class, 'contact_id'); }
    public function bill(): BelongsTo { return $this->belongsTo(Bill::class, 'bill_id'); }
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class, 'transaction_id'); }
    public function items(): HasMany { return $this->hasMany(DebitNoteItem::class, 'debit_note_id')->orderBy('sort_order'); }
}
