<?php

namespace Tests\Feature\Authorization;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Agreement\Services\TenancyDeboardingService;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Models\Audit;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\EditTenantDeboarding;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ManageDeboardingAudit;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ManageDeboardingCompletion;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ManageDeboardingKeys;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ManageDeboardingSettlement;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class DeboardingAndSettlementRbacTest extends TestCase
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

    protected Audit $moveInAudit;

    protected TenancyAgreement $agreement;

    protected TenantDeboarding $deboarding;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('Business Owner');

        $this->cityManager = User::factory()->create();
        $this->cityManager->assignRole('City Manager');

        $this->supplyManager = User::factory()->create();
        $this->supplyManager->assignRole('Supply Manager');

        $this->demandManager = User::factory()->create();
        $this->demandManager->assignRole('Demand Manager');

        $this->opsManager = User::factory()->create();
        $this->opsManager->assignRole('Operations Manager');

        $this->opsExecutive = User::factory()->create();
        $this->opsExecutive->assignRole('Operations Executive');

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole('Accountant');

        $org = Organization::create([
            'name' => 'Dwelly Living Private Limited',
            'legal_name' => 'Dwelly Living Private Limited',
        ]);

        $branch = Branch::create([
            'organization_id' => $org->id,
            'name' => 'Guwahati Central',
            'code' => 'GH-01',
            'city' => 'Guwahati',
        ]);

        $this->property = Property::create([
            'code' => 'PROP-GH-402',
            'building_name' => 'Silpukhuri Residency #4B',
            'address_line_1' => 'MG Road, Guwahati',
            'status' => 'occupied',
            'branch_id' => $branch->id,
        ]);

        $this->opsExecutive->branches()->attach($branch->id);
        $this->opsManager->branches()->attach($branch->id);
        $this->accountant->branches()->attach($branch->id);
        $this->demandManager->branches()->attach($branch->id);
        $this->cityManager->branches()->attach($branch->id);

        $this->tenant = Party::create([
            'display_name' => 'Pranjal Saikia',
            'phone' => '+91 91234 56789',
            'party_type' => 'individual',
        ]);

        $this->moveInAudit = Audit::create([
            'property_id' => $this->property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::APPROVED,
            'inspector_id' => $this->opsExecutive->id,
            'is_locked' => true,
        ]);

        $this->agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'audit_id' => $this->moveInAudit->id,
            'code' => 'TNC-2026-0042',
            'status' => 'active',
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'rent_amount' => 25000.00,
            'security_deposit' => 50000.00,
        ]);

        $this->agreement->roles()->create([
            'party_id' => $this->tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        $service = app(TenancyDeboardingService::class);
        $this->deboarding = $service->initiateDeboarding($this->agreement, [
            'notice_date' => now()->toDateString(),
            'vacating_date' => now()->addDays(30)->toDateString(),
            'reason' => 'Agreement Expiry',
        ], $this->opsManager);
    }

    public function test_notice_and_deboarding_intake_boundaries(): void
    {
        // 1. Demand Manager can initiate deboarding notice on tenant behalf
        $this->assertTrue($this->demandManager->can('create', TenantDeboarding::class));

        // 2. Operations Manager, City Manager, and Owner can initiate deboarding
        $this->assertTrue($this->opsManager->can('create', TenantDeboarding::class));
        $this->assertTrue($this->cityManager->can('create', TenantDeboarding::class));
        $this->assertTrue($this->owner->can('create', TenantDeboarding::class));

        // 3. Operations Executive cannot initiate deboarding notice (field technician, not leasing)
        $this->assertFalse($this->opsExecutive->can('create', TenantDeboarding::class));

        // 4. Supply Manager is strictly barred via Chinese Wall
        $this->assertFalse($this->supplyManager->can('create', TenantDeboarding::class));
        $this->assertFalse($this->supplyManager->can('viewAny', TenantDeboarding::class));
        $this->assertFalse($this->supplyManager->can('view', $this->deboarding));

        // 5. Check sub-navigation notice tab access
        $this->actingAs($this->demandManager);
        $this->assertTrue(EditTenantDeboarding::canAccess());

        $this->actingAs($this->supplyManager);
        $this->assertFalse(EditTenantDeboarding::canAccess());
    }

    public function test_move_out_exit_audit_access_and_sealing_boundaries(): void
    {
        $moveOutAudit = $this->deboarding->moveOutAudit;
        $this->assertNotNull($moveOutAudit);

        // 1. Operations Executive can access exit audit tab and inspect on tablet
        $moveOutAudit->update(['inspector_id' => $this->opsExecutive->id]);
        $this->actingAs($this->opsExecutive);
        $this->assertTrue(ManageDeboardingAudit::canAccess());
        $this->assertTrue($this->opsExecutive->can('inspect', $moveOutAudit));
        // But Ops Executive CANNOT seal the audit baseline (4-Eyes checker gate)
        $this->assertFalse($this->opsExecutive->can('seal', $moveOutAudit));

        // 2. Operations Manager can access exit audit tab and seal the baseline
        $this->actingAs($this->opsManager);
        $this->assertTrue(ManageDeboardingAudit::canAccess());
        $this->assertTrue($this->opsManager->can('seal', $moveOutAudit));

        // 3. Demand Manager and Accountant are forbidden from exit audit tab
        $this->actingAs($this->demandManager);
        $this->assertFalse(ManageDeboardingAudit::canAccess());

        $this->actingAs($this->accountant);
        $this->assertFalse(ManageDeboardingAudit::canAccess());

        // 4. Supply Manager has zero access
        $this->actingAs($this->supplyManager);
        $this->assertFalse(ManageDeboardingAudit::canAccess());
    }

    public function test_physical_key_handover_access_boundaries(): void
    {
        // 1. Operations Executive can collect keys on-site and record timestamp
        $this->actingAs($this->opsExecutive);
        $this->assertTrue(ManageDeboardingKeys::canAccess());
        $this->assertTrue($this->opsExecutive->can('returnKeys', $this->deboarding));

        // 2. Operations Manager can confirm key handover
        $this->actingAs($this->opsManager);
        $this->assertTrue(ManageDeboardingKeys::canAccess());
        $this->assertTrue($this->opsManager->can('returnKeys', $this->deboarding));

        // 3. Demand Manager and Supply Manager have no access to key collection
        $this->actingAs($this->demandManager);
        $this->assertFalse(ManageDeboardingKeys::canAccess());

        $this->actingAs($this->supplyManager);
        $this->assertFalse(ManageDeboardingKeys::canAccess());
    }

    public function test_settlement_sheet_access_isolation(): void
    {
        // 1. Operations Executive and Demand Manager are strictly barred from Settlement Sheet tab
        $this->actingAs($this->opsExecutive);
        $this->assertFalse(ManageDeboardingSettlement::canAccess());
        $this->assertFalse($this->opsExecutive->can('draftSettlement', $this->deboarding));
        $this->assertFalse($this->opsExecutive->can('approveSettlement', $this->deboarding));

        $this->actingAs($this->demandManager);
        $this->assertFalse(ManageDeboardingSettlement::canAccess());
        $this->assertFalse($this->demandManager->can('draftSettlement', $this->deboarding));

        // 2. Supply Manager has zero access
        $this->actingAs($this->supplyManager);
        $this->assertFalse(ManageDeboardingSettlement::canAccess());

        // 3. Operations Manager and Accountant have full access to draft/review settlement
        $this->actingAs($this->opsManager);
        $this->assertTrue(ManageDeboardingSettlement::canAccess());
        $this->assertTrue($this->opsManager->can('draftSettlement', $this->deboarding));
        $this->assertTrue($this->opsManager->can('approveSettlement', $this->deboarding));

        $this->actingAs($this->accountant);
        $this->assertTrue(ManageDeboardingSettlement::canAccess());
        $this->assertTrue($this->accountant->can('draftSettlement', $this->deboarding));
        $this->assertTrue($this->accountant->can('approveSettlement', $this->deboarding));
    }

    public function test_dual_ops_finance_and_fiduciary_refund_disbursement_gate(): void
    {
        // 1. Operations Manager can approve settlement sheet (Ops Checker), but CANNOT disburse bank refund
        $this->assertTrue($this->opsManager->can('approveSettlement', $this->deboarding));
        $this->assertFalse($this->opsManager->can('disburseRefund', $this->deboarding));

        // 2. City Manager is strictly FORBIDDEN from disbursing deposit refund (fiduciary gate)
        $this->assertTrue($this->cityManager->can('approveSettlement', $this->deboarding));
        $this->assertFalse($this->cityManager->can('disburseRefund', $this->deboarding));

        // 3. Accountant has full fiduciary authority to disburse deposit refund
        $this->assertTrue($this->accountant->can('approveSettlement', $this->deboarding));
        $this->assertTrue($this->accountant->can('disburseRefund', $this->deboarding));

        // 4. Business Owner has full fiduciary authority
        $this->assertTrue($this->owner->can('disburseRefund', $this->deboarding));

        // 5. Operations Executive and Demand Manager have zero refund authority
        $this->assertFalse($this->opsExecutive->can('disburseRefund', $this->deboarding));
        $this->assertFalse($this->demandManager->can('disburseRefund', $this->deboarding));
    }

    public function test_final_handover_completion_and_immutability(): void
    {
        // 1. Operations Executive and Demand Manager cannot complete deboarding
        $this->actingAs($this->opsExecutive);
        $this->assertFalse(ManageDeboardingCompletion::canAccess());
        $this->assertFalse($this->opsExecutive->can('complete', $this->deboarding));

        $this->actingAs($this->demandManager);
        $this->assertFalse(ManageDeboardingCompletion::canAccess());
        $this->assertFalse($this->demandManager->can('complete', $this->deboarding));

        // 2. Operations Manager, Accountant, City Manager, and Owner can access completion
        $this->actingAs($this->opsManager);
        $this->assertTrue(ManageDeboardingCompletion::canAccess());
        $this->assertTrue($this->opsManager->can('complete', $this->deboarding));

        $this->actingAs($this->accountant);
        $this->assertTrue(ManageDeboardingCompletion::canAccess());
        $this->assertTrue($this->accountant->can('complete', $this->deboarding));

        // 3. Complete the deboarding workflow via service
        $service = app(TenancyDeboardingService::class);
        $service->completeDeboardingAndVacate(
            $this->deboarding,
            'vacant',
            [
                'net_refund' => 45000.00,
                'settlement_status' => 'settled',
                'refund_payment_mode' => 'Bank Transfer (NEFT / IMPS / RTGS)',
                'refund_transaction_reference' => 'UTR9876543210',
            ],
            $this->opsManager
        );

        $this->deboarding->refresh();
        $this->agreement->refresh();
        $this->property->refresh();

        $this->assertEquals(DeboardingStatus::COMPLETED, $this->deboarding->status);
        $this->assertEquals('vacated', $this->agreement->status);
        $this->assertEquals('vacant', $this->property->status);

        // 4. Invariant: Completed deboarding is immutable!
        $this->assertFalse($this->opsManager->can('update', $this->deboarding));
        $this->assertFalse($this->opsManager->can('complete', $this->deboarding));
        $this->assertFalse($this->opsManager->can('delete', $this->deboarding));
        $this->assertFalse($this->owner->can('delete', $this->deboarding));
    }
}
