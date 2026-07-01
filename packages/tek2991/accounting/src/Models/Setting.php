<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Setting extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('accounting');
    }

    protected $fillable = [
        'company_id',
        'default_currency',
        'company_name',
        'company_email',
        'company_address',
        'company_phone',
        'company_tax_id',
        'invoice_prefix',
        'invoice_next_number',
        'bill_prefix',
        'bill_next_number',
        'credit_note_prefix',
        'credit_note_next_number',
        'debit_note_prefix',
        'debit_note_next_number',
        'payment_prefix',
        'journal_prefix',
    ];

    public function getTable(): string
    {
        $prefix = config('accounting.table_prefix', 'acc_');
        return $prefix . 'settings';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(config('accounting.company_model', 'App\\Models\\Company'));
    }
}
