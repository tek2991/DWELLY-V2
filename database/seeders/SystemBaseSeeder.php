<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SystemBaseSeeder extends Seeder
{
    /**
     * Seed foundational reference and system data required by the application.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            \Tek2991\Accounting\Database\Seeders\IndianStatesSeeder::class,
            RegionsSeeder::class,
            \Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder::class,
            \Tek2991\Accounting\Database\Seeders\DemoTaxesSeeder::class,
            UserSeeder::class,
            ReferenceDataSeeder::class,
            OpportunitySourceSeeder::class,
            FinancialModelSeeder::class,
            KycDocumentTypeSeeder::class,
            EstablishmentSeeder::class,
            TaskTemplateSeeder::class,
        ]);
    }
}
