<?php

namespace App\Domain\Task\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TaskCategory: string implements HasLabel, HasColor, HasIcon
{
    case FIELD_WORK = 'field_work';
    case COMPLIANCE = 'compliance';
    case LIFECYCLE = 'lifecycle';
    case QUALITY_ASSURANCE = 'quality_assurance';
    case ADMINISTRATIVE = 'administrative';

    public function getLabel(): string
    {
        return match ($this) {
            self::FIELD_WORK => 'Field & Property Care',
            self::COMPLIANCE => 'Legal & Compliance',
            self::LIFECYCLE => 'Tenant & Owner Relationship',
            self::QUALITY_ASSURANCE => 'Quality Assurance & Handover',
            self::ADMINISTRATIVE => 'Administrative & Logistics',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::FIELD_WORK => 'info',
            self::COMPLIANCE => 'warning',
            self::LIFECYCLE => 'success',
            self::QUALITY_ASSURANCE => 'purple',
            self::ADMINISTRATIVE => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::FIELD_WORK => 'heroicon-m-wrench-screwdriver',
            self::COMPLIANCE => 'heroicon-m-document-check',
            self::LIFECYCLE => 'heroicon-m-user-group',
            self::QUALITY_ASSURANCE => 'heroicon-m-clipboard-document-check',
            self::ADMINISTRATIVE => 'heroicon-m-briefcase',
        };
    }
}
