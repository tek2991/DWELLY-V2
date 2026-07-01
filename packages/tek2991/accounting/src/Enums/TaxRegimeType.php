<?php

namespace Tek2991\Accounting\Enums;

use Filament\Support\Contracts\HasLabel;

enum TaxRegimeType: string implements HasLabel
{
    case Generic = 'generic';
    case IndiaGst = 'india_gst';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Generic => 'Generic / Standard',
            self::IndiaGst => 'India GST',
        };
    }
}
