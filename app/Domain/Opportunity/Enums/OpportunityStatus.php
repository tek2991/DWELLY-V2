<?php

namespace App\Domain\Opportunity\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum OpportunityStatus: string implements HasLabel, HasColor, HasIcon
{
    case NEW = 'new';
    case CONTACTED = 'contacted';
    case SITE_VISIT_SCHEDULED = 'site_visit_scheduled';
    case SITE_VISIT_COMPLETED = 'site_visit_completed';
    case NEGOTIATION = 'negotiation';
    case READY_FOR_MOU = 'ready_for_mou';
    case MOU_CREATED = 'mou_created';
    case MOU_SIGNED = 'mou_signed';
    case CONVERTED = 'converted';
    case CLOSED_LOST = 'closed_lost';
    case CANCELLED = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NEW => 'New Lead',
            self::CONTACTED => 'Contacted',
            self::SITE_VISIT_SCHEDULED => 'Site Visit Scheduled',
            self::SITE_VISIT_COMPLETED => 'Site Visit Completed',
            self::NEGOTIATION => 'Negotiation',
            self::READY_FOR_MOU => 'Ready For MOU',
            self::MOU_CREATED => 'MOU Created',
            self::MOU_SIGNED => 'MOU Signed',
            self::CONVERTED => 'Converted to Property',
            self::CLOSED_LOST => 'Closed Lost',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NEW => 'info',
            self::CONTACTED => 'primary',
            self::SITE_VISIT_SCHEDULED => 'warning',
            self::SITE_VISIT_COMPLETED => 'teal',
            self::NEGOTIATION => 'purple',
            self::READY_FOR_MOU => 'emerald',
            self::MOU_CREATED => 'sky',
            self::MOU_SIGNED => 'success',
            self::CONVERTED => 'success',
            self::CLOSED_LOST => 'danger',
            self::CANCELLED => 'gray',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::NEW => Heroicon::OutlinedSparkles,
            self::CONTACTED => Heroicon::OutlinedPhone,
            self::SITE_VISIT_SCHEDULED => Heroicon::OutlinedCalendarDays,
            self::SITE_VISIT_COMPLETED => Heroicon::OutlinedMapPin,
            self::NEGOTIATION => Heroicon::OutlinedChatBubbleLeftRight,
            self::READY_FOR_MOU => Heroicon::OutlinedCheckBadge,
            self::MOU_CREATED => Heroicon::OutlinedDocumentPlus,
            self::MOU_SIGNED => Heroicon::OutlinedDocumentCheck,
            self::CONVERTED => Heroicon::OutlinedBuildingOffice2,
            self::CLOSED_LOST => Heroicon::OutlinedXCircle,
            self::CANCELLED => Heroicon::OutlinedNoSymbol,
        };
    }
}
