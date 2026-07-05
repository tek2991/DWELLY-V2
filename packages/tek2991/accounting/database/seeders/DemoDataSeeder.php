<?php

namespace Tek2991\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // You can easily comment out any module you don't want to seed
            $this->call([
                DemoOrganizationSeeder::class,
                DemoAccountsSeeder::class,
                // DemoContactsSeeder::class,
                DemoTaxesSeeder::class,
                // DemoItemsSeeder::class,
                // DemoTransactionsSeeder::class,
            ]);
        });
    }
}
