<?php

namespace App\Domain\Maintenance\Enums;

enum MaintenanceStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case VENDOR_ASSIGNED = 'vendor_assigned';
    case QUOTED = 'quoted';
    case QUOTATION_PENDING = 'quotation_pending';
    case QUOTATION_APPROVED = 'quotation_approved';
    case IN_PROGRESS = 'in_progress';
    case WORK_COMPLETED = 'work_completed';
    case AUDIT_PENDING = 'audit_pending';
    case AUDIT_APPROVED = 'audit_approved';
    case INVOICED = 'invoiced';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::VENDOR_ASSIGNED => 'Vendor Assigned',
            self::QUOTED, self::QUOTATION_PENDING => 'Quotation Pending Approval',
            self::QUOTATION_APPROVED => 'Quotation Approved',
            self::IN_PROGRESS => 'Repair In Progress',
            self::WORK_COMPLETED => 'Work Completed',
            self::AUDIT_PENDING => 'Audit Pending',
            self::AUDIT_APPROVED => 'Audit Approved',
            self::INVOICED => 'Invoiced to Payer',
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
            self::VENDOR_ASSIGNED, self::QUOTED, self::QUOTATION_PENDING => 'warning',
            self::QUOTATION_APPROVED => 'info',
            self::IN_PROGRESS => 'primary',
            self::WORK_COMPLETED => 'teal',
            self::AUDIT_PENDING => 'purple',
            self::AUDIT_APPROVED => 'sky',
            self::INVOICED => 'indigo',
            self::RESOLVED, self::CLOSED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
