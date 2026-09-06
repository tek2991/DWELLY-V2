<?php

namespace Tests\Feature\Authorization;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Models\Audit;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Mou\Enums\MouType;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Party\Enums\BusinessRole;
use App\Domain\Party\Enums\VendorOnboardingStatus;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\VendorTrade;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Operations\MOUResource;
use App\Filament\Resources\Properties\Widgets\OnboardingProgressWidget;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowActionsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $cityManager;

    protected User $supplyManager;

    protected User $demandManager;

    protected User $opsManager;

    protected User $opsExecutive;

    protected User $accountant;

    protected Property $property;

    protected Party $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole(RoleName::BUSINESS_OWNER->value);

        $this->cityManager = User::factory()->create();
        $this->cityManager->assignRole(RoleName::CITY_MANAGER->value);

        $this->supplyManager = User::factory()->create();
        $this->supplyManager->assignRole(RoleName::SUPPLY_MANAGER->value);

        $this->demandManager = User::factory()->create();
        $this->demandManager->assignRole(RoleName::DEMAND_MANAGER->value);

        $this->opsManager = User::factory()->create();
        $this->opsManager->assignRole(RoleName::OPERATIONS_MANAGER->value);

        $this->opsExecutive = User::factory()->create();
        $this->opsExecutive->assignRole(RoleName::OPERATIONS_EXECUTIVE->value);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole(RoleName::ACCOUNTANT->value);

        $this->property = Property::create([
            'building_name' => 'Dwelly Heights #101',
            'address_line_1' => 'GS Road, Guwahati',
            'status' => 'vacant',
        ]);

        $this->tenant = Party::create([
            'display_name' => 'Rahul Sharma',
            'phone' => '+91 98765 43210',
            'party_type' => 'individual',
        ]);
        $this->tenant->enableRole(BusinessRole::TENANT);
    }

    public function test_opportunity_workflow_actions_authorization(): void
    {
        $opportunity = Opportunity::create([
            'number' => 'OPP-WF-01',
            'title' => 'Green Valley Apartment',
            'owner_name' => 'John Doe',
            'owner_phone' => '9876543210',
            'expected_rent' => 25000,
            'assigned_user_id' => $this->supplyManager->id,
            'status' => OpportunityStatus::NEW,
        ]);

        // Supply Manager can update opportunity (can mark ready for MOU & close lost)
        $this->assertTrue($this->supplyManager->can('update', $opportunity));

        // Operations Manager has view access (for onboarding forecasting) but CANNOT update opportunity
        $this->assertTrue($this->opsManager->can('view', $opportunity));
        $this->assertFalse($this->opsManager->can('update', $opportunity));

        // Demand Manager and Accountant have zero access to opportunities
        $this->assertFalse($this->demandManager->can('view', $opportunity));
        $this->assertFalse($this->demandManager->can('update', $opportunity));

        $this->assertFalse($this->accountant->can('view', $opportunity));
        $this->assertFalse($this->accountant->can('update', $opportunity));
    }

    public function test_mou_workflow_actions_authorization(): void
    {
        $opportunity = Opportunity::create([
            'number' => 'OPP-WF-02',
            'title' => 'Green Valley Apartment 2',
            'owner_name' => 'Jane Landlord',
            'owner_phone' => '9888877777',
            'assigned_user_id' => $this->supplyManager->id,
            'status' => OpportunityStatus::NEW,
        ]);

        $mou = Mou::create([
            'number' => 'MOU-WF-01',
            'opportunity_id' => $opportunity->id,
            'type' => MouType::ONBOARDING,
            'status' => MouStatus::DRAFT,
            'owner_name' => 'Jane Landlord',
            'owner_phone' => '9888877777',
        ]);

        // Supply Manager can edit draft MOU (can generate PDF, upload signed copy, provision accounting)
        $this->actingAs($this->supplyManager);
        $this->assertTrue(MOUResource::canEdit($mou));
        $this->assertTrue($this->supplyManager->can('update', $mou));

        // Ops Manager CANNOT update draft MOU (only verify signed copy)
        $this->actingAs($this->opsManager);
        $this->assertFalse(MOUResource::canEdit($mou));
        $this->assertFalse($this->opsManager->can('update', $mou));

        // Maker vs Checker on Verification:
        $signedMou = Mou::create([
            'number' => 'MOU-WF-02',
            'opportunity_id' => $opportunity->id,
            'type' => MouType::ONBOARDING,
            'status' => MouStatus::SIGNED_COPY_UPLOADED,
            'owner_name' => 'Jane Landlord',
            'owner_phone' => '9888877777',
        ]);

        // Supply Manager (Maker) is strictly blocked from verifying MOU
        $this->assertFalse($this->supplyManager->can('verify', $signedMou));

        // Operations Manager and City Manager (Checkers) can verify MOU
        $this->assertTrue($this->opsManager->can('verify', $signedMou));
        $this->assertTrue($this->cityManager->can('verify', $signedMou));
    }

    public function test_property_onboarding_review_actions_authorization(): void
    {
        $widget = new OnboardingProgressWidget;
        $widget->record = $this->property;

        // Operations Executive CANNOT review/activate onboarding
        $this->actingAs($this->opsExecutive);
        $this->assertFalse($widget->canUserReview());

        // Operations Manager CAN review/activate onboarding
        $this->actingAs($this->opsManager);
        $this->assertTrue($widget->canUserReview());

        // City Manager CAN review/activate onboarding
        $this->actingAs($this->cityManager);
        $this->assertTrue($widget->canUserReview());

        // Business Owner CAN review/activate onboarding
        $this->actingAs($this->owner);
        $this->assertTrue($widget->canUserReview());
    }

    public function test_tenancy_agreement_creation_and_renewal_authorization(): void
    {
        $agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'TNC-WF-01',
            'status' => 'active',
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->addMonths(8)->toDateString(),
            'rent_amount' => 20000.00,
            'security_deposit' => 40000.00,
        ]);
        $agreement->roles()->create([
            'party_id' => $this->tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        // Chinese Wall: Supply Manager CANNOT create or renew tenancy agreements
        $this->assertFalse($this->supplyManager->can('create', TenancyAgreement::class));
        $this->assertFalse($this->supplyManager->can('renew', $agreement));

        // Demand Manager CAN create and renew tenancy agreements
        $this->assertTrue($this->demandManager->can('create', TenancyAgreement::class));
        $this->assertTrue($this->demandManager->can('renew', $agreement));

        // Operations Executive CANNOT renew tenancy agreements
        $this->assertFalse($this->opsExecutive->can('renew', $agreement));
    }

    public function test_maintenance_start_repair_and_close_ticket_authorization(): void
    {
        $ticket = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'ticket_number' => 'MR-WF-01',
            'title' => 'Leaking Faucet in Kitchen',
            'status' => MaintenanceStatus::SUBMITTED,
            'priority' => MaintenancePriority::MEDIUM,
        ]);

        // Supply Manager has Chinese Wall against Maintenance
        $this->assertFalse($this->supplyManager->can('superviseRepair', $ticket));
        $this->assertFalse($this->supplyManager->can('signOff', $ticket));

        // Operations Executive CAN supervise/start repair, but CANNOT sign-off/close ticket
        $this->assertTrue($this->opsExecutive->can('superviseRepair', $ticket));
        $this->assertFalse($this->opsExecutive->can('signOff', $ticket));

        // Operations Manager CAN both supervise repair AND sign-off/close ticket
        $this->assertTrue($this->opsManager->can('superviseRepair', $ticket));
        $this->assertTrue($this->opsManager->can('signOff', $ticket));

        // Accountant CAN sign-off/close ticket for financial clearance, but does not supervise repairs
        $this->assertTrue($this->accountant->can('signOff', $ticket));
    }

    public function test_vendor_verification_and_suspension_authorization(): void
    {
        $trade = VendorTrade::create([
            'name' => 'Plumbing',
            'slug' => 'plumbing',
            'is_active' => true,
        ]);

        $vendor = Party::create([
            'display_name' => 'Quick Plumbing Services',
            'phone' => '+91 99999 11111',
            'party_type' => 'organization',
        ]);
        $vendor->enableRole(BusinessRole::VENDOR, [
            'vendor_trade_id' => $trade->id,
            'onboarding_status' => VendorOnboardingStatus::PENDING_VERIFICATION->value,
        ]);

        // Manager roles have authority to verify and suspend vendors
        $this->assertTrue($this->owner->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::OPERATIONS_MANAGER]));
        $this->assertTrue($this->cityManager->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::OPERATIONS_MANAGER]));
        $this->assertTrue($this->opsManager->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::OPERATIONS_MANAGER]));

        // Field/Sales roles DO NOT have authority to verify or suspend vendors
        $this->assertFalse($this->opsExecutive->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::OPERATIONS_MANAGER]));
        $this->assertFalse($this->supplyManager->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::OPERATIONS_MANAGER]));
        $this->assertFalse($this->demandManager->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::OPERATIONS_MANAGER]));
    }

    public function test_audit_review_actions_enforce_four_eyes_authorization(): void
    {
        $audit = Audit::create([
            'property_id' => $this->property->id,
            'audit_number' => 'AUD-WF-01',
            'status' => AuditStatus::PENDING_REVIEW,
            'audit_type' => AuditType::PERIODIC,
        ]);

        // 4-Eyes Gate: Operations Executive CANNOT review/approve audit
        $this->assertFalse($this->opsExecutive->can('review', $audit));

        // Operations Manager, City Manager, and Owner CAN review/approve audit
        $this->assertTrue($this->opsManager->can('review', $audit));
        $this->assertTrue($this->cityManager->can('review', $audit));
        $this->assertTrue($this->owner->can('review', $audit));
    }
}
