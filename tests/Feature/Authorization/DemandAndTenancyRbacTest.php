<?php

namespace Tests\Feature\Authorization;

use App\Domain\Agreement\Actions\ActivateTenancyAction;
use App\Domain\Agreement\Actions\DraftTenancyAgreementAction;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandAndTenancyRbacTest extends TestCase
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

        $this->property = Property::create([
            'building_name' => 'Royal Heritage Residency #302',
            'address_line_1' => 'GS Road, Dispur, Guwahati',
            'status' => 'vacant',
        ]);

        $this->tenant = Party::create([
            'display_name' => 'Anurag Kashyap',
            'phone' => '+91 98765 43210',
            'party_type' => 'individual',
        ]);
    }

    public function test_tenancy_agreement_view_and_draft_access_boundaries(): void
    {
        // 1. Demand Manager has full access to view and draft agreements (Primary Maker)
        $this->assertTrue($this->demandManager->can('viewAny', TenancyAgreement::class));
        $this->assertTrue($this->demandManager->can('create', TenancyAgreement::class));

        // 2. City Manager and Business Owner have full management authority
        $this->assertTrue($this->cityManager->can('viewAny', TenancyAgreement::class));
        $this->assertTrue($this->cityManager->can('create', TenancyAgreement::class));
        $this->assertTrue($this->owner->can('viewAny', TenancyAgreement::class));
        $this->assertTrue($this->owner->can('create', TenancyAgreement::class));

        // 3. Operations Manager has read-only visibility for move-in audit prep
        $this->assertTrue($this->opsManager->can('viewAny', TenancyAgreement::class));
        $this->assertFalse($this->opsManager->can('create', TenancyAgreement::class));

        // 4. Accountant has read-only visibility for audit & security deposit tracking
        $this->assertTrue($this->accountant->can('viewAny', TenancyAgreement::class));
        $this->assertFalse($this->accountant->can('create', TenancyAgreement::class));

        // 5. Supply Manager is strictly walled off (Chinese Wall: zero tenancy access)
        $this->assertFalse($this->supplyManager->can('viewAny', TenancyAgreement::class));
        $this->assertFalse($this->supplyManager->can('create', TenancyAgreement::class));

        // 6. Operations Executive does not have direct tenancy list access
        $this->assertFalse($this->opsExecutive->can('viewAny', TenancyAgreement::class));
        $this->assertFalse($this->opsExecutive->can('create', TenancyAgreement::class));
    }

    public function test_commercial_terms_editing_policy(): void
    {
        $agreement = app(DraftTenancyAgreementAction::class)->execute(
            $this->property,
            [
                'code' => 'TA-2026-1001',
                'rent_amount' => 25000.00,
                'security_deposit' => 50000.00,
                'start_date' => '2026-10-01',
                'end_date' => '2027-08-31',
            ],
            [
                [
                    'party_id' => $this->tenant->id,
                    'role_type' => 'Primary Tenant',
                    'is_primary' => true,
                ],
            ],
            $this->demandManager
        );

        // While in draft: Demand Manager, City Manager, Owner can modify terms
        $this->assertTrue($this->demandManager->can('updateTerms', $agreement));
        $this->assertTrue($this->cityManager->can('updateTerms', $agreement));
        $this->assertTrue($this->owner->can('updateTerms', $agreement));

        // Accountant and Supply Manager cannot edit terms
        $this->assertFalse($this->accountant->can('updateTerms', $agreement));
        $this->assertFalse($this->supplyManager->can('updateTerms', $agreement));

        // Mark agreement as active
        $agreement->status = 'active';
        $agreement->save();

        // Once active: Demand Manager CANNOT update terms (policy bound; concessions require City/Owner approval)
        $this->assertFalse($this->demandManager->can('updateTerms', $agreement));

        // Business Owner and City Manager maintain terms amendment authority
        $this->assertTrue($this->owner->can('updateTerms', $agreement));
        $this->assertTrue($this->cityManager->can('updateTerms', $agreement));
    }

    public function test_dual_key_possession_gate_and_activation(): void
    {
        $agreement = app(DraftTenancyAgreementAction::class)->execute(
            $this->property,
            [
                'code' => 'TA-2026-1002',
                'rent_amount' => 30000.00,
                'security_deposit' => 60000.00,
                'start_date' => '2026-11-01',
                'end_date' => '2027-09-30',
            ],
            [
                [
                    'party_id' => $this->tenant->id,
                    'role_type' => 'Primary Tenant',
                    'is_primary' => true,
                ],
            ],
            $this->demandManager
        );

        // Gate Key 1: Key Handover
        // Without keys handed over, non-admin Demand Manager CANNOT activate
        $this->assertFalse((bool) $agreement->keys_handed_over);
        $this->assertFalse($this->demandManager->can('activate', $agreement));

        // Operations Executive logs key handover in the field
        $this->assertTrue($this->opsExecutive->can('handoverKeys', $agreement));
        $this->assertTrue($this->demandManager->can('handoverKeys', $agreement));
        $this->assertFalse($this->accountant->can('handoverKeys', $agreement));
        $this->assertFalse($this->supplyManager->can('handoverKeys', $agreement));

        // Confirm Key Handover
        $agreement->keys_handed_over = true;
        $agreement->keys_handed_over_at = now();
        $agreement->save();

        // Now Demand Manager is authorized to activate
        $this->assertTrue($this->demandManager->can('activate', $agreement));
        $this->assertTrue($this->cityManager->can('activate', $agreement));
        $this->assertTrue($this->owner->can('activate', $agreement));

        // Execute activation
        app(ActivateTenancyAction::class)->execute($agreement, $this->demandManager);

        $agreement->refresh();
        $this->property->refresh();

        // Verify active state, occupied property status, and locked move-in audit
        $this->assertEquals('active', $agreement->status);
        $this->assertEquals('occupied', $this->property->status);
        $this->assertTrue($agreement->audit->is_locked);

        // Lifecycle invariant: Active agreement cannot be activated again (even by Owner)
        $this->assertFalse($this->demandManager->can('activate', $agreement));
        $this->assertFalse($this->owner->can('activate', $agreement));
    }

    public function test_lease_renewal_and_deboarding_permissions(): void
    {
        $agreement = app(DraftTenancyAgreementAction::class)->execute(
            $this->property,
            [
                'code' => 'TA-2026-1003',
                'rent_amount' => 20000.00,
                'security_deposit' => 40000.00,
                'start_date' => '2026-08-01',
                'end_date' => '2027-06-30',
            ],
            [
                [
                    'party_id' => $this->tenant->id,
                    'role_type' => 'Primary Tenant',
                    'is_primary' => true,
                ],
            ],
            $this->demandManager
        );

        $agreement->status = 'active';
        $agreement->save();

        // Renewals: Demand Manager, City Manager, Owner
        $this->assertTrue($this->demandManager->can('renew', $agreement));
        $this->assertTrue($this->cityManager->can('renew', $agreement));
        $this->assertTrue($this->owner->can('renew', $agreement));

        $this->assertFalse($this->opsManager->can('renew', $agreement));
        $this->assertFalse($this->opsExecutive->can('renew', $agreement));
        $this->assertFalse($this->accountant->can('renew', $agreement));
        $this->assertFalse($this->supplyManager->can('renew', $agreement));

        // Deboarding: Demand Manager, Operations Manager, City Manager, Owner
        $this->assertTrue($this->demandManager->can('deboard', $agreement));
        $this->assertTrue($this->opsManager->can('deboard', $agreement));
        $this->assertTrue($this->cityManager->can('deboard', $agreement));
        $this->assertTrue($this->owner->can('deboard', $agreement));

        $this->assertFalse($this->opsExecutive->can('deboard', $agreement));
        $this->assertFalse($this->accountant->can('deboard', $agreement));
        $this->assertFalse($this->supplyManager->can('deboard', $agreement));
    }

    public function test_draft_document_generation_permissions(): void
    {
        $agreement = app(DraftTenancyAgreementAction::class)->execute(
            $this->property,
            [
                'code' => 'TA-2026-1004',
                'rent_amount' => 22000.00,
                'security_deposit' => 44000.00,
                'start_date' => '2026-09-01',
                'end_date' => '2027-07-31',
            ],
            [
                [
                    'party_id' => $this->tenant->id,
                    'role_type' => 'Primary Tenant',
                    'is_primary' => true,
                ],
            ],
            $this->demandManager
        );

        // Demand Manager, City Manager, Business Owner can compile drafts
        $this->assertTrue($this->demandManager->can('generateDraft', $agreement));
        $this->assertTrue($this->cityManager->can('generateDraft', $agreement));
        $this->assertTrue($this->owner->can('generateDraft', $agreement));

        // Supply Manager, Accountant, Ops Executive have no access to contract generation
        $this->assertFalse($this->supplyManager->can('generateDraft', $agreement));
        $this->assertFalse($this->accountant->can('generateDraft', $agreement));
        $this->assertFalse($this->opsExecutive->can('generateDraft', $agreement));
    }
}
