<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Seeds foundational system data, then seeds the full demo suite.
     */
    public function run(): void
    {
        $this->call([
            SystemBaseSeeder::class,
            \Tek2991\Accounting\Database\Seeders\DemoDataSeeder::class,
            TenantSeeder::class,
            PropertySeeder::class,
            MaintenanceDemoSeeder::class,
        ]);
    }
}
