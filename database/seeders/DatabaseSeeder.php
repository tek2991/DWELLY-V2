<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'admin@dwelly.in'],
            [
                'name' => 'Dwelly Admin',
                'password' => bcrypt('password'),
            ]
        );

        $this->call([
            RolesAndPermissionsSeeder::class,
            ReferenceDataSeeder::class,
            RegionsSeeder::class,
            \Tek2991\Accounting\Database\Seeders\IndianStatesSeeder::class,
            \Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder::class,
            \Tek2991\Accounting\Database\Seeders\DemoDataSeeder::class,
        ]);

        $user->assignRole('Business Owner');
    }
}
