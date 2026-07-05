<?php

namespace Tek2991\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\BankAccountType;
use Tek2991\Accounting\Enums\ReportingClass;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\BankAccount;
use App\Models\Branch;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::where('code', 'GHY')->first();

        // 1. Chart of Accounts for Bank Accounts
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

        // 2. Respective Bank Accounts
        if ($branch) {
            BankAccount::firstOrCreate([
                'branch_id' => $branch->id,
                'account_id' => $hdfcCurrentAccount->id,
            ], [
                'type' => BankAccountType::Depository,
                'nickname' => 'HDFC Current',
                'number' => '1234567890',
                'enabled' => true,
            ]);

            BankAccount::firstOrCreate([
                'branch_id' => $branch->id,
                'account_id' => $sbiCurrentAccount->id,
            ], [
                'type' => BankAccountType::Depository,
                'nickname' => 'SBI Current',
                'number' => '0987654321',
                'enabled' => true,
            ]);

            BankAccount::firstOrCreate([
                'branch_id' => $branch->id,
                'account_id' => $iciciSavingsAccount->id,
            ], [
                'type' => BankAccountType::Depository,
                'nickname' => 'ICICI Savings',
                'number' => '1122334455',
                'enabled' => true,
            ]);
        }
    }
}
