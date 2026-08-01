<?php

namespace App\Domain\Maintenance\Enums;

enum MaintenanceStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case VENDOR_ASSIGNED = 'vendor_assigned';
    case QUOTED = 'quoted';
    case IN_PROGRESS = 'in_progress';
    case WORK_COMPLETED = 'work_completed';
    case AUDIT_PENDING = 'audit_pending';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::VENDOR_ASSIGNED => 'Vendor Assigned',
            self::QUOTED => 'Quoted',
            self::IN_PROGRESS => 'In Progress',
            self::WORK_COMPLETED => 'Work Completed',
            self::AUDIT_PENDING => 'Audit Pending',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SUBMITTED => 'info',
            self::VENDOR_ASSIGNED => 'warning',
            self::QUOTED => 'warning',
            self::IN_PROGRESS => 'primary',
            self::WORK_COMPLETED => 'teal',
            self::AUDIT_PENDING => 'purple',
            self::RESOLVED, self::CLOSED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
