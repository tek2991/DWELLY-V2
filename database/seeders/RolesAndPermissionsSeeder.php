<?php

namespace Database\Seeders;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Auth\Services\PermissionCatalog;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Register all permissions defined in PermissionCatalog
        foreach (array_keys(PermissionCatalog::getPermissions()) as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // 2. Define the 7 Core Roles
        $owner = Role::firstOrCreate(
            ['name' => RoleName::BUSINESS_OWNER->value, 'guard_name' => 'web'],
            [
                'display_name' => RoleName::BUSINESS_OWNER->label(),
                'description' => RoleName::BUSINESS_OWNER->description(),
                'is_system' => true,
            ]
        );
        $owner->update([
            'display_name' => RoleName::BUSINESS_OWNER->label(),
            'description' => RoleName::BUSINESS_OWNER->description(),
            'is_system' => true,
        ]);

        $cityManager = Role::firstOrCreate(
            ['name' => RoleName::CITY_MANAGER->value, 'guard_name' => 'web'],
            [
                'display_name' => RoleName::CITY_MANAGER->label(),
                'description' => RoleName::CITY_MANAGER->description(),
                'is_system' => true,
            ]
        );
        $cityManager->update([
            'display_name' => RoleName::CITY_MANAGER->label(),
            'description' => RoleName::CITY_MANAGER->description(),
            'is_system' => true,
        ]);

        $supplyManager = Role::firstOrCreate(
            ['name' => RoleName::SUPPLY_MANAGER->value, 'guard_name' => 'web'],
            [
                'display_name' => RoleName::SUPPLY_MANAGER->label(),
                'description' => RoleName::SUPPLY_MANAGER->description(),
                'is_system' => true,
            ]
        );
        $supplyManager->update([
            'display_name' => RoleName::SUPPLY_MANAGER->label(),
            'description' => RoleName::SUPPLY_MANAGER->description(),
            'is_system' => true,
        ]);

        $demandManager = Role::firstOrCreate(
            ['name' => RoleName::DEMAND_MANAGER->value, 'guard_name' => 'web'],
            [
                'display_name' => RoleName::DEMAND_MANAGER->label(),
                'description' => RoleName::DEMAND_MANAGER->description(),
                'is_system' => true,
            ]
        );
        $demandManager->update([
            'display_name' => RoleName::DEMAND_MANAGER->label(),
            'description' => RoleName::DEMAND_MANAGER->description(),
            'is_system' => true,
        ]);

        $opsManager = Role::firstOrCreate(
            ['name' => RoleName::OPERATIONS_MANAGER->value, 'guard_name' => 'web'],
            [
                'display_name' => RoleName::OPERATIONS_MANAGER->label(),
                'description' => RoleName::OPERATIONS_MANAGER->description(),
                'is_system' => true,
            ]
        );
        $opsManager->update([
            'display_name' => RoleName::OPERATIONS_MANAGER->label(),
            'description' => RoleName::OPERATIONS_MANAGER->description(),
            'is_system' => true,
        ]);

        $opsExecutive = Role::firstOrCreate(
            ['name' => RoleName::OPERATIONS_EXECUTIVE->value, 'guard_name' => 'web'],
            [
                'display_name' => RoleName::OPERATIONS_EXECUTIVE->label(),
                'description' => RoleName::OPERATIONS_EXECUTIVE->description(),
                'is_system' => true,
            ]
        );
        $opsExecutive->update([
            'display_name' => RoleName::OPERATIONS_EXECUTIVE->label(),
            'description' => RoleName::OPERATIONS_EXECUTIVE->description(),
            'is_system' => true,
        ]);

        $accountant = Role::firstOrCreate(
            ['name' => RoleName::ACCOUNTANT->value, 'guard_name' => 'web'],
            [
                'display_name' => RoleName::ACCOUNTANT->label(),
                'description' => RoleName::ACCOUNTANT->description(),
                'is_system' => true,
            ]
        );
        $accountant->update([
            'display_name' => RoleName::ACCOUNTANT->label(),
            'description' => RoleName::ACCOUNTANT->description(),
            'is_system' => true,
        ]);

        // 3. Sync Default Factory Permissions for System Roles from PermissionCatalog
        $owner->syncPermissions(PermissionCatalog::getDefaultsForRole(RoleName::BUSINESS_OWNER));
        $cityManager->syncPermissions(PermissionCatalog::getDefaultsForRole(RoleName::CITY_MANAGER));
        $supplyManager->syncPermissions(PermissionCatalog::getDefaultsForRole(RoleName::SUPPLY_MANAGER));
        $demandManager->syncPermissions(PermissionCatalog::getDefaultsForRole(RoleName::DEMAND_MANAGER));
        $opsManager->syncPermissions(PermissionCatalog::getDefaultsForRole(RoleName::OPERATIONS_MANAGER));
        $opsExecutive->syncPermissions(PermissionCatalog::getDefaultsForRole(RoleName::OPERATIONS_EXECUTIVE));
        $accountant->syncPermissions(PermissionCatalog::getDefaultsForRole(RoleName::ACCOUNTANT));
    }
}
