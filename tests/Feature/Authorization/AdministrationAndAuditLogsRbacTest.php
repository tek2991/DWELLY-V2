<?php

namespace Tests\Feature\Authorization;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Geographic\Models\City;
use App\Domain\Property\Models\Property;
use App\Filament\Pages\Administration\ManageFinancialSettings;
use App\Filament\Resources\Administration\AuditLogResource;
use App\Filament\Resources\Administration\RoleResource;
use App\Filament\Resources\Administration\UserResource;
use App\Filament\Resources\Geographic\Cities\CityResource;
use App\Filament\Resources\Settings\Branches\BranchResource;
use App\Filament\Resources\Settings\FinancialModelResource;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class AdministrationAndAuditLogsRbacTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $cityManager;

    protected User $supplyManager;

    protected User $demandManager;

    protected User $opsManager;

    protected User $opsExecutive;

    protected User $accountant;

    protected Branch $branch1;

    protected Branch $branch2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $org = Organization::firstOrCreate([
            'slug' => 'dwelly-inc',
        ], [
            'name' => 'Dwelly Living Private Limited',
            'legal_name' => 'Dwelly Living Private Limited',
        ]);

        $this->branch1 = Branch::create([
            'organization_id' => $org->id,
            'name' => 'North Branch',
            'code' => 'BR-01',
            'city' => 'Guwahati',
        ]);

        $this->branch2 = Branch::create([
            'organization_id' => $org->id,
            'name' => 'South Branch',
            'code' => 'BR-02',
            'city' => 'Shillong',
        ]);

        $this->owner = User::factory()->create(['name' => 'Business Owner']);
        $this->owner->assignRole(RoleName::BUSINESS_OWNER);

        $this->cityManager = User::factory()->create(['name' => 'City Manager']);
        $this->cityManager->assignRole(RoleName::CITY_MANAGER);
        $this->cityManager->branches()->attach($this->branch1);

        $this->supplyManager = User::factory()->create(['name' => 'Supply Manager']);
        $this->supplyManager->assignRole(RoleName::SUPPLY_MANAGER);

        $this->demandManager = User::factory()->create(['name' => 'Demand Manager']);
        $this->demandManager->assignRole(RoleName::DEMAND_MANAGER);

        $this->opsManager = User::factory()->create(['name' => 'Operations Manager']);
        $this->opsManager->assignRole(RoleName::OPERATIONS_MANAGER);

        $this->opsExecutive = User::factory()->create(['name' => 'Operations Executive']);
        $this->opsExecutive->assignRole(RoleName::OPERATIONS_EXECUTIVE);
        $this->opsExecutive->branches()->attach($this->branch1);

        $this->accountant = User::factory()->create(['name' => 'Accountant']);
        $this->accountant->assignRole(RoleName::ACCOUNTANT);
    }

    public function test_user_management_and_staff_creation_access_control(): void
    {
        // 1. Business Owner has full authority
        $this->actingAs($this->owner);
        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(UserResource::canCreate());
        $this->assertTrue(UserResource::canEdit($this->opsExecutive));
        $this->assertTrue(UserResource::canDelete($this->opsExecutive));
        $this->assertTrue(UserResource::canDeleteAny());
        $this->assertTrue(Gate::allows('assignRoles', User::class));

        // 2. City Manager can view, create field staff, and update field staff in branch
        $this->actingAs($this->cityManager);
        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(UserResource::canCreate());
        $this->assertTrue(UserResource::canEdit($this->opsExecutive));

        // City Manager CANNOT delete users
        $this->assertFalse(UserResource::canDelete($this->opsExecutive));
        $this->assertFalse(UserResource::canDeleteAny());

        // City Manager CANNOT update Business Owner or another City Manager
        $anotherCityManager = User::factory()->create();
        $anotherCityManager->assignRole(RoleName::CITY_MANAGER);
        $this->assertFalse(UserResource::canEdit($this->owner));
        $this->assertFalse(UserResource::canEdit($anotherCityManager));

        // City Manager CANNOT update user in another branch
        $otherBranchStaff = User::factory()->create();
        $otherBranchStaff->assignRole(RoleName::OPERATIONS_EXECUTIVE);
        $otherBranchStaff->branches()->attach($this->branch2);
        $this->assertFalse(UserResource::canEdit($otherBranchStaff));

        // City Manager CANNOT assign arbitrary roles
        $this->assertFalse(Gate::allows('assignRoles', User::class));

        // 3. Other roles have zero access to User Management
        $isolatedRoles = [$this->supplyManager, $this->demandManager, $this->opsManager, $this->opsExecutive, $this->accountant];
        foreach ($isolatedRoles as $roleUser) {
            $this->actingAs($roleUser);
            $this->assertFalse(UserResource::canViewAny(), "Role {$roleUser->getRoleNames()->first()} should not view users");
            $this->assertFalse(UserResource::canCreate(), "Role {$roleUser->getRoleNames()->first()} should not create users");
        }
    }

    public function test_role_assignment_and_permission_config_boundary(): void
    {
        // 1. Business Owner has authority over roles
        $this->actingAs($this->owner);
        $this->assertTrue(RoleResource::canViewAny());
        $this->assertTrue(RoleResource::canCreate());
        $targetRole = Role::where('name', RoleName::OPERATIONS_EXECUTIVE->value)->first();
        $this->assertTrue(RoleResource::canEdit($targetRole));

        // Core system roles CANNOT be deleted, even by Business Owner
        $this->assertFalse(RoleResource::canDelete($targetRole));
        $this->assertFalse(Gate::allows('delete', $targetRole));

        // Custom roles CAN be deleted by Business Owner
        $customRole = Role::create(['name' => 'Custom Reviewer', 'guard_name' => 'web']);
        $this->assertTrue(RoleResource::canDelete($customRole));
        $this->assertTrue(Gate::allows('delete', $customRole));

        // 2. City Manager has NO access to roles
        $this->actingAs($this->cityManager);
        $this->assertFalse(RoleResource::canViewAny());
        $this->assertFalse(RoleResource::canCreate());
        $this->assertFalse(RoleResource::canEdit($targetRole));
        $this->assertFalse(RoleResource::canDelete($targetRole));
        $this->assertFalse(RoleResource::canDelete($customRole));

        // 3. All other roles have NO access to roles
        $isolatedRoles = [$this->supplyManager, $this->demandManager, $this->opsManager, $this->opsExecutive, $this->accountant];
        foreach ($isolatedRoles as $roleUser) {
            $this->actingAs($roleUser);
            $this->assertFalse(RoleResource::canViewAny());
            $this->assertFalse(RoleResource::canCreate());
        }
    }

    public function test_branch_and_geographic_hierarchy_setup_authorization(): void
    {
        // 1. Business Owner can view and mutate branches and cities
        $this->actingAs($this->owner);
        $this->assertTrue(BranchResource::canViewAny());
        $this->assertTrue(BranchResource::canCreate());
        $this->assertTrue(BranchResource::canEdit($this->branch1));
        $this->assertTrue(BranchResource::canDelete($this->branch1));

        $this->assertTrue(CityResource::canViewAny());
        $this->assertTrue(CityResource::canCreate());

        // 2. City Manager can view branches and cities, but CANNOT mutate
        $this->actingAs($this->cityManager);
        $this->assertTrue(BranchResource::canViewAny());
        $this->assertFalse(BranchResource::canCreate());
        $this->assertFalse(BranchResource::canEdit($this->branch1));
        $this->assertFalse(BranchResource::canDelete($this->branch1));

        $this->assertTrue(CityResource::canViewAny());
        $this->assertFalse(CityResource::canCreate());

        // 3. Other roles have NO access to branch management
        $isolatedRoles = [$this->supplyManager, $this->demandManager, $this->opsManager, $this->opsExecutive, $this->accountant];
        foreach ($isolatedRoles as $roleUser) {
            $this->actingAs($roleUser);
            $this->assertFalse(BranchResource::canViewAny());
            $this->assertFalse(BranchResource::canCreate());
        }
    }

    public function test_financial_models_and_system_settings_protection(): void
    {
        // 1. Business Owner can view and mutate financial models and settings
        $this->actingAs($this->owner);
        $this->assertTrue(FinancialModelResource::canViewAny());
        $this->assertTrue(FinancialModelResource::canCreate());
        $this->assertTrue(ManageFinancialSettings::canAccess());

        // 2. City Manager and Accountant can VIEW reference settings, but NOT mutate
        $this->actingAs($this->cityManager);
        $this->assertTrue(FinancialModelResource::canViewAny());
        $this->assertFalse(FinancialModelResource::canCreate());
        $this->assertTrue(ManageFinancialSettings::canAccess());

        $this->actingAs($this->accountant);
        $this->assertTrue(FinancialModelResource::canViewAny());
        $this->assertFalse(FinancialModelResource::canCreate());
        $this->assertTrue(ManageFinancialSettings::canAccess());

        // City Manager and Accountant blocked from executing save() on ManageFinancialSettings
        $this->actingAs($this->cityManager);
        $settingsPage = new ManageFinancialSettings;
        $this->expectException(HttpException::class);
        $settingsPage->save();
    }

    public function test_forensic_audit_trail_inspection_and_query_scoping(): void
    {
        // 1. Business Owner, City Manager, and Accountant can access audit logs; other roles forbidden
        $this->actingAs($this->owner);
        $this->assertTrue(AuditLogResource::canViewAny());

        $this->actingAs($this->cityManager);
        $this->assertTrue(AuditLogResource::canViewAny());

        $this->actingAs($this->accountant);
        $this->assertTrue(AuditLogResource::canViewAny());

        $isolatedRoles = [$this->supplyManager, $this->demandManager, $this->opsManager, $this->opsExecutive];
        foreach ($isolatedRoles as $roleUser) {
            $this->actingAs($roleUser);
            $this->assertFalse(AuditLogResource::canViewAny(), "Role {$roleUser->getRoleNames()->first()} should be denied from audit logs");
        }

        // 2. AuditLog is strictly immutable (cannot create, edit, delete)
        $this->actingAs($this->owner);
        $this->assertFalse(AuditLogResource::canCreate());
        $this->assertFalse(AuditLogResource::canDeleteAny());

        // 3. Create test activity records
        $prop1 = Property::create([
            'building_name' => 'Sunshine Residency 401',
            'branch_id' => $this->branch1->id,
            'status' => 'draft',
        ]);
        $prop2 = Property::create([
            'building_name' => 'Moonlight Towers 202',
            'branch_id' => $this->branch2->id,
            'status' => 'draft',
        ]);

        $logBranch1 = Activity::create([
            'log_name' => 'property',
            'description' => 'Property created in branch 1',
            'subject_type' => Property::class,
            'subject_id' => $prop1->id,
            'causer_type' => User::class,
            'causer_id' => $this->opsExecutive->id,
        ]);

        $logBranch2 = Activity::create([
            'log_name' => 'property',
            'description' => 'Property created in branch 2',
            'subject_type' => Property::class,
            'subject_id' => $prop2->id,
            'causer_type' => User::class,
            'causer_id' => $this->owner->id,
        ]);

        $logFinance = Activity::create([
            'log_name' => 'finance',
            'description' => 'Owner payout batch processed',
            'subject_type' => OwnerPayout::class,
            'subject_id' => 999,
            'causer_type' => User::class,
            'causer_id' => $this->accountant->id,
        ]);

        // 4. Owner sees all logs
        $this->actingAs($this->owner);
        $ownerQuery = AuditLogResource::getEloquentQuery()->get();
        $this->assertTrue($ownerQuery->contains('id', $logBranch1->id));
        $this->assertTrue($ownerQuery->contains('id', $logBranch2->id));
        $this->assertTrue($ownerQuery->contains('id', $logFinance->id));

        // 5. City Manager only sees logs for their branch (branch1 / opsExecutive)
        $this->actingAs($this->cityManager);
        $cmQuery = AuditLogResource::getEloquentQuery()->get();
        $this->assertTrue($cmQuery->contains('id', $logBranch1->id));
        $this->assertFalse($cmQuery->contains('id', $logBranch2->id));
        $this->assertFalse($cmQuery->contains('id', $logFinance->id));

        // 6. Accountant only sees financial logs
        $this->actingAs($this->accountant);
        $accountantQuery = AuditLogResource::getEloquentQuery()->get();
        $this->assertTrue($accountantQuery->contains('id', $logFinance->id));
        $this->assertFalse($accountantQuery->contains('id', $logBranch1->id));
        $this->assertFalse($accountantQuery->contains('id', $logBranch2->id));
    }

    public function test_system_roles_cannot_be_renamed_at_model_level(): void
    {
        $role = Role::where('name', RoleName::BUSINESS_OWNER->value)->firstOrFail();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Core system role 'Business Owner' cannot be renamed.");

        $role->update(['name' => 'Managing Director']);
    }

    public function test_system_roles_cannot_be_deleted_at_model_level(): void
    {
        $role = Role::where('name', RoleName::CITY_MANAGER->value)->firstOrFail();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Core system role 'City Manager' cannot be deleted under any circumstances.");

        $role->delete();
    }

    public function test_system_role_display_name_can_be_customized(): void
    {
        $role = Role::where('name', RoleName::BUSINESS_OWNER->value)->firstOrFail();

        // Business Owner can freely customize the display_name without touching the system machine name
        $role->update(['display_name' => 'Executive Director & Founder']);

        $role->refresh();
        $this->assertEquals('Executive Director & Founder', $role->display_name);
        $this->assertEquals('Business Owner', $role->name);
        $this->assertEquals('Executive Director & Founder', $role->display_name_or_name);

        // Security check: hasRole() continues working flawlessly with RoleName enum
        $this->assertTrue($this->owner->hasRole(RoleName::BUSINESS_OWNER));
        $this->assertTrue($this->owner->hasRole('Business Owner'));
    }

    public function test_custom_roles_can_be_created_renamed_and_deleted(): void
    {
        $this->actingAs($this->owner);

        // Create non-system custom role
        $customRole = Role::create([
            'name' => 'Regional Field Officer',
            'display_name' => 'Field Officer (Junior)',
            'guard_name' => 'web',
            'is_system' => false,
        ]);

        $this->assertFalse($customRole->isSystem());
        $this->assertTrue(RoleResource::canDelete($customRole));

        // Rename custom role
        $customRole->update(['name' => 'Senior Field Officer']);
        $customRole->refresh();
        $this->assertEquals('Senior Field Officer', $customRole->name);

        // Delete custom role
        $roleId = $customRole->id;
        $customRole->delete();
        $this->assertNull(Role::find($roleId));
    }

    public function test_role_name_enum_helper_methods(): void
    {
        $values = RoleName::values();

        $this->assertCount(7, $values);
        $this->assertContains('Business Owner', $values);
        $this->assertContains('City Manager', $values);
        $this->assertContains('Supply Manager', $values);
        $this->assertContains('Demand Manager', $values);
        $this->assertContains('Operations Manager', $values);
        $this->assertContains('Operations Executive', $values);
        $this->assertContains('Accountant', $values);

        $this->assertTrue(RoleName::isSystem('Business Owner'));
        $this->assertTrue(RoleName::isSystem(RoleName::CITY_MANAGER));
        $this->assertFalse(RoleName::isSystem('Arbitrary Custom Role'));
        $this->assertNotEmpty(RoleName::BUSINESS_OWNER->description());
        $this->assertEquals('Business Owner', RoleName::BUSINESS_OWNER->label());
    }
}
