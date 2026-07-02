<?php

namespace App\Domain\Party\Enums;

enum BusinessRole: string
{
    case OWNER = 'owner';
    case TENANT = 'tenant';
    case VENDOR = 'vendor';
    case STAFF = 'staff';
    
    public function getLabel(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::TENANT => 'Tenant',
            self::VENDOR => 'Vendor',
            self::STAFF => 'Staff',
        };
    }
}
