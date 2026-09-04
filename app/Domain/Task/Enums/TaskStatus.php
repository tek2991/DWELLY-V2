<?php

namespace App\Domain\Task\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TaskStatus: string implements HasLabel, HasColor, HasIcon
{
    case PENDING = 'pending';
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case UNDER_REVIEW = 'under_review';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case BLOCKED = 'blocked';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SCHEDULED => 'Scheduled',
            self::IN_PROGRESS => 'In Progress',
            self::UNDER_REVIEW => 'Under Review',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::BLOCKED => 'Blocked',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::SCHEDULED => 'info',
            self::IN_PROGRESS => 'warning',
            self::UNDER_REVIEW => 'purple',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
            self::BLOCKED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING => 'heroicon-m-clock',
            self::SCHEDULED => 'heroicon-m-calendar',
            self::IN_PROGRESS => 'heroicon-m-arrow-path',
            self::UNDER_REVIEW => 'heroicon-m-eye',
            self::COMPLETED => 'heroicon-m-check-circle',
            self::CANCELLED => 'heroicon-m-x-circle',
            self::BLOCKED => 'heroicon-m-no-symbol',
        };
    }
}
