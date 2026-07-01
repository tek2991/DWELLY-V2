<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Tek2991\Accounting\Enums\ContactType;
use Tek2991\Accounting\Enums\GstRegistrationType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Contact extends Model
{
    use SoftDeletes;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('catalog')
            ->setDescriptionForEvent(fn(string $eventName) => "This contact has been {$eventName}");
    }

    protected $fillable = [
        'type',
        'name',
        'email',
        'phone',
        'tax_id',
        'state_id',
        'is_tax_registered',
        'gstin',
        'gst_registration_type',
        'billing_address',
        'shipping_address',
        'receivable_balance',
        'payable_balance',
    ];

    protected $casts = [
        'type' => ContactType::class,
        'gst_registration_type' => GstRegistrationType::class,
        'is_tax_registered' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'contacts';
    }

    protected function gstin(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value ? strtoupper($value) : null,
        );
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    protected function receivableBalance(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value !== null ? $value / 100 : 0,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function payableBalance(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value !== null ? $value / 100 : 0,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    public function isCustomer(): bool
    {
        return in_array($this->type, [ContactType::Customer, ContactType::Both]);
    }

    public function isVendor(): bool
    {
        return in_array($this->type, [ContactType::Vendor, ContactType::Both]);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'contact_id');
    }

    public function receivableAccount(): HasOne
    {
        return $this->hasOne(Account::class, 'contact_id')
            ->where('system_role', \Tek2991\Accounting\Enums\SystemRole::CustomerReceivable);
    }

    public function payableAccount(): HasOne
    {
        return $this->hasOne(Account::class, 'contact_id')
            ->where('system_role', \Tek2991\Accounting\Enums\SystemRole::VendorPayable);
    }
}
