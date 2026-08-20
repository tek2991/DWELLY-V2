<?php

namespace App\Domain\Maintenance\Enums;

enum PayerType: string
{
    case OWNER = 'owner';
    case TENANT = 'tenant';
    case SPLIT = 'split';
    case DWELLY = 'dwelly';

    // Legacy cases for backwards compatibility
    case OWNER_DIRECT = 'owner_direct';
    case TENANT_DIRECT = 'tenant_direct';
    case DWELLY_INVOICE_OWNER = 'dwelly_invoice_owner';
    case DWELLY_INVOICE_TENANT = 'dwelly_invoice_tenant';
    case DWELLY_INVOICE_SPLIT = 'dwelly_invoice_split';
    case DWELLY_DIRECT_ABSORBED = 'dwelly_direct_absorbed';

    public function getLabel(): string
    {
        return match ($this) {
            self::OWNER, self::OWNER_DIRECT, self::DWELLY_INVOICE_OWNER => '👤 Owner',
            self::TENANT, self::TENANT_DIRECT, self::DWELLY_INVOICE_TENANT => '🏠 Tenant',
            self::SPLIT, self::DWELLY_INVOICE_SPLIT => '🤝 Split (Owner & Tenant)',
            self::DWELLY, self::DWELLY_DIRECT_ABSORBED => '🏢 Dwelly (Internal Absorbed)',
        };
    }

    public function getPlainLabel(): string
    {
        return match ($this) {
            self::OWNER, self::OWNER_DIRECT, self::DWELLY_INVOICE_OWNER => 'Owner',
            self::TENANT, self::TENANT_DIRECT, self::DWELLY_INVOICE_TENANT => 'Tenant',
            self::SPLIT, self::DWELLY_INVOICE_SPLIT => 'Split (Owner & Tenant)',
            self::DWELLY, self::DWELLY_DIRECT_ABSORBED => 'Dwelly (Internal Absorbed)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OWNER, self::OWNER_DIRECT, self::DWELLY_INVOICE_OWNER => 'info',
            self::TENANT, self::TENANT_DIRECT, self::DWELLY_INVOICE_TENANT => 'warning',
            self::SPLIT, self::DWELLY_INVOICE_SPLIT => 'purple',
            self::DWELLY, self::DWELLY_DIRECT_ABSORBED => 'danger',
        };
    }

    public function isDirectPayment(): bool
    {
        return in_array($this, [self::OWNER_DIRECT, self::TENANT_DIRECT]);
    }

    public function isDwellyInvoiced(): bool
    {
        return in_array($this, [self::OWNER, self::TENANT, self::SPLIT, self::DWELLY_INVOICE_OWNER, self::DWELLY_INVOICE_TENANT, self::DWELLY_INVOICE_SPLIT]);
    }

    public function isDwellyAbsorbed(): bool
    {
        return in_array($this, [self::DWELLY, self::DWELLY_DIRECT_ABSORBED]);
    }
}
