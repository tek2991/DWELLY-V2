<?php

namespace Tek2991\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\ReportingClass;
use Tek2991\Accounting\Enums\SystemRole;
use Tek2991\Accounting\Models\Account;

class DefaultChartOfAccountsSeeder extends Seeder
{
    /**
     * Seed a standard Chart of Accounts for a given company.
     */
    public function run(int $companyId, ?string $currencyCode = null): void
    {
        $currencyCode = $currencyCode ?? \Tek2991\Accounting\Facades\Accounting::getCurrency();
        $this->seedHierarchy($companyId, $currencyCode);
    }

    protected function seedHierarchy(int $companyId, string $currencyCode): void
    {
        // Define the entire hierarchy.
        // Each entry can have 'children' which are also accounts.
        $hierarchy = [
            // ────────────────────────────────────────────────────────
            // 1. ASSETS
            // ────────────────────────────────────────────────────────
            [
                'code' => '1000', 'name' => 'Current Assets', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset,
                'children' => [
                    [
                        'code' => '1100', 'name' => 'Cash & Bank', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset,
                        'children' => [
                            ['code' => '1110', 'name' => 'Cash in Hand', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset, 'system_role' => SystemRole::Cash],
                            ['code' => '1120', 'name' => 'Petty Cash', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset],
                            ['code' => '1130', 'name' => 'Current Accounts', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset, 'system_role' => SystemRole::Bank],
                            ['code' => '1140', 'name' => 'Savings Accounts', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset, 'system_role' => SystemRole::Bank],
                            ['code' => '1150', 'name' => 'Fixed Deposits (<1 year)', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset],
                        ]
                    ],
                    [
                        'code' => '1200', 'name' => 'Trade Receivables', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset, 'is_control_account' => true, 'system_role' => SystemRole::TradeReceivable,
                        'children' => [
                            ['code' => '1210', 'name' => 'Debtors', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset],
                            ['code' => '1220', 'name' => 'Bills Receivable', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset],
                        ]
                    ],
                    [
                        'code' => '1300', 'name' => 'Tax Assets', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::GSTAsset,
                        'children' => [
                            ['code' => '1310', 'name' => 'Input CGST', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::GSTAsset, 'system_role' => SystemRole::GstInput],
                            ['code' => '1320', 'name' => 'Input SGST', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::GSTAsset, 'system_role' => SystemRole::GstInput],
                            ['code' => '1330', 'name' => 'Input IGST', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::GSTAsset, 'system_role' => SystemRole::GstInput],
                            ['code' => '1340', 'name' => 'Input Cess', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::GSTAsset, 'system_role' => SystemRole::GstInput],
                            ['code' => '1350', 'name' => 'TDS Receivable', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::OtherAsset],
                            ['code' => '1360', 'name' => 'Income Tax Refund Receivable', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::OtherAsset],
                        ]
                    ],
                    [
                        'code' => '1400', 'name' => 'Inventory', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset, 'is_control_account' => true, 'system_role' => SystemRole::Inventory,
                        'children' => [
                            ['code' => '1410', 'name' => 'Raw Materials', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset],
                            ['code' => '1420', 'name' => 'Work In Progress', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset],
                            ['code' => '1430', 'name' => 'Finished Goods', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset],
                            ['code' => '1440', 'name' => 'Stock in Transit', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset],
                        ]
                    ],
                    [
                        'code' => '1500', 'name' => 'Advances', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset,
                        'children' => [
                            ['code' => '1510', 'name' => 'Advance to Suppliers', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset],
                            ['code' => '1520', 'name' => 'Employee Advances', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset],
                            ['code' => '1530', 'name' => 'Security Deposits', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::CurrentAsset],
                        ]
                    ],
                ]
            ],
            [
                'code' => '1600', 'name' => 'Fixed Assets', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset,
                'children' => [
                    ['code' => '1610', 'name' => 'Land', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset],
                    ['code' => '1620', 'name' => 'Buildings', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset],
                    ['code' => '1630', 'name' => 'Furniture & Fixtures', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset],
                    ['code' => '1640', 'name' => 'Computers', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset],
                    ['code' => '1650', 'name' => 'Office Equipment', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset],
                    ['code' => '1660', 'name' => 'Vehicles', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset],
                    ['code' => '1670', 'name' => 'Plant & Machinery', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset],
                ]
            ],
            [
                'code' => '1700', 'name' => 'Intangible Assets', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset,
                'children' => [
                    ['code' => '1710', 'name' => 'Software', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset],
                    ['code' => '1720', 'name' => 'Website Development', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset],
                    ['code' => '1730', 'name' => 'Trademarks', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset],
                ]
            ],
            [
                'code' => '1800', 'name' => 'Accumulated Depreciation', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset,
                'children' => [
                    ['code' => '1810', 'name' => 'Building Depreciation', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset],
                    ['code' => '1820', 'name' => 'Vehicle Depreciation', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset],
                    ['code' => '1830', 'name' => 'Computer Depreciation', 'type' => AccountType::Asset, 'reporting_class' => ReportingClass::FixedAsset],
                ]
            ],

            // ────────────────────────────────────────────────────────
            // 2. LIABILITIES
            // ────────────────────────────────────────────────────────
            [
                'code' => '2100', 'name' => 'Trade Payables', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability, 'is_control_account' => true, 'system_role' => SystemRole::TradePayable,
                'children' => [
                    ['code' => '2110', 'name' => 'Creditors', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability],
                    ['code' => '2120', 'name' => 'Bills Payable', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability],
                ]
            ],
            [
                'code' => '2200', 'name' => 'GST Liabilities', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::GSTLiability,
                'children' => [
                    ['code' => '2210', 'name' => 'Output CGST', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::GSTLiability, 'system_role' => SystemRole::GstOutput],
                    ['code' => '2220', 'name' => 'Output SGST', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::GSTLiability, 'system_role' => SystemRole::GstOutput],
                    ['code' => '2230', 'name' => 'Output IGST', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::GSTLiability, 'system_role' => SystemRole::GstOutput],
                    ['code' => '2240', 'name' => 'Output Cess', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::GSTLiability, 'system_role' => SystemRole::GstOutput],
                ]
            ],
            [
                'code' => '2300', 'name' => 'Statutory Dues', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability,
                'children' => [
                    ['code' => '2310', 'name' => 'TDS Payable', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability],
                    ['code' => '2320', 'name' => 'TCS Payable', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability],
                    ['code' => '2330', 'name' => 'PF Payable', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability],
                    ['code' => '2340', 'name' => 'ESI Payable', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability],
                    ['code' => '2350', 'name' => 'Professional Tax Payable', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability],
                    ['code' => '2360', 'name' => 'Labour Welfare Fund Payable', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability],
                ]
            ],
            [
                'code' => '2400', 'name' => 'Accruals', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability,
                'children' => [
                    ['code' => '2410', 'name' => 'Salary Payable', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability],
                    ['code' => '2420', 'name' => 'Rent Payable', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability],
                    ['code' => '2430', 'name' => 'Interest Payable', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability],
                ]
            ],
            [
                'code' => '2500', 'name' => 'Customer Advances', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability,
                'children' => [
                    ['code' => '2510', 'name' => 'Advance from Customers', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::CurrentLiability],
                ]
            ],
            [
                'code' => '2600', 'name' => 'Loans', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::LongTermLiability,
                'children' => [
                    ['code' => '2610', 'name' => 'Bank Loans', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::LongTermLiability],
                    ['code' => '2620', 'name' => 'Vehicle Loans', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::LongTermLiability],
                    ['code' => '2630', 'name' => 'Mortgage Loans', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::LongTermLiability],
                    ['code' => '2640', 'name' => 'Unsecured Loans', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::LongTermLiability],
                    ['code' => '2650', 'name' => 'Director\'s Loan', 'type' => AccountType::Liability, 'reporting_class' => ReportingClass::LongTermLiability],
                ]
            ],

            // ────────────────────────────────────────────────────────
            // 3. EQUITY
            // ────────────────────────────────────────────────────────
            [
                'code' => '3100', 'name' => 'Capital Account', 'type' => AccountType::Equity, 'reporting_class' => ReportingClass::Equity,
                'children' => [
                    ['code' => '3110', 'name' => 'Drawings', 'type' => AccountType::Equity, 'reporting_class' => ReportingClass::Equity],
                    ['code' => '3120', 'name' => 'Current Year Profit/Loss', 'type' => AccountType::Equity, 'reporting_class' => ReportingClass::Equity],
                    ['code' => '3130', 'name' => 'Retained Earnings', 'type' => AccountType::Equity, 'reporting_class' => ReportingClass::Equity, 'system_role' => SystemRole::RetainedEarnings],
                ]
            ],

            // ────────────────────────────────────────────────────────
            // 4. REVENUE
            // ────────────────────────────────────────────────────────
            [
                'code' => '4100', 'name' => 'Sales Revenue', 'type' => AccountType::Revenue, 'reporting_class' => ReportingClass::Revenue,
                'children' => [
                    ['code' => '4110', 'name' => 'Domestic Goods Sales', 'type' => AccountType::Revenue, 'reporting_class' => ReportingClass::Revenue],
                    ['code' => '4120', 'name' => 'Export Sales', 'type' => AccountType::Revenue, 'reporting_class' => ReportingClass::Revenue],
                    ['code' => '4130', 'name' => 'Service Revenue', 'type' => AccountType::Revenue, 'reporting_class' => ReportingClass::Revenue],
                    ['code' => '4140', 'name' => 'Job Work Revenue', 'type' => AccountType::Revenue, 'reporting_class' => ReportingClass::Revenue],
                ]
            ],
            [
                'code' => '4200', 'name' => 'Other Operating Revenue', 'type' => AccountType::Revenue, 'reporting_class' => ReportingClass::Revenue,
                'children' => [
                    ['code' => '4210', 'name' => 'Scrap Sales', 'type' => AccountType::Revenue, 'reporting_class' => ReportingClass::Revenue],
                    ['code' => '4220', 'name' => 'Commission Income', 'type' => AccountType::Revenue, 'reporting_class' => ReportingClass::Revenue],
                ]
            ],
            [
                'code' => '4300', 'name' => 'Non-Operating Revenue', 'type' => AccountType::Revenue, 'reporting_class' => ReportingClass::Revenue,
                'children' => [
                    ['code' => '4310', 'name' => 'Interest Income', 'type' => AccountType::Revenue, 'reporting_class' => ReportingClass::Revenue],
                    ['code' => '4320', 'name' => 'Dividend Income', 'type' => AccountType::Revenue, 'reporting_class' => ReportingClass::Revenue],
                    ['code' => '4330', 'name' => 'Rental Income', 'type' => AccountType::Revenue, 'reporting_class' => ReportingClass::Revenue],
                    ['code' => '4340', 'name' => 'Profit on Asset Sale', 'type' => AccountType::Revenue, 'reporting_class' => ReportingClass::Revenue],
                    ['code' => '4350', 'name' => 'Discount Received', 'type' => AccountType::Revenue, 'reporting_class' => ReportingClass::Revenue],
                ]
            ],

            // ────────────────────────────────────────────────────────
            // 5. COST OF GOODS SOLD
            // ────────────────────────────────────────────────────────
            [
                'code' => '5100', 'name' => 'Purchases', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::COGS,
                'children' => [
                    ['code' => '5110', 'name' => 'Raw Material Purchases', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::COGS],
                    ['code' => '5120', 'name' => 'Finished Goods Purchases', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::COGS],
                ]
            ],
            [
                'code' => '5200', 'name' => 'Direct Costs', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::COGS,
                'children' => [
                    ['code' => '5210', 'name' => 'Freight Inward', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::COGS],
                    ['code' => '5220', 'name' => 'Customs Duty', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::COGS],
                    ['code' => '5230', 'name' => 'Direct Labour', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::COGS],
                ]
            ],
            [
                'code' => '5300', 'name' => 'Inventory Adjustments', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::COGS,
                'children' => [
                    ['code' => '5310', 'name' => 'Opening Stock', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::COGS],
                    ['code' => '5320', 'name' => 'Closing Stock', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::COGS],
                ]
            ],

            // ────────────────────────────────────────────────────────
            // 6. OPERATING EXPENSES
            // ────────────────────────────────────────────────────────
            [
                'code' => '6100', 'name' => 'Salaries & Benefits', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense,
                'children' => [
                    ['code' => '6110', 'name' => 'Salaries', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6120', 'name' => 'Bonus', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6130', 'name' => 'Overtime', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6140', 'name' => 'Employer PF', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6150', 'name' => 'Employer ESI', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6160', 'name' => 'Staff Welfare', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                ]
            ],
            [
                'code' => '6200', 'name' => 'Administration', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense,
                'children' => [
                    ['code' => '6210', 'name' => 'Rent', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6220', 'name' => 'Electricity', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6230', 'name' => 'Internet', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6240', 'name' => 'Telephone', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6250', 'name' => 'Office Expenses', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6260', 'name' => 'Printing & Stationery', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6270', 'name' => 'Legal Fees', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6280', 'name' => 'Audit Fees', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6290', 'name' => 'Professional Fees', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                ]
            ],
            [
                'code' => '6300', 'name' => 'Selling Expenses', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense,
                'children' => [
                    ['code' => '6310', 'name' => 'Advertising', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6320', 'name' => 'Sales Commission', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6330', 'name' => 'Travel', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6340', 'name' => 'Promotion Expenses', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                ]
            ],
            [
                'code' => '6400', 'name' => 'Vehicle Expenses', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense,
                'children' => [
                    ['code' => '6410', 'name' => 'Fuel', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6420', 'name' => 'Repairs', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6430', 'name' => 'Insurance', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '6440', 'name' => 'Toll Charges', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                ]
            ],

            // ────────────────────────────────────────────────────────
            // 7. FINANCE COSTS
            // ────────────────────────────────────────────────────────
            [
                'code' => '8100', 'name' => 'Interest Expenses', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::FinanceCost,
                'children' => [
                    ['code' => '8110', 'name' => 'Bank Loan Interest', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::FinanceCost],
                    ['code' => '8120', 'name' => 'Vehicle Loan Interest', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::FinanceCost],
                ]
            ],
            [
                'code' => '8200', 'name' => 'Banking Charges', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::FinanceCost,
                'children' => [
                    ['code' => '8210', 'name' => 'Bank Charges', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::FinanceCost],
                    ['code' => '8220', 'name' => 'Payment Gateway Charges', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::FinanceCost],
                    ['code' => '8230', 'name' => 'Cheque Bounce Charges', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::FinanceCost],
                ]
            ],

            // ────────────────────────────────────────────────────────
            // 8. DEPRECIATION & AMORTIZATION
            // ────────────────────────────────────────────────────────
            [
                'code' => '8500', 'name' => 'Depreciation & Amortization', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense,
                'children' => [
                    ['code' => '8510', 'name' => 'Building Depreciation', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '8520', 'name' => 'Vehicle Depreciation', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '8530', 'name' => 'Computer Depreciation', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '8540', 'name' => 'Furniture Depreciation', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                    ['code' => '8550', 'name' => 'Software Amortization', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OperatingExpense],
                ]
            ],

            // ────────────────────────────────────────────────────────
            // 9. EXCEPTIONAL ACCOUNTS
            // ────────────────────────────────────────────────────────
            [
                'code' => '9000', 'name' => 'Exceptional Accounts', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OtherExpense,
                'children' => [
                    ['code' => '9100', 'name' => 'Prior Period Adjustments', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OtherExpense],
                    ['code' => '9200', 'name' => 'Extraordinary Losses', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OtherExpense],
                    ['code' => '9300', 'name' => 'Extraordinary Gains', 'type' => AccountType::Revenue, 'reporting_class' => ReportingClass::Revenue],
                    ['code' => '9400', 'name' => 'Discount Given', 'type' => AccountType::Expense, 'reporting_class' => ReportingClass::OtherExpense],
                ]
            ]
        ];

        $this->createAccounts($companyId, $currencyCode, $hierarchy);
    }

    protected function createAccounts(int $companyId, string $currencyCode, array $accounts, ?int $parentId = null): void
    {
        foreach ($accounts as $data) {
            $children = $data['children'] ?? [];
            unset($data['children']);

            $account = Account::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'code' => $data['code'],
                ],
                array_merge($data, [
                    'company_id' => $companyId,
                    'parent_id' => $parentId,
                    'currency_code' => $currencyCode,
                    'default' => $data['default'] ?? false,
                    'is_control_account' => $data['is_control_account'] ?? false,
                ])
            );

            if (!empty($children)) {
                $this->createAccounts($companyId, $currencyCode, $children, $account->id);
            }
        }
    }
}
