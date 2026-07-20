<?php

namespace App\Domain\Audit\Enums;

enum AuditStatus: string
{
    case DRAFT = 'draft';
    case IN_PROGRESS = 'in_progress';
    case PENDING_REVIEW = 'pending_review';
    case IN_REVIEW = 'in_review';
    case PARTIALLY_APPROVED = 'partially_approved';
    case APPROVED = 'approved';
    case COMPLETED = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::IN_PROGRESS => 'In Progress',
            self::PENDING_REVIEW => 'Pending Review',
            self::IN_REVIEW => 'In Review',
            self::PARTIALLY_APPROVED => 'Changes Requested',
            self::APPROVED => 'Approved',
            self::COMPLETED => 'Completed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::IN_PROGRESS => 'info',
            self::PENDING_REVIEW => 'warning',
            self::IN_REVIEW => 'warning',
            self::PARTIALLY_APPROVED => 'danger',
            self::APPROVED => 'success',
            self::COMPLETED => 'primary',
        };
    }
}
