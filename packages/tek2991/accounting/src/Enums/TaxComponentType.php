<?php

namespace Tek2991\Accounting\Enums;

use Filament\Support\Contracts\HasLabel;

enum TaxComponentType: string implements HasLabel
{
    case Generic = 'generic';
    case Intrastate = 'intrastate';
    case Interstate = 'interstate';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Generic => 'Generic',
            self::Intrastate => 'Intrastate (e.g. CGST/SGST)',
            self::Interstate => 'Interstate (e.g. IGST)',
        };
    }
}
