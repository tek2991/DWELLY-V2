<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tek2991\Accounting\Concerns\Blamable;
use Tek2991\Accounting\Concerns\CompanyOwned;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\ReportingClass;
use Tek2991\Accounting\Enums\SystemRole;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Account extends Model
{
    use Blamable;
    use CompanyOwned;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'code', 'archived', 'type', 'reporting_class', 'system_role', 'is_control_account'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('accounting');
    }

    protected $fillable = [
        'company_id',
        'parent_id',
        'contact_id',
        'type',
        'reporting_class',
        'system_role',
        'is_control_account',
        'code',
        'name',
        'currency_code',
        'description',
        'archived',
        'default',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => AccountType::class,
        'reporting_class' => ReportingClass::class,
        'system_role' => SystemRole::class,
        'is_control_account' => 'boolean',
        'archived' => 'boolean',
        'default' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'accounts';
    }

    // ──────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }

    /**
     * The bank account record linked to this chart account (if any).
     * Only Asset and Liability accounts can be bank accounts.
     */
    public function bankAccount(): HasOne
    {
        return $this->hasOne(BankAccount::class, 'account_id');
    }

    // ──────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('archived', false);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('archived', true);
    }

    public function scopeControlAccounts(Builder $query): Builder
    {
        return $query->where('is_control_account', true);
    }

    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_control_account', false)->doesntHave('children');
    }

    /**
     * Scope to accounts eligible to be linked to a bank account.
     * Only Asset and Liability categories qualify.
     */
    public function scopeForBankAccounts(Builder $query): Builder
    {
        return $query->whereIn('type', [
            AccountType::Asset,
            AccountType::Liability,
        ]);
    }

    /**
     * Scope to accounts that are already linked as a bank account.
     */
    public function scopeIsBankAccount(Builder $query): Builder
    {
        return $query->whereHas('bankAccount');
    }

    /**
     * Scope to accounts that are NOT linked as a bank account.
     */
    public function scopeIsNotBankAccount(Builder $query): Builder
    {
        return $query->doesntHave('bankAccount');
    }

    public function scopeOfType(Builder $query, AccountType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOfReportingClass(Builder $query, ReportingClass $reportingClass): Builder
    {
        return $query->where('reporting_class', $reportingClass);
    }

    public function scopeOfSystemRole(Builder $query, SystemRole $systemRole): Builder
    {
        return $query->where('system_role', $systemRole);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('default', true);
    }

    /**
     * Scope to accounts that appear on the Balance Sheet.
     */
    public function scopeBalanceSheet(Builder $query): Builder
    {
        return $query->whereIn('type', [
            AccountType::Asset,
            AccountType::Liability,
            AccountType::Equity,
        ]);
    }

    /**
     * Scope to accounts that appear on the Income Statement.
     */
    public function scopeIncomeStatement(Builder $query): Builder
    {
        return $query->whereIn('type', [
            AccountType::Revenue,
            AccountType::Expense,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────────────────────

    /**
     * Get the full account display name with code prefix.
     */
    public function getQualifiedNameAttribute(): string
    {
        if ($this->code) {
            return "{$this->code} — {$this->name}";
        }
        return $this->name;
    }

    /**
     * Check if this account has journal entries and shouldn't be deleted.
     */
    public function getHasActivityAttribute(): bool
    {
        return $this->journalEntries()->exists();
    }
}
