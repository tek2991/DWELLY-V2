<?php

namespace App\Domain\Opportunity\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum MouStatus: string implements HasLabel, HasColor
{
    case DRAFT = 'draft';
    case PENDING_SIGNATURE = 'pending_signature';
    case SIGNED = 'signed';
    case VERIFIED = 'verified';
    case ARCHIVED = 'archived';
    case CANCELLED = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_SIGNATURE => 'Pending Signature',
            self::SIGNED => 'Signed',
            self::VERIFIED => 'Verified',
            self::ARCHIVED => 'Archived',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING_SIGNATURE => 'warning',
            self::SIGNED => 'info',
            self::VERIFIED => 'success',
            self::ARCHIVED => 'danger',
            self::CANCELLED => 'danger',
        };
    }
}
