<?php

namespace App\Domain\Opportunity\Enums;

use Filament\Support\Contracts\HasLabel;

enum OpportunityActivityType: string implements HasLabel
{
    case CREATED = 'created';
    case NOTE = 'note';
    case CALL = 'call';
    case WHATSAPP = 'whatsapp';
    case EMAIL = 'email';
    case SITE_VISIT = 'site_visit';
    case STATUS_CHANGE = 'status_change';
    case MOU_STATUS_CHANGE = 'mou_status_change';
    case MOU_GENERATED = 'mou_generated';
    case MOU_UPLOADED = 'mou_uploaded';
    case INTERNAL_NOTE = 'internal_note';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CREATED => 'Created',
            self::NOTE => 'Note',
            self::CALL => 'Call',
            self::WHATSAPP => 'WhatsApp',
            self::EMAIL => 'Email',
            self::SITE_VISIT => 'Site Visit',
            self::STATUS_CHANGE => 'Status Change',
            self::MOU_STATUS_CHANGE => 'MOU Status Change',
            self::MOU_GENERATED => 'MOU Generated',
            self::MOU_UPLOADED => 'MOU Uploaded',
            self::INTERNAL_NOTE => 'Internal Note',
        };
    }
}
