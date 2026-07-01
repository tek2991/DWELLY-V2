<?php

namespace Tek2991\Accounting\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContactType: string implements HasLabel
{
    case Customer = 'customer';
    case Vendor = 'vendor';
    case Both = 'both';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Vendor => 'Vendor',
            self::Both => 'Customer & Vendor',
        };
    }
}
