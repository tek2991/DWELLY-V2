<?php

namespace App\Domain\Agreement\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Property\Models\Property;
use App\Domain\Audit\Models\Audit;
use App\Domain\Party\Models\Party;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TenancyAgreement extends DomainModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'tenancy_agreements';

    protected $fillable = [
        'property_id',
        'audit_id',
        'code',
        'status',
        'start_date',
        'end_date',
        'rent_amount',
        'first_month_rent',
        'security_deposit',
        'booking_amount',
        'security_deposit_notes',
        'lock_in_period_months',
        'notice_period_days',
        'special_terms',
        'apdcl_consumer_id',
        'electricity_provider_id',
        'tenant_bank_details',
        'pricing_version_id',
        'signed_at',
        'signed_by_tenant',
        'keys_handed_over',
        'keys_handed_over_at',
        'key_handover_notes',
        'key_details',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'signed_at' => 'datetime',
        'keys_handed_over_at' => 'datetime',
        'rent_amount' => 'decimal:2',
        'first_month_rent' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'booking_amount' => 'decimal:2',
        'signed_by_tenant' => 'boolean',
        'keys_handed_over' => 'boolean',
        'tenant_bank_details' => 'array',
        'key_details' => 'array',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('draft_pdf');
        $this->addMediaCollection('draft_word');
        $this->addMediaCollection('signed_agreement')->singleFile();
        $this->addMediaCollection('tenant_aadhaar');
        $this->addMediaCollection('tenant_pan');
        $this->addMediaCollection('cancelled_cheque');
        $this->addMediaCollection('kyc_documents');
        $this->addMediaCollection('key_handover_attachments');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(TenancyRole::class);
    }

    public function tenants()
    {
        return $this->belongsToMany(Party::class, 'tenancy_roles')
                    ->withPivot(['role_type', 'is_primary']);
    }

    public function primaryTenant()
    {
        return $this->hasOne(TenancyRole::class)->where('is_primary', true);
    }

    public function electricityProvider(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Property\Models\UtilityProvider::class, 'electricity_provider_id');
    }
}

