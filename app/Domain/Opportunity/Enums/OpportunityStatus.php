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
    case MOU_PENDING = 'mou_pending';
    case MOU_SIGNED = 'mou_signed';
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
            self::MOU_PENDING => 'MOU Pending',
            self::MOU_SIGNED => 'MOU Signed',
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
            self::NEGOTIATION, self::MOU_PENDING => 'purple',
            self::MOU_SIGNED, self::CONVERTED => 'success',
            self::CLOSED_LOST, self::CANCELLED => 'danger',
        };
    }
}
