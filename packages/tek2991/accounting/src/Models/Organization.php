<?php

namespace Tek2991\Accounting\Models;

use App\Models\Branch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tek2991\Accounting\Enums\TaxRegimeType;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'legal_name',
        'trade_name',
        'pan',
        'default_currency',
        'fiscal_year_start',
        'tax_regime',
        'email',
        'phone',
        'website',
        'logo',
    ];

    protected $casts = [
        'tax_regime' => TaxRegimeType::class,
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'organizations';
    }

    public static function current(): self
    {
        return self::firstOrCreate(
            [], // single row
            [
                'name' => 'Dwelly',
                'default_currency' => config('accounting.default_currency', 'INR'),
                'fiscal_year_start' => config('accounting.fiscal_year_start', 4),
            ]
        );
    }
    
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'organization_id');
    }
}
