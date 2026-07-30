<?php

namespace App\Domain\Mou\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum MouType: string implements HasLabel, HasColor
{
    case ONBOARDING = 'onboarding';
    case SIGN_AUTHORITY_UPDATE = 'sign_authority_update';
    case BANK_DETAILS_UPDATE = 'bank_details_update';
    case KYC_UPDATE = 'kyc_update';
    case PRICING_UPDATE = 'pricing_update';

    public function getLabel(): ?string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match($this) {
            self::ONBOARDING => 'Onboarding',
            self::SIGN_AUTHORITY_UPDATE => 'Sign Authority Update',
            self::BANK_DETAILS_UPDATE => 'Bank Details Update',
            self::KYC_UPDATE => 'KYC Update',
            self::PRICING_UPDATE => 'Pricing Update',
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::ONBOARDING => 'primary',
            self::SIGN_AUTHORITY_UPDATE => 'warning',
            self::BANK_DETAILS_UPDATE => 'info',
            self::KYC_UPDATE => 'purple',
            self::PRICING_UPDATE => 'success',
        };
    }
}
