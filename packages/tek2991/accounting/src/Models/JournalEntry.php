<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Tek2991\Accounting\Concerns\Blamable;
use Tek2991\Accounting\Concerns\CompanyOwned;
use Tek2991\Accounting\Enums\JournalEntryType;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class JournalEntry extends Model
{
    use Blamable;
    use CompanyOwned;
    use LogsActivity;

    protected static $recordEvents = ['created'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['account_id', 'type', 'amount'])
            ->useLogName('accounting');
    }

    protected $fillable = [
        'company_id',
        'transaction_id',
        'account_id',
        'type',
        'amount',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => JournalEntryType::class,
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'journal_entries';
    }

    // ──────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────

    /**
     * The transaction this entry belongs to.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * The account this entry affects.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Interact with the journal entry's amount.
     * Stored as minor units (cents), but exposed as major units.
     */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value !== null ? $value / 100 : 0,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    /**
     * Check if this is a debit entry.
     */
    public function isDebit(): bool
    {
        return $this->type === JournalEntryType::Debit;
    }

    /**
     * Check if this is a credit entry.
     */
    public function isCredit(): bool
    {
        return $this->type === JournalEntryType::Credit;
    }
}
