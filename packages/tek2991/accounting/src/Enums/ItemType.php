<?php

namespace Tek2991\Accounting\Enums;

use Filament\Support\Contracts\HasLabel;

enum ItemType: string implements HasLabel
{
    case Goods = 'goods';
    case Services = 'services';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Goods => 'Goods',
            self::Services => 'Services',
        };
    }
}
