<?php

namespace App\Domain\Audit\Enums;

enum EvidenceStatus: string
{
    case PENDING = 'pending';
    case ANNOTATED = 'annotated';
    case VERIFIED = 'verified';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ANNOTATED => 'Annotated',
            self::VERIFIED => 'Verified',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::ANNOTATED => 'success',
            self::VERIFIED => 'info',
        };
    }
}
