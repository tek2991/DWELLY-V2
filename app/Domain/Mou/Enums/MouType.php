<?php

namespace App\Domain\Mou\Enums;

enum MouType: string
{
    case ONBOARDING = 'onboarding';
    case SIGN_AUTHORITY_UPDATE = 'sign_authority_update';
    case BANK_DETAILS_UPDATE = 'bank_details_update';
    case KYC_UPDATE = 'kyc_update';
    case PRICING_UPDATE = 'pricing_update';

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
}
