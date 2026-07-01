<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Tek2991\Accounting\Concerns\Blamable;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Enums\TransactionType;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use Blamable;
    use LogsActivity;

    protected static $recordEvents = ['created'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('accounting');
    }

    protected $fillable = [
        'branch_id',
        'account_id',
        'bank_account_id',
        'type',
        'description',
        'notes',
        'reference',
        'amount',
        'pending',
        'reviewed',
        'allow_reversal',
        'posted_at',
        'created_by',
        'updated_by',
        'voucherable_type',
        'voucherable_id',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'pending' => 'boolean',
        'reviewed' => 'boolean',
        'allow_reversal' => 'boolean',
        'posted_at' => 'date',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'transactions';
    }

    // ──────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────

    /**
     * The primary account for this transaction (Income/Expense/Category account).
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * The bank account (cash/card side) for Deposit and Withdrawal transactions.
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * Journal entries that make up this transaction (debits and credits).
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'transaction_id');
    }

    /**
     * Polymorphic relation to the source document (Invoice, Bill, Payment).
     */
    public function voucherable(): MorphTo
    {
        return $this->morphTo();
    }

    // ──────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────

    public function scopePosted(Builder $query): Builder
    {
        return $query->whereNotNull('posted_at');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('pending', true);
    }

    public function scopeReviewed(Builder $query): Builder
    {
        return $query->where('reviewed', true);
    }

    public function scopeUnreviewed(Builder $query): Builder
    {
        return $query->where('reviewed', false);
    }

    public function scopeOfType(Builder $query, TransactionType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeInDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('posted_at', [$startDate, $endDate]);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Check if this transaction's journal entries are balanced.
     */
    public function isBalanced(): bool
    {
        $entries = $this->journalEntries;

        $totalDebit = $entries
            ->where('type', \Tek2991\Accounting\Enums\JournalEntryType::Debit)
            ->sum(fn ($entry) => $entry->getRawOriginal('amount'));

        $totalCredit = $entries
            ->where('type', \Tek2991\Accounting\Enums\JournalEntryType::Credit)
            ->sum(fn ($entry) => $entry->getRawOriginal('amount'));

        return $totalDebit === $totalCredit;
    }

    /**
     * Interact with the transaction's amount.
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
     * Check if this transaction has been posted.
     */
    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }

    // ──────────────────────────────────────────────────────────────
    // Static Helpers (for Filament forms)
    // ──────────────────────────────────────────────────────────────

    /**
     * Get bank account options grouped by subtype for Select fields.
     * Returns ['Subtype' => [id => name]].
     */
    public static function getBankAccountOptions(bool $excludeArchived = true): array
    {
        return BankAccount::getGroupedSelectOptions($excludeArchived);
    }

    /**
     * Get category (chart account) options for Deposit/Withdrawal.
     * Filters accounts to types logically appropriate for the transaction type.
     */
    public static function getCategoryAccountOptions(TransactionType $type): array
    {
        // Allow all account types for manual transactions, but filter out Bank Accounts
        $query = Account::query()->active()->isNotBankAccount();

        return $query->get()
            ->groupBy(fn (Account $account) => $account->type->getPluralLabel())
            ->map(fn ($accounts, string $category) => $accounts->pluck('name', 'id'))
            ->toArray();
    }

    /**
     * Get all chart account options grouped by category (for Journal entries).
     */
    public static function getJournalAccountOptions(): array
    {
        return Account::query()
            ->active()
            ->get()
            ->groupBy(fn (Account $account) => $account->type->getPluralLabel())
            ->map(fn ($accounts, string $category) => $accounts->pluck('name', 'id'))
            ->toArray();
    }
}
