<?php

namespace Tek2991\Accounting\Enums;

use Filament\Support\Contracts\HasLabel;

enum GstRegistrationType: string implements HasLabel
{
    case Regular = 'regular';
    case Composition = 'composition';
    case Unregistered = 'unregistered';
    case Consumer = 'consumer';
    case SEZ = 'sez';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Composition => 'Composition',
            self::Unregistered => 'Unregistered Business',
            self::Consumer => 'Consumer',
            self::SEZ => 'SEZ',
        };
    }
}
