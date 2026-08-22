<?php

namespace App\Domain\Opportunity\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum MouStatus: string implements HasLabel, HasColor, HasIcon
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
            self::VERIFIED => 'Verified Agreement',
            self::COMPLETED => 'Completed',
            self::CONVERTED => 'Converted to Property',
            self::EXPIRED => 'Expired',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PARTY_PENDING => 'amber',
            self::READY_TO_GENERATE => 'sky',
            self::PDF_GENERATED => 'info',
            self::DOWNLOADED => 'indigo',
            self::SIGNED_COPY_UPLOADED => 'purple',
            self::VERIFIED => 'teal',
            self::COMPLETED, self::CONVERTED => 'success',
            self::EXPIRED => 'orange',
            self::CANCELLED => 'danger',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::DRAFT => Heroicon::OutlinedPencilSquare,
            self::PARTY_PENDING => Heroicon::OutlinedUserPlus,
            self::READY_TO_GENERATE => Heroicon::OutlinedDocumentPlus,
            self::PDF_GENERATED => Heroicon::OutlinedArrowDownTray,
            self::DOWNLOADED => Heroicon::OutlinedArrowDownCircle,
            self::SIGNED_COPY_UPLOADED => Heroicon::OutlinedDocumentArrowUp,
            self::VERIFIED => Heroicon::OutlinedCheckBadge,
            self::COMPLETED => Heroicon::OutlinedCheckCircle,
            self::CONVERTED => Heroicon::OutlinedBuildingOffice2,
            self::EXPIRED => Heroicon::OutlinedClock,
            self::CANCELLED => Heroicon::OutlinedXCircle,
        };
    }
}
