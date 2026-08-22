<?php

namespace App\Domain\Property\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum PropertyStatus: string implements HasColor, HasIcon, HasLabel
{
    case DRAFT = 'Draft';
    case ONBOARDING = 'Onboarding';
    case VACANT = 'Vacant';
    case OCCUPIED = 'Occupied';
    case MAINTENANCE = 'Maintenance';
    case ARCHIVED = 'Archived';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ONBOARDING => 'In Onboarding',
            self::VACANT => 'Vacant',
            self::OCCUPIED => 'Occupied',
            self::MAINTENANCE => 'Under Maintenance',
            self::ARCHIVED => 'Archived',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ONBOARDING => 'warning',
            self::VACANT => 'success',
            self::OCCUPIED => 'primary',
            self::MAINTENANCE => 'danger',
            self::ARCHIVED => 'gray',
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::DRAFT => Heroicon::OutlinedPencilSquare,
            self::ONBOARDING => Heroicon::OutlinedArrowPath,
            self::VACANT => Heroicon::OutlinedHome,
            self::OCCUPIED => Heroicon::OutlinedUserGroup,
            self::MAINTENANCE => Heroicon::OutlinedWrenchScrewdriver,
            self::ARCHIVED => Heroicon::OutlinedArchiveBox,
        };
    }

    public static function fromValue(?string $value): ?self
    {
        if (! $value) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'draft' => self::DRAFT,
            'onboarding' => self::ONBOARDING,
            'vacant' => self::VACANT,
            'occupied' => self::OCCUPIED,
            'maintenance', 'under maintenance', 'under_maintenance' => self::MAINTENANCE,
            'archived', 'deactivated' => self::ARCHIVED,
            default => null,
        };
    }
}
