<?php

namespace App\Domain\Audit\Enums;

enum ItemStatus: string
{
    case PENDING = 'pending';
    case INSPECTED = 'inspected';
    case VERIFIED = 'verified';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::INSPECTED => 'Inspected',
            self::VERIFIED => 'Verified',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::INSPECTED => 'info',
            self::VERIFIED => 'success',
        };
    }
}
