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
        };
    }
}
