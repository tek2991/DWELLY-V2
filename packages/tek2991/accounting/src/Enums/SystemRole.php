<?php

namespace Tek2991\Accounting\Enums;

use Filament\Support\Contracts\HasLabel;

enum SystemRole: string implements HasLabel
{
    case TradeReceivable = 'trade_receivable';
    case TradePayable = 'trade_payable';
    case Inventory = 'inventory';
    case Cash = 'cash';
    case Bank = 'bank';
    case GstInput = 'gst_input';
    case GstOutput = 'gst_output';
    case RetainedEarnings = 'retained_earnings';
    case CustomerReceivable = 'customer_receivable';
    case VendorPayable = 'vendor_payable';

    // Real Estate & Fiduciary System Roles
    case OwnerPayable = 'owner_payable';
    case TenantReceivable = 'tenant_receivable';
    case SecurityDepositLiability = 'security_deposit_liability';
    case SecurityDepositOwnerAsset = 'security_deposit_owner_asset';
    case OwnerAdvanceAsset = 'owner_advance_asset';
    case CommissionRevenue = 'commission_revenue';
    case MaintenanceIncome = 'maintenance_income';
    case MaintenanceExpense = 'maintenance_expense';
    case RentIncome = 'rent_income';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TradeReceivable => 'Trade Receivable Control',
            self::TradePayable => 'Trade Payable Control',
            self::Inventory => 'Inventory Control',
            self::Cash => 'Cash',
            self::Bank => 'Bank Account',
            self::GstInput => 'GST Input',
            self::GstOutput => 'GST Output',
            self::RetainedEarnings => 'Retained Earnings',
            self::CustomerReceivable => 'Customer Sub-ledger',
            self::VendorPayable => 'Vendor Sub-ledger',
            self::OwnerPayable => 'Owner Payable (Pass-Through Rent)',
            self::TenantReceivable => 'Tenant Receivable',
            self::SecurityDepositLiability => 'Tenant Security Deposit Liability',
            self::SecurityDepositOwnerAsset => 'Security Deposit with Owner',
            self::OwnerAdvanceAsset => 'Owner Advance / Recoverable',
            self::CommissionRevenue => 'Property Management Commission Income',
            self::MaintenanceIncome => 'Maintenance Service Income',
            self::MaintenanceExpense => 'Maintenance Direct Expense',
            self::RentIncome => 'Rental Income (Direct)',
        };
    }
}
