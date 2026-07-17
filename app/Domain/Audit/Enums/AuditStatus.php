<?php

namespace App\Domain\Audit\Enums;

enum AuditStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case APPROVED = 'approved';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SCHEDULED => 'Scheduled',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::APPROVED => 'Approved',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SCHEDULED => 'warning',
            self::IN_PROGRESS => 'info',
            self::COMPLETED => 'success',
            self::APPROVED => 'primary',
        };
    }
}
