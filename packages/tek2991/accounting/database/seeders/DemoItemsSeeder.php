<?php

namespace Tek2991\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\ReportingClass;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Item;

class DemoItemsSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('en_IN');

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
        
        foreach ($itemNames as $i => $name) {
            Item::firstOrCreate([
                'name' => $name,
            ], [
                'type' => \Tek2991\Accounting\Enums\ItemType::Services,
                'sku' => 'SRV-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
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
    }
}
