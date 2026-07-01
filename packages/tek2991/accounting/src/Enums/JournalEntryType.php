<?php

namespace Tek2991\Accounting\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JournalEntryType: string implements HasLabel, HasColor
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function getLabel(): ?string
    {
        return $this->name;
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Debit => 'info',
            self::Credit => 'success',
        };
    }

    /**
     * Get the opposite entry type (debit ↔ credit).
     */
    public function opposite(): self
    {
        return match ($this) {
            self::Debit => self::Credit,
            self::Credit => self::Debit,
        };
    }
}
