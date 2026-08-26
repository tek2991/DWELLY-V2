<?php

namespace App\Domain\Agreement\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DeboardingStatus: string implements HasLabel, HasColor, HasIcon
{
    case NOTICE_SERVED = 'notice_served';
    case AUDIT_PENDING = 'audit_pending';
    case AUDIT_IN_PROGRESS = 'audit_in_progress';
    case AUDIT_REVIEW = 'audit_review';
    case AUDIT_APPROVED = 'audit_approved';
    case MAINTENANCE_REQUIRED = 'maintenance_required';
    case SETTLEMENT_PENDING = 'settlement_pending';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::NOTICE_SERVED => 'Notice Served',
            self::AUDIT_PENDING => 'Exit Audit Pending',
            self::AUDIT_IN_PROGRESS => 'Exit Audit In Progress',
            self::AUDIT_REVIEW => 'Audit In Review',
            self::AUDIT_APPROVED => 'Audit Approved',
            self::MAINTENANCE_REQUIRED => 'Maintenance & Repairs',
            self::SETTLEMENT_PENDING => 'Deposit & Key Settlement',
            self::COMPLETED => 'Vacated & Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NOTICE_SERVED => 'warning',
            self::AUDIT_PENDING => 'info',
            self::AUDIT_IN_PROGRESS => 'primary',
            self::AUDIT_REVIEW => 'purple',
            self::AUDIT_APPROVED => 'sky',
            self::MAINTENANCE_REQUIRED => 'danger',
            self::SETTLEMENT_PENDING => 'amber',
            self::COMPLETED => 'success',
            self::CANCELLED => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::NOTICE_SERVED => 'heroicon-o-bell-alert',
            self::AUDIT_PENDING => 'heroicon-o-clipboard-document-list',
            self::AUDIT_IN_PROGRESS => 'heroicon-o-camera',
            self::AUDIT_REVIEW => 'heroicon-o-magnifying-glass',
            self::AUDIT_APPROVED => 'heroicon-o-check-badge',
            self::MAINTENANCE_REQUIRED => 'heroicon-o-wrench-screwdriver',
            self::SETTLEMENT_PENDING => 'heroicon-o-key',
            self::COMPLETED => 'heroicon-o-check-circle',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }
}
