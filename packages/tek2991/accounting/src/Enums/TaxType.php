<?php

namespace Tek2991\Accounting\Enums;

use Filament\Support\Contracts\HasLabel;

enum TaxType: string implements HasLabel
{
    case Inclusive = 'inclusive';
    case Exclusive = 'exclusive';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Inclusive => 'Inclusive',
            self::Exclusive => 'Exclusive',
        };
    }
}
