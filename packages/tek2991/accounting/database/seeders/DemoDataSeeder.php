<?php

namespace Tek2991\Accounting\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\BankAccountType;
use Tek2991\Accounting\Enums\ContactType;
use Tek2991\Accounting\Enums\ReportingClass;
use Tek2991\Accounting\Enums\DocumentLineType;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\BankAccount;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\FiscalPeriod;
use Tek2991\Accounting\Models\Tax;
use Tek2991\Accounting\Models\Item;
use Tek2991\Accounting\Services\InvoiceService;
use Tek2991\Accounting\Services\BillService;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $faker = Faker::create('en_IN');

            // 1. Organization & Branches
            $org = \Tek2991\Accounting\Models\Organization::current();
            $org->update(['tax_regime' => \Tek2991\Accounting\Enums\TaxRegimeType::IndiaGst]);

            $assam = \Tek2991\Accounting\Models\State::where('name', 'Assam')->first();
            $maharashtra = \Tek2991\Accounting\Models\State::where('name', 'Maharashtra')->first();
            $delhi = \Tek2991\Accounting\Models\State::where('name', 'Delhi')->first();
            $statesForSupply = collect([$assam, $assam, $assam, $maharashtra, $delhi])->filter()->values();

            $gstRegistration = \Tek2991\Accounting\Models\GstRegistration::create([
                'gstin' => '18AAAAA0000A1Z5',
                'legal_name' => 'Dwelly India Pvt Ltd',
                'state_id' => $assam?->id,
                'is_default' => true,
            ]);

            $karnataka = \Tek2991\Accounting\Models\State::where('name', 'Karnataka')->first();

            $branch = \App\Models\Branch::create([
                'organization_id' => $org->id,
                'gst_registration_id' => $gstRegistration->id,
                'name' => 'Guwahati Head Office',
                'code' => 'GHY',
                'city' => 'Guwahati',
                'state_id' => $assam?->id,
                'is_active' => true,
            ]);

            $bangaloreBranch = \App\Models\Branch::create([
                'organization_id' => $org->id,
                'gst_registration_id' => $gstRegistration->id,
                'name' => 'Bangalore Branch',
                'code' => 'BLR',
                'city' => 'Bangalore',
                'state_id' => $karnataka?->id ?? $maharashtra?->id,
                'is_active' => true,
            ]);
            
            $branches = [$branch, $bangaloreBranch];
            
            $branchContext = app(\Tek2991\Accounting\Services\BranchContext::class);
            $branchContext->set($branch);

            // 1. Fiscal Periods (Previous, Current)
            $startMonth = \Tek2991\Accounting\Facades\Accounting::getFiscalYearStart();
            $currentYear = now()->year;
            $startYear = now()->month >= $startMonth ? $currentYear : $currentYear - 1;
            
            $startDate = Carbon::create($startYear, $startMonth, 1)->startOfDay();
            $endDate = $startDate->copy()->addYear()->subDay()->endOfDay();

            // Current FY
            FiscalPeriod::firstOrCreate([
                'name' => "FY {$startYear}-" . substr($startYear + 1, 2),
            ], [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            // Previous FY
            $prevStartDate = $startDate->copy()->subYear();
            $prevEndDate = $endDate->copy()->subYear();
            FiscalPeriod::firstOrCreate([
                'name' => "FY " . ($startYear - 1) . "-" . substr($startYear, 2),
            ], [
                'start_date' => $prevStartDate,
                'end_date' => $prevEndDate,
            ]);

            // 2. Chart of Accounts for Bank Accounts
            $hdfcCurrentAccount = Account::firstOrCreate([
                'name' => 'HDFC Current Account',
            ], [
                'type' => AccountType::Asset,
                'reporting_class' => ReportingClass::CurrentAsset,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $sbiCurrentAccount = Account::firstOrCreate([
                'name' => 'SBI Current Account',
            ], [
                'type' => AccountType::Asset,
                'reporting_class' => ReportingClass::CurrentAsset,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $iciciSavingsAccount = Account::firstOrCreate([
                'name' => 'ICICI Savings Account',
            ], [
                'type' => AccountType::Asset,
                'reporting_class' => ReportingClass::CurrentAsset,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            // 3. Respective Bank Accounts
            $bankAccounts = [];
            $bankAccounts[] = BankAccount::firstOrCreate([
                'branch_id' => $branch->id,
                'account_id' => $hdfcCurrentAccount->id,
            ], [
                'type' => BankAccountType::Depository,
                'nickname' => 'HDFC Current',
                'number' => '1234567890',
                'enabled' => true,
            ]);

            $bankAccounts[] = BankAccount::firstOrCreate([
                'branch_id' => $branch->id,
                'account_id' => $sbiCurrentAccount->id,
            ], [
                'type' => BankAccountType::Depository,
                'nickname' => 'SBI Current',
                'number' => '0987654321',
                'enabled' => true,
            ]);

            $bankAccounts[] = BankAccount::firstOrCreate([
                'branch_id' => $branch->id,
                'account_id' => $iciciSavingsAccount->id,
            ], [
                'type' => BankAccountType::Depository,
                'nickname' => 'ICICI Savings',
                'number' => '1122334455',
                'enabled' => true,
            ]);

            // 4. Contacts (25 mixed)
            $contacts = [];
            for ($i = 0; $i < 25; $i++) {
                $type = $faker->randomElement([ContactType::Customer, ContactType::Vendor, ContactType::Both]);
                $contacts[] = Contact::firstOrCreate([
                    'name' => $faker->company,
                ], [
                    'type' => $type,
                    'email' => $faker->companyEmail,
                    'phone' => $faker->phoneNumber,
                    'tax_id' => '27' . strtoupper($faker->bothify('?????####?1Z?')), // Fake GSTIN
                ]);
            }

            // Group contacts for easy access
            $customers = collect($contacts)->filter(fn($c) => in_array($c->type, [ContactType::Customer, ContactType::Both]))->values();
            $vendors = collect($contacts)->filter(fn($c) => in_array($c->type, [ContactType::Vendor, ContactType::Both]))->values();

            // 5. Taxes (GST 9% & 18%)
            $outputCgstAccount = Account::firstOrCreate([
                'name' => 'Output CGST',
            ], [
                'type' => AccountType::Liability,
                'reporting_class' => ReportingClass::CurrentLiability,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $outputSgstAccount = Account::firstOrCreate([
                'name' => 'Output SGST',
            ], [
                'type' => AccountType::Liability,
                'reporting_class' => ReportingClass::CurrentLiability,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $outputIgstAccount = Account::firstOrCreate([
                'name' => 'Output IGST',
            ], [
                'type' => AccountType::Liability,
                'reporting_class' => ReportingClass::CurrentLiability,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $inputCgstAccount = Account::firstOrCreate([
                'name' => 'Input CGST',
            ], [
                'type' => AccountType::Asset,
                'reporting_class' => ReportingClass::CurrentAsset,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $inputSgstAccount = Account::firstOrCreate([
                'name' => 'Input SGST',
            ], [
                'type' => AccountType::Asset,
                'reporting_class' => ReportingClass::CurrentAsset,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $inputIgstAccount = Account::firstOrCreate([
                'name' => 'Input IGST',
            ], [
                'type' => AccountType::Asset,
                'reporting_class' => ReportingClass::CurrentAsset,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            // GST 18%
            $gst18 = Tax::firstOrCreate([
                'name' => 'GST 18%',
            ], [
                'description' => '9% CGST + 9% SGST | 18% IGST',
                'is_active' => true,
            ]);

            if ($gst18->wasRecentlyCreated) {
                $gst18->components()->create([
                    'name' => 'CGST',
                    'rate' => 9.00,
                    'type' => \Tek2991\Accounting\Enums\TaxComponentType::Intrastate->value,
                    'sales_account_id' => $outputCgstAccount->id,
                    'purchase_account_id' => $inputCgstAccount->id,
                ]);
                $gst18->components()->create([
                    'name' => 'SGST',
                    'rate' => 9.00,
                    'type' => \Tek2991\Accounting\Enums\TaxComponentType::Intrastate->value,
                    'sales_account_id' => $outputSgstAccount->id,
                    'purchase_account_id' => $inputSgstAccount->id,
                ]);
                $gst18->components()->create([
                    'name' => 'IGST',
                    'rate' => 18.00,
                    'type' => \Tek2991\Accounting\Enums\TaxComponentType::Interstate->value,
                    'sales_account_id' => $outputIgstAccount->id,
                    'purchase_account_id' => $inputIgstAccount->id,
                ]);
            }
            
            $taxes = [$gst18];

            // 6. Items
            $salesAccount = Account::firstOrCreate([
                'name' => 'Sales Revenue',
            ], [
                'type' => AccountType::Revenue,
                'reporting_class' => ReportingClass::Revenue,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $cogsAccount = Account::firstOrCreate([
                'name' => 'Cost of Goods Sold',
            ], [
                'type' => AccountType::Expense,
                'reporting_class' => ReportingClass::COGS,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $itemNames = ['Web Development', 'SEO Optimization', 'Server Hosting', 'UI/UX Design', 'Consulting Hours', 'Software License', 'Maintenance Retainer', 'Premium Support', 'API Integration', 'Security Audit'];
            $items = [];
            foreach ($itemNames as $i => $name) {
                $items[] = Item::firstOrCreate([
                    'name' => $name,
                ], [
                    'type' => \Tek2991\Accounting\Enums\ItemType::Services,
                    'sku' => 'SRV-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'description' => $faker->sentence,
                    'hsn_sac' => '998314',
                    'income_account_id' => $salesAccount->id,
                    'expense_account_id' => $cogsAccount->id,
                    'sale_price' => $faker->numberBetween(5000, 100000),
                    'purchase_price' => $faker->numberBetween(1000, 20000),
                    'sellable' => true,
                    'purchasable' => true,
                ]);
            }

            // Services instances
            $invoiceService = app(InvoiceService::class);
            $billService = app(BillService::class);

            // 7. Generate Invoices and Bills within the current fiscal year (up to today)
            $maxDaysDiff = max(1, $startDate->diffInDays(now()));
            foreach ($branches as $currentBranch) {
                $branchContext->set($currentBranch);
                for ($i = 0; $i < 5; $i++) {
                    $date = now()->subDays(rand(0, $maxDaysDiff - 1));
                    
                    // INVOICES
                if ($customers->isNotEmpty()) {
                    $customer = $faker->randomElement($customers);
                    $invoice = $invoiceService->create($currentBranch, [
                        'contact_id' => $customer->id,
                        'issue_date' => $date->format('Y-m-d'),
                        'due_date' => $date->copy()->addDays(30)->format('Y-m-d'),
                        'place_of_supply_state_id' => $statesForSupply->isNotEmpty() ? $faker->randomElement($statesForSupply)->id : null,
                        'notes' => 'Generated by Comprehensive Demo Seeder',
                    ]);

                    $numItems = rand(1, 4);
                    for ($j = 0; $j < $numItems; $j++) {
                        $item = $faker->randomElement($items);
                        $invoice->items()->create([
                            'line_type' => DocumentLineType::Item,
                            'item_id' => $item->id,
                            'description' => $item->name,
                            'quantity' => rand(1, 10),
                            'unit_price' => $item->sale_price,
                            'tax_id' => $gst18->id,
                        ]);
                    }

                    $invoiceService->recalculateTotals($invoice);
                    $invoiceService->post($invoice);

                    // 75% chance of payment
                    if (rand(1, 100) <= 75) {
                        $paymentDate = $date->copy()->addDays(rand(1, 30));
                        if ($paymentDate->isPast()) {
                            $invoiceService->recordPayment($invoice, [
                                'payment_account_id' => $faker->randomElement($bankAccounts)->account_id,
                                'payment_date' => $paymentDate->format('Y-m-d'),
                                'amount' => $invoice->grand_total,
                                'reference' => 'PAY-' . $faker->bothify('####????'),
                            ]);
                        }
                    }
                }

                // BILLS
                if ($vendors->isNotEmpty()) {
                    $vendor = $faker->randomElement($vendors);
                    $bill = $billService->create($currentBranch, [
                        'contact_id' => $vendor->id,
                        'issue_date' => $date->format('Y-m-d'),
                        'due_date' => $date->copy()->addDays(30)->format('Y-m-d'),
                        'place_of_supply_state_id' => $statesForSupply->isNotEmpty() ? $faker->randomElement($statesForSupply)->id : null,
                        'notes' => 'Generated by Comprehensive Demo Seeder',
                    ]);

                    $numItems = rand(1, 4);
                    for ($j = 0; $j < $numItems; $j++) {
                        $item = $faker->randomElement($items);
                        $bill->items()->create([
                            'line_type' => DocumentLineType::Item,
                            'item_id' => $item->id,
                            'description' => $item->name,
                            'quantity' => rand(1, 5),
                            'unit_price' => $item->purchase_price,
                            'tax_id' => $gst18->id,
                        ]);
                    }

                    $billService->recalculateTotals($bill);
                    $billService->post($bill);

                    // 80% chance of payment
                    if (rand(1, 100) <= 80) {
                        $paymentDate = $date->copy()->addDays(rand(1, 30));
                        if ($paymentDate->isPast()) {
                            $billService->recordPayment($bill, [
                                'payment_account_id' => $faker->randomElement($bankAccounts)->account_id,
                                'payment_date' => $paymentDate->format('Y-m-d'),
                                'amount' => $bill->grand_total,
                                'reference' => 'BILL-PAY-' . $faker->bothify('####????'),
                            ]);
                        }
                    }
                }
                }
            }

        });
    }
}
