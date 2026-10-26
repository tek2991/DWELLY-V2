<?php

namespace App\Domain\Shared\Models;

use App\Domain\Shared\Enums\KycRequirementLevel;
use Illuminate\Database\Eloquent\Builder;

class KycDocumentType extends DomainModel
{
    public const SYSTEM_CODES = [
        'pan',
        'aadhaar',
        'electricity_bill',
        'passport_photo',
    ];

    protected $table = 'kyc_document_types';

    protected static function booted(): void
    {
        static::deleting(function (self $doc) {
            if ($doc->isSystem()) {
                throw new \InvalidArgumentException("Cannot delete system-reserved KYC document type '{$doc->name}'.");
            }
        });
    }

    public function isSystem(): bool
    {
        return in_array($this->code, self::SYSTEM_CODES, true);
    }

    protected $fillable = [
        'name',
        'code',
        'description',
        'icon',
        'has_number',
        'number_label',
        'number_regex',
        'mou_owner_requirement',
        'mou_signatory_requirement',
        'tenancy_primary_requirement',
        'tenancy_secondary_requirement',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'has_number' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'mou_owner_requirement' => KycRequirementLevel::class,
        'mou_signatory_requirement' => KycRequirementLevel::class,
        'tenancy_primary_requirement' => KycRequirementLevel::class,
        'tenancy_secondary_requirement' => KycRequirementLevel::class,
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function scopeForMouOwner(Builder $query): Builder
    {
        return $query->active()->where('mou_owner_requirement', '!=', KycRequirementLevel::DISABLED);
    }

    public function scopeForMouSignatory(Builder $query): Builder
    {
        return $query->active()->where('mou_signatory_requirement', '!=', KycRequirementLevel::DISABLED);
    }

    public function scopeForTenancyPrimary(Builder $query): Builder
    {
        return $query->active()->where('tenancy_primary_requirement', '!=', KycRequirementLevel::DISABLED);
    }

    public function scopeForTenancySecondary(Builder $query): Builder
    {
        return $query->active()->where('tenancy_secondary_requirement', '!=', KycRequirementLevel::DISABLED);
    }

    public function isMouOwnerMandatory(): bool
    {
        return $this->mou_owner_requirement === KycRequirementLevel::MANDATORY;
    }

    public function isMouSignatoryMandatory(): bool
    {
        return $this->mou_signatory_requirement === KycRequirementLevel::MANDATORY;
    }

    public function isTenancyPrimaryMandatory(): bool
    {
        return $this->tenancy_primary_requirement === KycRequirementLevel::MANDATORY;
    }

    public function isTenancySecondaryMandatory(): bool
    {
        return $this->tenancy_secondary_requirement === KycRequirementLevel::MANDATORY;
    }
}
