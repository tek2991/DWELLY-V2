<?php

namespace App\Domain\Party\Enums;

enum VendorOnboardingStatus: string
{
    case DRAFT = 'draft';
    case PENDING_VERIFICATION = 'pending_verification';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';
    case SUSPENDED = 'suspended';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_VERIFICATION => 'Pending Verification',
            self::VERIFIED => 'Verified & Active',
            self::REJECTED => 'Rejected',
            self::SUSPENDED => 'Suspended',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING_VERIFICATION => 'warning',
            self::VERIFIED => 'success',
            self::REJECTED => 'danger',
            self::SUSPENDED => 'danger',
        };
    }
}
