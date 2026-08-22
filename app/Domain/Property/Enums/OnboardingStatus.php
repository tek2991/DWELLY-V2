<?php

namespace App\Domain\Property\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum OnboardingStatus: string implements HasColor, HasIcon, HasLabel
{
    case DRAFT = 'Draft';
    case IN_PROGRESS = 'In Progress';
    case PENDING_REVIEW = 'Pending Review';
    case CHANGES_REQUESTED = 'Changes Requested';
    case ACTIVATED = 'Activated';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::IN_PROGRESS => 'In Progress',
            self::PENDING_REVIEW => 'Pending Review',
            self::CHANGES_REQUESTED => 'Changes Requested',
            self::ACTIVATED => 'Activated & Live',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::IN_PROGRESS => 'info',
            self::PENDING_REVIEW => 'warning',
            self::CHANGES_REQUESTED => 'danger',
            self::ACTIVATED => 'success',
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::DRAFT => Heroicon::OutlinedPencilSquare,
            self::IN_PROGRESS => Heroicon::OutlinedArrowPath,
            self::PENDING_REVIEW => Heroicon::OutlinedClock,
            self::CHANGES_REQUESTED => Heroicon::OutlinedExclamationTriangle,
            self::ACTIVATED => Heroicon::OutlinedCheckBadge,
        };
    }

    public static function fromValue(?string $value): ?self
    {
        if (! $value) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'draft' => self::DRAFT,
            'in progress', 'in_progress' => self::IN_PROGRESS,
            'pending review', 'pending_review' => self::PENDING_REVIEW,
            'changes requested', 'changes_requested' => self::CHANGES_REQUESTED,
            'activated' => self::ACTIVATED,
            default => null,
        };
    }
}
