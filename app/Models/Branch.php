<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tek2991\Accounting\Models\Organization;
use Tek2991\Accounting\Models\GstRegistration;
use Tek2991\Accounting\Models\State;
use Tek2991\Accounting\Models\BankAccount;

class Branch extends Model
{
    protected $fillable = [
        'organization_id',
        'gst_registration_id',
        'name',
        'code',
        'address',
        'city',
        'district',
        'state_id',
        'postal_code',
        'phone',
        'email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getTable(): string
    {
        return 'branches';
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function gstRegistration(): BelongsTo
    {
        return $this->belongsTo(GstRegistration::class, 'gst_registration_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }
    
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'branch_id');
    }
    
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
