<?php

namespace Tests\Feature\Auth;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Domain\Task\Models\Task;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tek2991\Accounting\Models\Organization;
use Tek2991\Accounting\Services\BranchContext;
use Tests\TestCase;

class BranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchGhy;
    protected Branch $branchBlr;
    protected User $staffGhy;
    protected User $staffBlr;
    protected User $ownerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'name' => 'Dwelly Living Private Limited',
            'legal_name' => 'Dwelly Living Private Limited',
        ]);

        $this->branchGhy = Branch::create([
            'organization_id' => $org->id,
            'name' => 'Guwahati Head Office',
            'code' => 'GHY',
            'city' => 'Guwahati',
            'is_active' => true,
        ]);

        $this->branchBlr = Branch::create([
            'organization_id' => $org->id,
            'name' => 'Bangalore Branch',
            'code' => 'BLR',
            'city' => 'Bangalore',
            'is_active' => true,
        ]);

        // Roles
        Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Operations Executive', 'guard_name' => 'web']);

        // Users
        $this->staffGhy = User::create([
            'name' => 'Rahul Guwahati',
            'email' => 'rahul@dwelly.in',
            'password' => bcrypt('password'),
        ]);
        $this->staffGhy->assignRole('Operations Executive');
        $this->staffGhy->branches()->attach($this->branchGhy->id);

        $this->staffBlr = User::create([
            'name' => 'Priya Bangalore',
            'email' => 'priya@dwelly.in',
            'password' => bcrypt('password'),
        ]);
        $this->staffBlr->assignRole('Operations Executive');
        $this->staffBlr->branches()->attach($this->branchBlr->id);

        $this->ownerUser = User::create([
            'name' => 'Business Owner',
            'email' => 'owner@dwelly.in',
            'password' => bcrypt('password'),
        ]);
        $this->ownerUser->assignRole('Business Owner');
    }

    protected function createProperty(array $attributes = []): Property
    {
        return Property::create(array_merge([
            'status' => 'Vacant',
            'is_listed' => true,
        ], $attributes));
    }

    public function test_user_assigned_to_branch_can_only_see_properties_of_that_branch(): void
    {
        // Create 2 Guwahati properties and 3 Bangalore properties
        $this->createProperty(['branch_id' => $this->branchGhy->id, 'code' => 'GHY-001', 'building_name' => 'Subham Regency', 'city' => 'Guwahati']);
        $this->createProperty(['branch_id' => $this->branchGhy->id, 'code' => 'GHY-002', 'building_name' => 'Protech View', 'city' => 'Guwahati']);
        $this->createProperty(['branch_id' => $this->branchBlr->id, 'code' => 'BLR-001', 'building_name' => 'Prestige Ferns', 'city' => 'Bangalore']);
        $this->createProperty(['branch_id' => $this->branchBlr->id, 'code' => 'BLR-002', 'building_name' => 'Sobha Dream Acres', 'city' => 'Bangalore']);
        $this->createProperty(['branch_id' => $this->branchBlr->id, 'code' => 'BLR-003', 'building_name' => 'Brigade Utopia', 'city' => 'Bangalore']);

        // Authenticate as Guwahati staff
        $this->actingAs($this->staffGhy);
        app(BranchContext::class)->clear();

        $this->assertEquals(2, Property::count());
        $this->assertEquals(2, Property::where('city', 'Guwahati')->count());
        $this->assertEquals(0, Property::where('city', 'Bangalore')->count());

        // Authenticate as Bangalore staff
        $this->actingAs($this->staffBlr);
        app(BranchContext::class)->clear();

        $this->assertEquals(3, Property::count());
        $this->assertEquals(0, Property::where('city', 'Guwahati')->count());
        $this->assertEquals(3, Property::where('city', 'Bangalore')->count());
    }

    public function test_user_cannot_access_other_branch_property_by_id(): void
    {
        $blrProp = $this->createProperty(['branch_id' => $this->branchBlr->id, 'code' => 'BLR-100', 'building_name' => 'Secret Bangalore Villa', 'city' => 'Bangalore']);

        $this->actingAs($this->staffGhy);
        app(BranchContext::class)->clear();

        $this->expectException(ModelNotFoundException::class);
        Property::findOrFail($blrProp->id);
    }

    public function test_user_cannot_tamper_session_to_access_unauthorized_branch(): void
    {
        $this->createProperty(['branch_id' => $this->branchGhy->id, 'code' => 'GHY-001', 'building_name' => 'Subham Regency', 'city' => 'Guwahati']);
        $this->createProperty(['branch_id' => $this->branchBlr->id, 'code' => 'BLR-001', 'building_name' => 'Prestige Ferns', 'city' => 'Bangalore']);

        $this->actingAs($this->staffGhy);

        // Staff attempts to inject unauthorized branch ID into session
        session(['accounting_branch_id' => $this->branchBlr->id]);

        $this->assertEquals(1, Property::count());
        $this->assertEquals('GHY-001', Property::first()->code);
    }

    public function test_business_owner_can_see_all_resources_across_all_branches(): void
    {
        $this->createProperty(['branch_id' => $this->branchGhy->id, 'code' => 'GHY-001', 'building_name' => 'Subham Regency', 'city' => 'Guwahati']);
        $this->createProperty(['branch_id' => $this->branchBlr->id, 'code' => 'BLR-001', 'building_name' => 'Prestige Ferns', 'city' => 'Bangalore']);

        $this->actingAs($this->ownerUser);
        app(BranchContext::class)->setAllBranches();

        $this->assertEquals(2, Property::count());
    }

    public function test_business_owner_can_filter_to_specific_branch(): void
    {
        $this->createProperty(['branch_id' => $this->branchGhy->id, 'code' => 'GHY-001', 'building_name' => 'Subham Regency', 'city' => 'Guwahati']);
        $this->createProperty(['branch_id' => $this->branchBlr->id, 'code' => 'BLR-001', 'building_name' => 'Prestige Ferns', 'city' => 'Bangalore']);

        $this->actingAs($this->ownerUser);

        // Owner selects Bangalore
        app(BranchContext::class)->set($this->branchBlr);

        $this->assertEquals(1, Property::count());
        $this->assertEquals('BLR-001', Property::first()->code);

        // Owner selects Guwahati
        app(BranchContext::class)->set($this->branchGhy);

        $this->assertEquals(1, Property::count());
        $this->assertEquals('GHY-001', Property::first()->code);
    }

    public function test_opportunities_and_mous_and_tenancies_are_branch_isolated(): void
    {
        $propGhy = $this->createProperty(['branch_id' => $this->branchGhy->id, 'code' => 'GHY-001', 'building_name' => 'Subham Regency', 'city' => 'Guwahati']);
        $propBlr = $this->createProperty(['branch_id' => $this->branchBlr->id, 'code' => 'BLR-001', 'building_name' => 'Prestige Ferns', 'city' => 'Bangalore']);

        $oppGhy = Opportunity::create(['branch_id' => $this->branchGhy->id, 'number' => 'OPP-GHY-01', 'title' => 'Guwahati Lead', 'status' => 'new']);
        $oppBlr = Opportunity::create(['branch_id' => $this->branchBlr->id, 'number' => 'OPP-BLR-01', 'title' => 'Bangalore Lead', 'status' => 'new']);

        Mou::create(['branch_id' => $this->branchGhy->id, 'opportunity_id' => $oppGhy->id, 'property_id' => $propGhy->id, 'number' => 'MOU-GHY-01', 'type' => 'onboarding', 'status' => 'draft']);
        Mou::create(['branch_id' => $this->branchBlr->id, 'opportunity_id' => $oppBlr->id, 'property_id' => $propBlr->id, 'number' => 'MOU-BLR-01', 'type' => 'onboarding', 'status' => 'draft']);

        TenancyAgreement::create([
            'branch_id' => $this->branchGhy->id,
            'property_id' => $propGhy->id,
            'code' => 'TNC-GHY-01',
            'status' => 'active',
            'rent_amount' => 15000,
            'security_deposit' => 30000,
        ]);
        TenancyAgreement::create([
            'branch_id' => $this->branchBlr->id,
            'property_id' => $propBlr->id,
            'code' => 'TNC-BLR-01',
            'status' => 'active',
            'rent_amount' => 25000,
            'security_deposit' => 50000,
        ]);

        // Guwahati staff view
        $this->actingAs($this->staffGhy);
        app(BranchContext::class)->clear();

        $this->assertEquals(1, Opportunity::count());
        $this->assertEquals('OPP-GHY-01', Opportunity::first()->number);

        $this->assertEquals(1, Mou::count());
        $this->assertEquals('MOU-GHY-01', Mou::first()->number);

        $this->assertEquals(1, TenancyAgreement::count());
        $this->assertEquals('TNC-GHY-01', TenancyAgreement::first()->code);

        // Owner view with all branches
        $this->actingAs($this->ownerUser);
        app(BranchContext::class)->setAllBranches();

        $this->assertEquals(2, Opportunity::count());
        $this->assertEquals(2, Mou::count());
        $this->assertEquals(2, TenancyAgreement::count());
    }

    public function test_tasks_and_owner_payouts_are_branch_isolated(): void
    {
        $ownerParty = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Owner Person',
            'phone' => '9988776655',
        ]);
        $propGhy = $this->createProperty(['branch_id' => $this->branchGhy->id, 'code' => 'GHY-001', 'building_name' => 'Subham Regency', 'city' => 'Guwahati']);
        $propBlr = $this->createProperty(['branch_id' => $this->branchBlr->id, 'code' => 'BLR-001', 'building_name' => 'Prestige Ferns', 'city' => 'Bangalore']);

        Task::create(['branch_id' => $this->branchGhy->id, 'property_id' => $propGhy->id, 'task_number' => 'TSK-GHY-01', 'title' => 'Inspect AC']);
        Task::create(['branch_id' => $this->branchBlr->id, 'property_id' => $propBlr->id, 'task_number' => 'TSK-BLR-01', 'title' => 'Key handover']);

        OwnerPayout::create(['branch_id' => $this->branchGhy->id, 'owner_id' => $ownerParty->id, 'property_id' => $propGhy->id, 'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'rent_collected' => 30000, 'management_fee' => 2400, 'amount' => 27600, 'status' => 'pending']);
        OwnerPayout::create(['branch_id' => $this->branchBlr->id, 'owner_id' => $ownerParty->id, 'property_id' => $propBlr->id, 'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'rent_collected' => 50000, 'management_fee' => 4000, 'amount' => 46000, 'status' => 'pending']);

        // Guwahati staff view
        $this->actingAs($this->staffGhy);
        app(BranchContext::class)->clear();

        $this->assertEquals(1, Task::count());
        $this->assertEquals('TSK-GHY-01', Task::first()->task_number);

        $this->assertEquals(1, OwnerPayout::count());
        $this->assertEquals(27600, OwnerPayout::first()->amount);

        // Owner view with all branches
        $this->actingAs($this->ownerUser);
        app(BranchContext::class)->setAllBranches();

        $this->assertEquals(2, Task::count());
        $this->assertEquals(2, OwnerPayout::count());
    }

    public function test_new_model_creation_auto_populates_branch_id_from_user_context(): void
    {
        $this->actingAs($this->staffGhy);
        app(BranchContext::class)->clear();

        // When creating a property without specifying branch_id, it should auto-assign the staff member's primary branch
        $newProp = $this->createProperty([
            'code' => 'GHY-AUTO-01',
            'building_name' => 'Auto Branch Residency',
            'city' => 'Guwahati',
        ]);

        $this->assertEquals($this->branchGhy->id, $newProp->branch_id);
    }
}

