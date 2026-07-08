<?php

namespace App\Domain\Opportunity\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum OpportunityStatus: string implements HasLabel, HasColor
{
    case NEW = 'new';
    case CONTACTED = 'contacted';
    case SITE_VISIT_SCHEDULED = 'site_visit_scheduled';
    case SITE_VISIT_COMPLETED = 'site_visit_completed';
    case NEGOTIATION = 'negotiation';
    case READY_FOR_MOU = 'ready_for_mou';
    case CONVERTED = 'converted';
    case CLOSED_LOST = 'closed_lost';
    case CANCELLED = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NEW => 'New',
            self::CONTACTED => 'Contacted',
            self::SITE_VISIT_SCHEDULED => 'Site Visit Scheduled',
            self::SITE_VISIT_COMPLETED => 'Site Visit Completed',
            self::NEGOTIATION => 'Negotiation',
            self::READY_FOR_MOU => 'Ready For MOU',
            self::CONVERTED => 'Converted',
            self::CLOSED_LOST => 'Closed Lost',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NEW => 'info',
            self::CONTACTED => 'primary',
            self::SITE_VISIT_SCHEDULED, self::SITE_VISIT_COMPLETED => 'warning',
            self::NEGOTIATION => 'purple',
            self::READY_FOR_MOU => 'success',
            self::CONVERTED => 'success',
            self::CLOSED_LOST, self::CANCELLED => 'danger',
        };
    }
}
