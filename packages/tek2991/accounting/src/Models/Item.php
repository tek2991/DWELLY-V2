<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Tek2991\Accounting\Concerns\CompanyOwned;
use Tek2991\Accounting\Enums\ItemType;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Item extends Model
{
    use CompanyOwned;
    use SoftDeletes;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('catalog')
            ->setDescriptionForEvent(fn(string $eventName) => "This item has been {$eventName}");
    }

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'sku',
        'description',
        'hsn_sac',
        'income_account_id',
        'expense_account_id',
        'sale_price',
        'purchase_price',
        'sellable',
        'purchasable',
    ];

    protected $casts = [
        'type' => ItemType::class,
        'sellable' => 'boolean',
        'purchasable' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'items';
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    protected function salePrice(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value !== null ? $value / 100 : 0,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function purchasePrice(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value !== null ? $value / 100 : 0,
            set: fn ($value) => (int) round($value * 100),
        );
    }
}
