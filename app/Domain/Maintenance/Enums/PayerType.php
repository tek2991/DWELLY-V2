<?php

namespace App\Domain\Maintenance\Enums;

enum PayerType: string
{
    // Scenario 1: Owner or Tenant pays vendor directly (Dwelly not involved)
    case OWNER_DIRECT = 'owner_direct';
    case TENANT_DIRECT = 'tenant_direct';

    // Scenario 2: Dwelly pays vendor and invoices owner/tenant
    case DWELLY_INVOICE_OWNER = 'dwelly_invoice_owner';
    case DWELLY_INVOICE_TENANT = 'dwelly_invoice_tenant';
    case DWELLY_INVOICE_SPLIT = 'dwelly_invoice_split';

    // Scenario 3: Dwelly pays vendor directly & absorbs cost (Very rare / internal expense)
    case DWELLY_DIRECT_ABSORBED = 'dwelly_direct_absorbed';

    public function getLabel(): string
    {
        return match ($this) {
            self::OWNER_DIRECT => 'Owner Paid Vendor Directly (Dwelly Not Involved)',
            self::TENANT_DIRECT => 'Tenant Paid Vendor Directly (Dwelly Not Involved)',
            self::DWELLY_INVOICE_OWNER => 'Dwelly Pays Vendor -> Invoices Owner',
            self::DWELLY_INVOICE_TENANT => 'Dwelly Pays Vendor -> Invoices Tenant',
            self::DWELLY_INVOICE_SPLIT => 'Dwelly Pays Vendor -> Invoices Owner & Tenant (Split)',
            self::DWELLY_DIRECT_ABSORBED => 'Dwelly Pays Directly (Absorbed Expense - Rare)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OWNER_DIRECT, self::TENANT_DIRECT => 'info',
            self::DWELLY_INVOICE_OWNER, self::DWELLY_INVOICE_TENANT, self::DWELLY_INVOICE_SPLIT => 'warning',
            self::DWELLY_DIRECT_ABSORBED => 'danger',
        };
    }

    public function isDirectPayment(): bool
    {
        return in_array($this, [self::OWNER_DIRECT, self::TENANT_DIRECT]);
    }

    public function isDwellyInvoiced(): bool
    {
        return in_array($this, [self::DWELLY_INVOICE_OWNER, self::DWELLY_INVOICE_TENANT, self::DWELLY_INVOICE_SPLIT]);
    }

    public function isDwellyAbsorbed(): bool
    {
        return $this === self::DWELLY_DIRECT_ABSORBED;
    }
}
