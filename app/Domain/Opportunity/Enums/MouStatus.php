<?php

namespace App\Domain\Opportunity\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum MouStatus: string implements HasLabel, HasColor
{
    case DRAFT = 'draft';
    case PARTY_PENDING = 'party_pending';
    case READY_TO_GENERATE = 'ready_to_generate';
    case PDF_GENERATED = 'pdf_generated';
    case DOWNLOADED = 'downloaded';
    case SIGNED_COPY_UPLOADED = 'signed_copy_uploaded';
    case VERIFIED = 'verified';
    case COMPLETED = 'completed';
    case CONVERTED = 'converted';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PARTY_PENDING => 'Party Pending',
            self::READY_TO_GENERATE => 'Ready To Generate',
            self::PDF_GENERATED => 'PDF Generated',
            self::DOWNLOADED => 'Downloaded',
            self::SIGNED_COPY_UPLOADED => 'Signed Copy Uploaded',
            self::VERIFIED => 'Verified',
            self::COMPLETED => 'Completed',
            self::CONVERTED => 'Converted',
            self::EXPIRED => 'Expired',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PARTY_PENDING, self::READY_TO_GENERATE => 'warning',
            self::PDF_GENERATED, self::DOWNLOADED, self::SIGNED_COPY_UPLOADED => 'info',
            self::VERIFIED, self::COMPLETED, self::CONVERTED => 'success',
            self::EXPIRED, self::CANCELLED => 'danger',
        };
    }
}
