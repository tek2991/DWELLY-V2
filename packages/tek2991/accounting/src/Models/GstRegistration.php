<?php

namespace Tek2991\Accounting\Models;

use App\Models\Branch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GstRegistration extends Model
{
    protected $fillable = [
        'gstin',
        'legal_name',
        'trade_name',
        'state_id',
        'address',
        'registration_date',
        'is_default',
        'status',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'is_default' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'gst_registrations';
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }
    
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'gst_registration_id');
    }
}
