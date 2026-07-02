<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Enums\DiscountType;

class Invoice extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('financial')
            ->setDescriptionForEvent(fn(string $eventName) => "This invoice has been {$eventName}");
    }

    protected $fillable = [
        'branch_id',
        'contact_id',
        'transaction_id',
        'invoice_number',
        'status',
        'issue_date',
        'due_date',
        'currency_code',
        'exchange_rate',
        'discount_type',
        'discount_rate',
        'discount_amount',
        'discount_account_id',
        'tax_total',
        'grand_total',
        'amount_paid',
        'balance_due',
        'notes',
        'terms',
        'place_of_supply_state_id',
    ];

    protected $casts = [
        'status' => InvoiceStatus::class,
        'issue_date' => 'date',
        'due_date' => 'date',
        'billing_address_snapshot' => 'array',
        'discount_type' => DiscountType::class,
        'exchange_rate' => 'decimal:6',
        'discount_rate' => 'decimal:4',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'invoices';
    }

    public function discountAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'discount_account_id');
    }

    // Amount Accessors/Mutators (Minor Units)
    protected function subtotal(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function discountAmount(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function taxTotal(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function grandTotal(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function amountPaid(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function balanceDue(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }

    // Relationships
    public function branch(): BelongsTo { return $this->belongsTo(\App\Models\Branch::class, 'branch_id'); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class, 'contact_id'); }
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class, 'transaction_id'); }
    public function items(): HasMany { return $this->hasMany(InvoiceItem::class, 'invoice_id')->orderBy('sort_order'); }
    public function payments(): MorphMany { return $this->morphMany(Payment::class, 'paymentable'); }
    public function placeOfSupplyState(): BelongsTo { return $this->belongsTo(State::class, 'place_of_supply_state_id'); }

    // Helpers
    public function getIsOverdueAttribute(): bool
    {
        return $this->getRawOriginal('balance_due') > 0 && 
               $this->due_date && 
               $this->due_date->isPast();
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->is_overdue && in_array($this->status, [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid])) {
            return 'overdue';
        }
        return $this->status->value;
    }
}
