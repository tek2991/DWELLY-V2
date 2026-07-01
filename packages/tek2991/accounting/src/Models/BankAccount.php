<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tek2991\Accounting\Concerns\Blamable;
use Tek2991\Accounting\Enums\BankAccountType;

class BankAccount extends Model
{
    use Blamable;
    protected $fillable = [
        'branch_id',
        'type',
        'account_id',
        'nickname',
        'number',
        'enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type'    => BankAccountType::class,
        'enabled' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'bank_accounts';
    }

    protected static function booted(): void
    {
        static::saving(function (BankAccount $bankAccount) {
            // If this account is being set as enabled, disable all others of the same type for this branch
            if ($bankAccount->enabled && $bankAccount->isDirty('enabled')) {
                BankAccount::query()
                    ->where('branch_id', $bankAccount->branch_id)
                    ->where('type', $bankAccount->type)
                    ->where('id', '!=', $bankAccount->id)
                    ->update(['enabled' => false]);
            }
        });
    }

    // ──────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────

    /**
     * The chart-of-account entry backing this bank account.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Transactions that used this bank account as their cash/card side.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'bank_account_id');
    }

    // ──────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeOfType(Builder $query, BankAccountType $type): Builder
    {
        return $query->where('type', $type);
    }

    // ──────────────────────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────────────────────

    /**
     * Masked account number showing only last 4 digits.
     */
    protected function mask(): Attribute
    {
        return Attribute::get(function (mixed $value, array $attributes): ?string {
            return isset($attributes['number']) && $attributes['number']
                ? '•••• ' . substr($attributes['number'], -4)
                : null;
        });
    }

    /**
     * Display name for the bank account (delegates to the chart account).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->account?->name ?? "Bank Account #{$this->id}";
    }

    // ──────────────────────────────────────────────────────────────
    // Static helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Get bank account options as a flat list for select fields.
     * Returns [id => account_name].
     */
    public static function getSelectOptions(bool $excludeArchived = true): array
    {
        return static::query()
            ->whereHas('account', function (Builder $query) use ($excludeArchived) {
                if ($excludeArchived) {
                    $query->where('archived', false);
                }
            })
            ->with('account')
            ->get()
            ->mapWithKeys(function ($bankAccount) {
                $label = $bankAccount->account->name;
                if ($bankAccount->mask) {
                    $label .= ' (' . $bankAccount->mask . ')';
                }
                return [$bankAccount->id => $label];
            })
            ->toArray();
    }

    /**
     * Get bank account options grouped by subtype, for select fields with optgroups.
     * Returns ['Subtype Name' => [id => account_name]].
     */
    public static function getGroupedSelectOptions(bool $excludeArchived = true): array
    {
        return static::query()
            ->whereHas('account', function (Builder $query) use ($excludeArchived) {
                if ($excludeArchived) {
                    $query->where('archived', false);
                }
            })
            ->with(['account'])
            ->get()
            ->groupBy(function ($bankAccount) {
                return $bankAccount->account->reporting_class?->getLabel() ?? 'Other';
            })
            ->map(function ($accounts, string $reportingClass) {
                return $accounts->mapWithKeys(function ($bankAccount) {
                    $label = $bankAccount->account->name;
                    if ($bankAccount->mask) {
                        $label .= ' (' . $bankAccount->mask . ')';
                    }
                    return [$bankAccount->id => $label];
                });
            })
            ->toArray();
    }
}
