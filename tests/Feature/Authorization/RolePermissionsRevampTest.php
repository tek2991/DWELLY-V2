<?php

namespace Tests\Feature\Authorization;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Auth\Services\PermissionCatalog;
use App\Filament\Resources\Administration\RoleResource\Pages\CreateRole;
use App\Filament\Resources\Administration\RoleResource\Pages\EditRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RolePermissionsRevampTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create(['name' => 'Super Admin']);
        $this->owner->assignRole(RoleName::BUSINESS_OWNER);
    }

    public function test_permission_catalog_contains_complete_metadata_and_categories(): void
    {
        $permissions = PermissionCatalog::getPermissions();
        $categories = PermissionCatalog::getCategories();
        $grouped = PermissionCatalog::getGroupedPermissions();

        // Must have all 131 defined permissions
        $this->assertCount(131, $permissions);

        // All permissions must have required fields
        foreach ($permissions as $code => $meta) {
            $this->assertSame($code, $meta['code']);
            $this->assertNotEmpty($meta['label'], "Permission {$code} must have a label");
            $this->assertNotEmpty($meta['description'], "Permission {$code} must have a description");
            $this->assertArrayHasKey($meta['category'], $categories, "Permission {$code} references invalid category {$meta['category']}");
            $this->assertContains($meta['risk'], ['read', 'action', 'sensitive', 'fiduciary', 'destructive'], "Permission {$code} has invalid risk {$meta['risk']}");
        }

        // Must have all 13 categories
        $this->assertCount(13, $categories);

        // Grouped output must include all permissions without omission
        $totalGroupedCount = 0;
        foreach ($grouped as $catGroup) {
            $totalGroupedCount += count($catGroup['permissions']);
        }
        $this->assertSame(131, $totalGroupedCount);
    }

    public function test_permission_catalog_provides_valid_defaults_for_all_seven_system_roles(): void
    {
        foreach (RoleName::cases() as $roleCase) {
            $defaults = PermissionCatalog::getDefaultsForRole($roleCase);
            $this->assertNotEmpty($defaults, "Default permissions for role {$roleCase->value} must not be empty");

            // Verify that all default permission strings exist in the catalog
            foreach ($defaults as $permName) {
                $this->assertArrayHasKey(
                    $permName,
                    PermissionCatalog::getPermissions(),
                    "Role {$roleCase->value} references uncataloged permission {$permName}"
                );
            }
        }

        // Business owner must have all 131 permissions
        $ownerDefaults = PermissionCatalog::getDefaultsForRole(RoleName::BUSINESS_OWNER);
        $this->assertCount(131, $ownerDefaults);

        // City manager must NOT have fiduciary disbursement or system security administration
        $cityManagerDefaults = PermissionCatalog::getDefaultsForRole(RoleName::CITY_MANAGER);
        $this->assertNotContains('payout.disburse', $cityManagerDefaults);
        $this->assertNotContains('deboarding.refund.disburse', $cityManagerDefaults);
        $this->assertNotContains('admin.roles.assign', $cityManagerDefaults);
        $this->assertNotContains('accounting.journal.post', $cityManagerDefaults);

        // Accountant MUST have fiduciary disbursement and general ledger permissions
        $accountantDefaults = PermissionCatalog::getDefaultsForRole(RoleName::ACCOUNTANT);
        $this->assertContains('payout.disburse', $accountantDefaults);
        $this->assertContains('deboarding.refund.disburse', $accountantDefaults);
        $this->assertContains('accounting.journal.post', $accountantDefaults);
    }

    public function test_system_role_permissions_can_be_reset_to_defaults_via_edit_role_action(): void
    {
        $this->actingAs($this->owner);

        /** @var Role $cityManagerRole */
        $cityManagerRole = Role::where('name', RoleName::CITY_MANAGER->value)->firstOrFail();
        $expectedDefaults = PermissionCatalog::getDefaultsForRole(RoleName::CITY_MANAGER);

        // Corrupt/modify the permissions: strip all and add only 1 permission
        $cityManagerRole->syncPermissions(['property.viewAny']);
        $this->assertCount(1, $cityManagerRole->fresh()->permissions);

        // Mount EditRole page and trigger the 'resetDefaults' header action
        Livewire::test(EditRole::class, ['record' => $cityManagerRole->id])
            ->assertActionVisible('resetDefaults')
            ->callAction('resetDefaults')
            ->assertNotified();

        // Verify that permissions are completely restored
        $freshPermissions = $cityManagerRole->fresh()->permissions->pluck('name')->all();
        sort($freshPermissions);
        sort($expectedDefaults);

        $this->assertEquals($expectedDefaults, $freshPermissions);
    }

    public function test_reset_defaults_action_is_hidden_for_custom_non_system_roles(): void
    {
        $this->actingAs($this->owner);

        $customRole = Role::create([
            'name' => 'custom-field-assistant',
            'display_name' => 'Custom Field Assistant',
            'guard_name' => 'web',
            'is_system' => false,
        ]);

        Livewire::test(EditRole::class, ['record' => $customRole->id])
            ->assertActionHidden('resetDefaults')
            ->assertActionVisible('delete');
    }

    public function test_role_permissions_can_be_updated_and_saved_on_custom_role(): void
    {
        $this->actingAs($this->owner);

        $customRole = Role::create([
            'name' => 'junior-leasing-officer',
            'display_name' => 'Junior Leasing Officer',
            'guard_name' => 'web',
            'is_system' => false,
        ]);
        $customRole->syncPermissions(['agreement.viewAny']);

        $selectedPermissions = [
            'agreement.viewAny',
            'agreement.view',
            'agreement.create',
            'property.viewAny',
            'property.view',
        ];

        Livewire::test(EditRole::class, ['record' => $customRole->id])
            ->fillForm([
                'display_name' => 'Junior Leasing Associate',
                'description' => 'Handles preliminary inquiries and property showings.',
                'permissions' => $selectedPermissions,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $savedPermissions = $customRole->fresh()->permissions->pluck('name')->all();
        sort($savedPermissions);
        sort($selectedPermissions);

        $this->assertEquals($selectedPermissions, $savedPermissions);
        $this->assertSame('Junior Leasing Associate', $customRole->fresh()->display_name);
    }

    public function test_new_role_can_be_created_with_permissions_matrix(): void
    {
        $this->actingAs($this->owner);

        $newPermissions = [
            'opportunity.access',
            'opportunity.viewAny',
            'opportunity.view',
            'opportunity.create',
        ];

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'supply-intern',
                'display_name' => 'Supply Intern',
                'description' => 'Assists with owner pipeline qualification.',
                'permissions' => $newPermissions,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var Role $createdRole */
        $createdRole = Role::where('name', 'supply-intern')->firstOrFail();
        $this->assertFalse($createdRole->isSystem());
        $this->assertSame('Supply Intern', $createdRole->display_name);

        $assignedPermissions = $createdRole->permissions->pluck('name')->all();
        sort($assignedPermissions);
        sort($newPermissions);

        $this->assertEquals($newPermissions, $assignedPermissions);
    }
}
