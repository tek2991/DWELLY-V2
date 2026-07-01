<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tek2991\Accounting\Enums\TaxRegimeType;

class CompanyProfile extends Model
{
    protected $fillable = [
        'company_id',
        'state_id',
        'tax_regime',
    ];

    protected $casts = [
        'tax_regime' => TaxRegimeType::class,
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'company_profiles';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(config('accounting.company_model', 'App\\Models\\Company'));
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }
}
