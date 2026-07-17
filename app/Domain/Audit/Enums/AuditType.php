<?php

namespace App\Domain\Audit\Enums;

enum AuditType: string
{
    case MOVE_IN = 'move_in';
    case MOVE_OUT = 'move_out';
    case PERIODIC = 'periodic';
    case MAINTENANCE = 'maintenance';
    case SAFETY = 'safety';

    public function getLabel(): string
    {
        return match ($this) {
            self::MOVE_IN => 'Move-In Audit',
            self::MOVE_OUT => 'Move-Out Audit',
            self::PERIODIC => 'Periodic Inspection',
            self::MAINTENANCE => 'Maintenance Verification',
            self::SAFETY => 'Safety Inspection',
        };
    }
}
