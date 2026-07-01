<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define core modules
        $modules = [
            'property', 'party', 'task', 'maintenance', 
            'finance', 'utility', 'agreement', 'document', 
            'communication', 'accounting', 'administration'
        ];

        // Create module.access permissions
        foreach ($modules as $module) {
            Permission::firstOrCreate(['name' => "{$module}.access", 'guard_name' => 'web']);
        }

        // Define Roles
        $owner = Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $manager = Role::firstOrCreate(['name' => 'Operations Manager', 'guard_name' => 'web']);
        $executive = Role::firstOrCreate(['name' => 'Operations Executive', 'guard_name' => 'web']);
        $accountant = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);

        // Assign all module access to Owner and Manager
        foreach ($modules as $module) {
            $owner->givePermissionTo("{$module}.access");
            $manager->givePermissionTo("{$module}.access");
        }

        // Executive gets access to operations modules, but not finance or administration
        $executiveModules = ['property', 'party', 'task', 'maintenance', 'agreement', 'document', 'communication'];
        foreach ($executiveModules as $module) {
            $executive->givePermissionTo("{$module}.access");
        }

        // Accountant gets access to finance, utility, and accounting
        $accountantModules = ['party', 'finance', 'utility', 'accounting'];
        foreach ($accountantModules as $module) {
            $accountant->givePermissionTo("{$module}.access");
        }
    }
}
