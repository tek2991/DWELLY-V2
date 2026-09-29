<?php

namespace App\Domain\Shared\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum KycRequirementLevel: string implements HasLabel, HasColor
{
    case DISABLED = 'disabled';
    case OPTIONAL = 'optional';
    case MANDATORY = 'mandatory';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DISABLED => 'Disabled (N/A)',
            self::OPTIONAL => 'Optional',
            self::MANDATORY => 'Mandatory',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DISABLED => 'gray',
            self::OPTIONAL => 'warning',
            self::MANDATORY => 'success',
        };
    }

    public function isMandatory(): bool
    {
        return $this === self::MANDATORY;
    }

    public function isOptional(): bool
    {
        return $this === self::OPTIONAL;
    }

    public function isEnabled(): bool
    {
        return $this !== self::DISABLED;
    }
}
