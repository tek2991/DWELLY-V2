<?php

namespace Tek2991\Accounting\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentLineType: string implements HasLabel
{
    case Item = 'item';
    case Account = 'account';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Item => 'Item',
            self::Account => 'Account',
        };
    }
}
