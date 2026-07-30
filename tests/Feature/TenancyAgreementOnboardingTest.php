<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Actions\DraftTenancyAgreementAction;
use App\Domain\Property\Models\Property;
use App\Domain\Audit\Models\Audit;
use App\Domain\Party\Models\Party;
use App\Domain\Agreement\Services\TenancyAgreementPdfService;
use App\Domain\Agreement\Services\TenancyAgreementDocxService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TenancyAgreementOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_tenancy_agreement_auto_triggers_move_in_audit_linked_to_tenant()
    {
        $user = User::factory()->create();

        $owner = Party::create([
            'display_name' => 'Jamuna Sharma',
            'party_type' => 'individual',
        ]);

        $property = Property::create([
            'building_name' => 'Tarun Nagar GF1',
            'address_line_1' => 'H/N 27, Tarun Nagar, Guwahati',
            'status' => 'vacant',
        ]);

        $tenant = Party::create([
            'display_name' => 'Tushar Tapan Boruah',
            'phone' => '+91 93941 06806',
            'party_type' => 'individual',
        ]);

        $action = app(DraftTenancyAgreementAction::class);

        $agreement = $action->execute(
            $property,
            [
                'code' => 'TNC-2026-0001',
                'rent_amount' => 16000.00,
                'security_deposit' => 32000.00,
                'start_date' => '2026-07-14',
                'end_date' => '2027-06-14',
            ],
            [
                [
                    'party_id' => $tenant->id,
                    'role_type' => 'Primary Tenant',
                    'is_primary' => true,
                ]
            ],
            $user
        );

        $this->assertNotNull($agreement->audit_id);
        
        $audit = Audit::find($agreement->audit_id);
        $this->assertNotNull($audit);
        $this->assertEquals($property->id, $audit->property_id);
        $this->assertEquals($tenant->id, $audit->tenant_id);
        $this->assertEquals(\App\Domain\Audit\Enums\AuditType::MOVE_IN, $audit->audit_type);
    }

    public function test_can_create_tenancy_agreement_with_inline_new_tenant_party()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Bellevue Flat 302',
            'address_line_1' => 'Zoo Road, Guwahati',
            'status' => 'vacant',
        ]);

        $createPage = new \App\Filament\Resources\TenancyAgreements\Pages\CreateTenancyAgreement();

        // Simulate handleRecordCreation with inline tenant party creation data
        $data = [
            'property_id' => $property->id,
            'start_date' => '2026-08-01',
            'end_date' => '2027-07-31',
            'create_new_tenant' => true,
            'new_tenant' => [
                'display_name' => 'Rahul Das',
                'phone' => '+91 98765 43210',
                'email' => 'rahul@example.com',
                'parent_name' => 'S/o M. L. Das',
                'aadhaar_number' => '1234 5678 9012',
                'pan_number' => 'ABCDE1234F',
                'address_line_1' => 'Ganeshguri, Guwahati',
            ],
            'code' => 'TNC-2026-0002',
            'rent_amount' => 18000.00,
        ];

        // Call handleRecordCreation via Reflection
        $method = new \ReflectionMethod($createPage, 'handleRecordCreation');
        $method->setAccessible(true);
        $agreement = $method->invoke($createPage, $data);

        $this->assertNotNull($agreement);
        $this->assertEquals('TNC-2026-0002', $agreement->code);

        // Verify Party creation
        $party = Party::where('display_name', 'Rahul Das')->first();
        $this->assertNotNull($party);
        $this->assertEquals('+91 98765 43210', $party->phone);
        $this->assertEquals('S/o M. L. Das', $party->individual->parent_name);
        $this->assertTrue($party->hasRole(\App\Domain\Party\Enums\BusinessRole::TENANT));

        // Verify Move-In Audit auto-creation and linkage
        $this->assertNotNull($agreement->audit_id);
        $audit = Audit::find($agreement->audit_id);
        $this->assertEquals($party->id, $audit->tenant_id);
    }

    public function test_rent_is_auto_fetched_from_property_pricing_version()
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Royal Plaza 101',
            'address_line_1' => 'GS Road, Guwahati',
            'status' => 'vacant',
        ]);

        $property->pricingVersions()->create([
            'rent' => 22500.00,
            'security_deposit' => 45000.00,
            'effective_from' => '2026-01-01',
        ]);

        $tenant = Party::create([
            'display_name' => 'Anurag Kashyap',
            'party_type' => 'individual',
        ]);

        $action = app(DraftTenancyAgreementAction::class);
        $agreement = $action->execute(
            $property,
            [
                'start_date' => '2026-08-01',
                'end_date' => '2027-06-30',
            ],
            [
                [
                    'party_id' => $tenant->id,
                    'role_type' => 'Primary Tenant',
                    'is_primary' => true,
                ]
            ],
            $user
        );

        $this->assertEquals(22500.00, (float)$agreement->rent_amount);
        $this->assertEquals(45000.00, (float)$agreement->security_deposit);
    }

    public function test_terms_and_drafts_not_ticked_unless_all_mandatory_fields_filled()
    {
        $property = Property::create([
            'building_name' => 'Greenwood Villa 202',
            'address_line_1' => 'Dispur, Guwahati',
            'status' => 'vacant',
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'TNC-2026-9999',
            'status' => 'draft',
            'start_date' => '2026-08-01',
            'end_date' => '2027-07-31',
            'rent_amount' => 15000.00,
            'security_deposit' => 0.00,
            // missing electricity_provider_id, apdcl_consumer_id, tenant_bank_details
        ]);

        // Should return false because mandatory fields are missing
        $this->assertFalse(\App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm::areTermsComplete($agreement));

        $provider = \App\Domain\Property\Models\UtilityProvider::create([
            'name' => 'APDCL',
            'utility_type_id' => \App\Domain\Property\Models\UtilityType::create(['name' => 'Electricity', 'slug' => 'electricity'])->id,
        ]);

        // Now populate all mandatory fields (leaving special_terms NULL)
        $agreement->update([
            'security_deposit' => 30000.00,
            'lock_in_period_months' => 6,
            'notice_period_days' => 30,
            'electricity_provider_id' => $provider->id,
            'apdcl_consumer_id' => '100123456',
            'special_terms' => null, // Excepted field - optional
            'tenant_bank_details' => [
                'account_holder_name' => 'Test Tenant',
                'bank_name' => 'HDFC Bank',
                'bank_address' => 'Ganeshguri Branch',
                'account_number' => '501002345678',
                'account_type' => 'Saving',
                'ifsc_code' => 'HDFC0000123',
                'pan_number' => 'ABCDE1234F',
            ],
        ]);

        $this->assertTrue(\App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm::areTermsComplete($agreement->fresh()));
    }
}
