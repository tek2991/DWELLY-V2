<?php

namespace Tests\Feature\Authorization;

use App\Domain\Mou\Enums\MouType;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Property\Models\OnboardingProject;
use App\Domain\Property\Models\Property;
use App\Filament\Pages\Properties\ReviewQueue;
use App\Filament\Resources\Properties\Pages\PropertyFinancials;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyAndOnboardingRbacTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $cityManager;

    protected User $supplyManager;

    protected User $demandManager;

    protected User $opsManager;

    protected User $opsExecutive;

    protected User $accountant;

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
    }

    public function test_opportunity_pipeline_access_boundaries(): void
    {
        // Supply Manager has full pipeline management
        $this->assertTrue($this->supplyManager->can('viewAny', Opportunity::class));
        $this->assertTrue($this->supplyManager->can('create', Opportunity::class));

        // City Manager has full city pipeline authority
        $this->assertTrue($this->cityManager->can('viewAny', Opportunity::class));
        $this->assertTrue($this->cityManager->can('create', Opportunity::class));

        // Operations Manager has read-only pipeline visibility for onboarding forecasting
        $this->assertTrue($this->opsManager->can('viewAny', Opportunity::class));
        $this->assertFalse($this->opsManager->can('create', Opportunity::class));

        // Demand Manager, Ops Executive, and Accountant have NO access to supply leads
        $this->assertFalse($this->demandManager->can('viewAny', Opportunity::class));
        $this->assertFalse($this->demandManager->can('create', Opportunity::class));

        $this->assertFalse($this->opsExecutive->can('viewAny', Opportunity::class));
        $this->assertFalse($this->opsExecutive->can('create', Opportunity::class));

        $this->assertFalse($this->accountant->can('viewAny', Opportunity::class));
        $this->assertFalse($this->accountant->can('create', Opportunity::class));
    }

    public function test_mou_drafting_and_four_eyes_verification_gate(): void
    {
        $opportunity = Opportunity::create([
            'number' => 'OPP-101',
            'title' => 'Greenwood Apartments Supply Lead',
            'owner_name' => 'Robert Landlord',
            'owner_phone' => '9888877777',
            'assigned_user_id' => $this->supplyManager->id,
            'status' => OpportunityStatus::NEW,
        ]);

        $mou = Mou::create([
            'number' => 'MOU-101',
            'opportunity_id' => $opportunity->id,
            'status' => MouStatus::SIGNED_COPY_UPLOADED,
            'type' => MouType::ONBOARDING,
            'owner_name' => 'Robert Landlord',
            'owner_phone' => '9888877777',
        ]);

        // 1. Supply Manager can draft & update MOU
        $this->assertTrue($this->supplyManager->can('viewAny', Mou::class));
        $this->assertTrue($this->supplyManager->can('create', Mou::class));

        // 2. CRITICAL MAKER-CHECKER RULE: Supply Manager CANNOT verify signed MOU
        $this->assertFalse($this->supplyManager->can('verify', $mou));

        // 3. Checkers (Operations Manager, City Manager, Owner) CAN verify signed MOU
        $this->assertTrue($this->opsManager->can('verify', $mou));
        $this->assertTrue($this->cityManager->can('verify', $mou));
        $this->assertTrue($this->owner->can('verify', $mou));

        // 4. Demand Manager and Ops Executive are forbidden
        $this->assertFalse($this->demandManager->can('viewAny', Mou::class));
        $this->assertFalse($this->opsExecutive->can('viewAny', Mou::class));
    }

    public function test_property_conversion_gate(): void
    {
        $opportunity = Opportunity::create([
            'number' => 'OPP-102',
            'title' => 'Lakeview Heights Unit',
            'owner_name' => 'Alice Landlord',
            'owner_phone' => '9111122222',
            'assigned_user_id' => $this->supplyManager->id,
            'status' => OpportunityStatus::NEW,
        ]);

        $unverifiedMou = Mou::create([
            'number' => 'MOU-102',
            'opportunity_id' => $opportunity->id,
            'status' => MouStatus::SIGNED_COPY_UPLOADED,
            'type' => MouType::ONBOARDING,
            'owner_name' => 'Alice Landlord',
            'owner_phone' => '9111122222',
        ]);

        // Cannot convert unverified MOU
        $this->assertFalse($this->supplyManager->can('convert', $unverifiedMou));
        $this->assertFalse($this->opsManager->can('convert', $unverifiedMou));

        // Once verified, Supply Manager or Ops Manager can trigger conversion
        $unverifiedMou->update(['status' => MouStatus::VERIFIED]);

        $this->assertTrue($this->supplyManager->can('convert', $unverifiedMou));
        $this->assertTrue($this->opsManager->can('convert', $unverifiedMou));
        $this->assertTrue($this->cityManager->can('convert', $unverifiedMou));
        $this->assertTrue($this->owner->can('convert', $unverifiedMou));
    }

    public function test_property_onboarding_and_review_queue_activation_gate(): void
    {
        $property = Property::create([
            'building_name' => 'Sunshine Residency 401',
            'status' => 'draft',
        ]);

        $onboardingProject = OnboardingProject::create([
            'property_id' => $property->id,
            'status' => 'Draft',
        ]);

        // 1. Makers (Supply Manager and Ops Executive) can update during onboarding
        $this->assertTrue($this->supplyManager->can('update', $property));
        $this->assertTrue($this->opsExecutive->can('update', $property));

        // 2. CRITICAL GATE: Only Checkers can review and activate properties
        $this->assertTrue($this->opsManager->can('review', $property));
        $this->assertTrue($this->opsManager->can('activate', $property));

        $this->assertTrue($this->cityManager->can('review', $property));
        $this->assertTrue($this->cityManager->can('activate', $property));

        $this->assertTrue($this->owner->can('review', $property));
        $this->assertTrue($this->owner->can('activate', $property));

        // 3. Supply Manager, Ops Executive, Demand Manager, Accountant CANNOT activate
        $this->assertFalse($this->supplyManager->can('activate', $property));
        $this->assertFalse($this->opsExecutive->can('activate', $property));
        $this->assertFalse($this->demandManager->can('activate', $property));
        $this->assertFalse($this->accountant->can('activate', $property));

        // 4. Check ReviewQueue access
        $this->actingAs($this->opsManager);
        $this->assertTrue(ReviewQueue::canAccess());

        $this->actingAs($this->cityManager);
        $this->assertTrue(ReviewQueue::canAccess());

        $this->actingAs($this->supplyManager);
        $this->assertFalse(ReviewQueue::canAccess());

        $this->actingAs($this->demandManager);
        $this->assertFalse(ReviewQueue::canAccess());

        $this->actingAs($this->opsExecutive);
        $this->assertFalse(ReviewQueue::canAccess());
    }

    public function test_property_financials_access_and_masking_policy(): void
    {
        $property = Property::create([
            'building_name' => 'Prestige Heights 102',
            'status' => 'Vacant',
        ]);

        // 1. Accountant, City Manager, Owner have full access
        $this->actingAs($this->accountant);
        $this->assertTrue(PropertyFinancials::canAccess(['record' => $property->id]));
        $this->assertTrue($this->accountant->can('property.bank.view_unmasked'));
        $this->assertTrue($this->accountant->can('property.bank.push'));

        $this->actingAs($this->cityManager);
        $this->assertTrue(PropertyFinancials::canAccess(['record' => $property->id]));
        $this->assertTrue($this->cityManager->can('property.bank.view_unmasked'));

        $this->actingAs($this->owner);
        $this->assertTrue(PropertyFinancials::canAccess(['record' => $property->id]));

        // 2. Supply Manager and Ops Manager have read-only terms view without unmasked bank details
        $this->actingAs($this->supplyManager);
        $this->assertTrue(PropertyFinancials::canAccess(['record' => $property->id]));
        $this->assertFalse($this->supplyManager->can('property.bank.view_unmasked'));
        $this->assertFalse($this->supplyManager->can('property.bank.push'));

        $this->actingAs($this->opsManager);
        $this->assertTrue(PropertyFinancials::canAccess(['record' => $property->id]));
        $this->assertFalse($this->opsManager->can('property.bank.view_unmasked'));
        $this->assertFalse($this->opsManager->can('property.bank.push'));

        // 3. Demand Manager and Operations Executive are FORBIDDEN from viewing financials
        $this->actingAs($this->demandManager);
        $this->assertFalse(PropertyFinancials::canAccess(['record' => $property->id]));

        $this->actingAs($this->opsExecutive);
        $this->assertFalse(PropertyFinancials::canAccess(['record' => $property->id]));
    }
}
