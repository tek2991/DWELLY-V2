<?php

namespace App\Domain\Shared\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentType: string implements HasLabel, HasColor
{
    case AADHAAR = 'aadhaar';
    case PAN = 'pan';
    case CANCELLED_CHEQUE = 'cancelled_cheque';
    case VOTER_ID = 'voter_id';
    case PASSPORT = 'passport';
    case NREGA_CARD = 'nrega_card';
    case DRIVING_LICENSE = 'driving_license';
    case POWER_OF_ATTORNEY = 'power_of_attorney';
    case ELECTRICITY_BILL = 'electricity_bill';
    case PROPERTY_TAX_RECEIPT = 'property_tax_receipt';
    case SALE_DEED = 'sale_deed';
    case OTHER = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::AADHAAR => 'Aadhaar Card',
            self::PAN => 'PAN Card',
            self::CANCELLED_CHEQUE => 'Cancelled Cheque',
            self::VOTER_ID => 'Voter ID',
            self::PASSPORT => 'Passport',
            self::NREGA_CARD => 'MGNREGA Job Card',
            self::DRIVING_LICENSE => 'Driving License',
            self::POWER_OF_ATTORNEY => 'Power of Attorney / Authorization',
            self::ELECTRICITY_BILL => 'Electricity Bill',
            self::PROPERTY_TAX_RECEIPT => 'Property Tax Receipt',
            self::SALE_DEED => 'Sale Deed / Index II',
            self::OTHER => 'Other Document',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::AADHAAR, self::PAN => 'success',
            self::CANCELLED_CHEQUE => 'info',
            self::VOTER_ID, self::PASSPORT, self::DRIVING_LICENSE, self::NREGA_CARD => 'primary',
            self::POWER_OF_ATTORNEY => 'warning',
            self::ELECTRICITY_BILL, self::PROPERTY_TAX_RECEIPT, self::SALE_DEED => 'gray',
            self::OTHER => 'gray',
        };
    }
}
