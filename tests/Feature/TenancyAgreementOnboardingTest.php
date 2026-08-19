<?php

namespace Tests\Feature;

use App\Domain\Agreement\Actions\ActivateTenancyAction;
use App\Domain\Agreement\Actions\DraftTenancyAgreementAction;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Services\TenancyAgreementDocxService;
use App\Domain\Agreement\Services\TenancyAgreementPdfService;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Enums\ItemCondition;
use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Models\AuditCategory;
use App\Domain\Audit\Models\AuditItem;
use App\Domain\Party\Enums\BusinessRole;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Models\PropertyRoom;
use App\Domain\Property\Models\RoomDefinition;
use App\Domain\Property\Models\RoomType;
use App\Domain\Property\Models\UtilityProvider;
use App\Domain\Property\Models\UtilityType;
use App\Filament\Resources\TenancyAgreements\Pages\CreateTenancyAgreement;
use App\Filament\Resources\TenancyAgreements\Pages\ListTenancyAgreements;
use App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

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
                ],
            ],
            $user
        );

        $this->assertNotNull($agreement->audit_id);

        $audit = Audit::find($agreement->audit_id);
        $this->assertNotNull($audit);
        $this->assertEquals($property->id, $audit->property_id);
        $this->assertEquals($tenant->id, $audit->tenant_id);
        $this->assertEquals(AuditType::MOVE_IN, $audit->audit_type);
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

        $createPage = new CreateTenancyAgreement;

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
        $this->assertTrue($party->hasRole(BusinessRole::TENANT));

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
                ],
            ],
            $user
        );

        $this->assertEquals(22500.00, (float) $agreement->rent_amount);
        $this->assertEquals(45000.00, (float) $agreement->security_deposit);
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
        $this->assertFalse(TenancyAgreementForm::areTermsComplete($agreement));

        $provider = UtilityProvider::create([
            'name' => 'APDCL',
            'utility_type_id' => UtilityType::create(['name' => 'Electricity', 'slug' => 'electricity'])->id,
        ]);

        // Now populate all mandatory fields (leaving special_terms NULL)
        $agreement->update([
            'security_deposit' => 30000.00,
            'first_month_rent' => 15000.00,
            'booking_amount' => 5000.00,
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

        $this->assertTrue(TenancyAgreementForm::areTermsComplete($agreement->fresh()));
    }

    public function test_prorated_agreement_conditionally_mandates_remarks_and_proof()
    {
        $property = Property::create([
            'building_name' => 'Hillside Heights 301',
            'address_line_1' => 'Kahilipara, Guwahati',
            'status' => 'vacant',
        ]);

        $provider = UtilityProvider::create([
            'name' => 'APDCL Urban',
            'utility_type_id' => UtilityType::create(['name' => 'Electricity 3', 'slug' => 'electricity-3'])->id,
        ]);

        // Mid-month lease start date: 2026-08-16 (Prorated)
        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'TNC-2026-7777',
            'status' => 'draft',
            'start_date' => '2026-08-16',
            'end_date' => '2027-08-15',
            'rent_amount' => 20000.00,
            'first_month_rent' => 10322.58,
            'security_deposit' => 40000.00,
            'booking_amount' => 5000.00,
            'lock_in_period_months' => 6,
            'notice_period_days' => 30,
            'electricity_provider_id' => $provider->id,
            'apdcl_consumer_id' => '100444555',
            'tenant_bank_details' => [
                'account_holder_name' => 'Prorated Tenant',
                'bank_name' => 'Axis Bank',
                'bank_address' => 'Ganeshguri Branch',
                'account_number' => '91201002345678',
                'account_type' => 'Saving',
                'ifsc_code' => 'UTIB0000123',
                'pan_number' => 'ABCDE7777F',
            ],
            // missing first_month_rent_notes and first_month_rent_proof
        ]);

        // Should return FALSE because mid-month move-in mandates remarks and proof
        $this->assertTrue(TenancyAgreementForm::isRecordProrated($agreement));
        $this->assertFalse(TenancyAgreementForm::areTermsComplete($agreement));

        // Add remarks only (still missing proof)
        $agreement->update([
            'first_month_rent_notes' => '16 days prorated calculation approved by sales head.',
        ]);
        $this->assertFalse(TenancyAgreementForm::areTermsComplete($agreement->fresh()));

        // Attach proof media
        $proofFile = UploadedFile::fake()->create('proration_approval.pdf', 50, 'application/pdf');
        $agreement->addMedia($proofFile)->toMediaCollection('first_month_rent_proof');

        // Now terms are complete
        $this->assertTrue(TenancyAgreementForm::areTermsComplete($agreement->fresh()));
    }

    public function test_activation_requires_all_checks_and_permanently_locks_linked_audit()
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Sunset Apartments 404',
            'address_line_1' => 'Zoo Road, Guwahati',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::APPROVED,
        ]);

        $provider = UtilityProvider::create([
            'name' => 'APDCL Test',
            'utility_type_id' => UtilityType::create(['name' => 'Electricity 2', 'slug' => 'electricity-2'])->id,
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'audit_id' => $audit->id,
            'code' => 'TNC-2026-8888',
            'status' => 'draft',
            'start_date' => '2026-08-01',
            'end_date' => '2027-07-31',
            'rent_amount' => 20000.00,
            'first_month_rent' => 20000.00,
            'security_deposit' => 40000.00,
            'booking_amount' => 5000.00,
            'lock_in_period_months' => 6,
            'notice_period_days' => 30,
            'electricity_provider_id' => $provider->id,
            'apdcl_consumer_id' => '100987654',
            'tenant_bank_details' => [
                'account_holder_name' => 'Test Tenant',
                'bank_name' => 'SBI',
                'bank_address' => 'Dispur',
                'account_number' => '30100987654',
                'account_type' => 'Saving',
                'ifsc_code' => 'SBIN0000123',
                'pan_number' => 'ABCDE9999F',
            ],
            'signed_by_tenant' => false,
            'keys_handed_over' => false,
        ]);

        // Activation check fails because signed_by_tenant, signed agreement pdf, and keys_handed_over are missing
        $this->assertFalse(TenancyAgreementForm::canActivateTenancy($agreement));
        $pending = TenancyAgreementForm::getPendingActivationRequirements($agreement);
        $this->assertNotEmpty($pending);

        // Update signed_by_tenant, signed_at, keys_handed_over, and keys_handed_over_at, attach dummy signed_agreement PDF
        $agreement->update([
            'signed_by_tenant' => true,
            'signed_at' => '2026-08-01',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-08-01',
        ]);

        // Attach dummy PDF media to 'signed_agreement' collection
        $file = UploadedFile::fake()->create('signed_agreement.pdf', 100, 'application/pdf');
        $agreement->addMedia($file)->toMediaCollection('signed_agreement');

        $this->assertTrue(TenancyAgreementForm::canActivateTenancy($agreement->fresh()));

        // Perform Activation
        app(ActivateTenancyAction::class)->execute($agreement->fresh(), $user);

        $agreement->refresh();
        $audit->refresh();

        $this->assertEquals('active', $agreement->status);
        $this->assertEquals('occupied', $property->fresh()->status);
        $this->assertTrue($audit->is_locked);
        $this->assertEquals(AuditStatus::COMPLETED, $audit->status);
    }

    public function test_annexure_iii_organizes_audit_items_by_room()
    {
        $property = Property::create([
            'building_name' => 'Royal Palms 501',
            'address_line_1' => 'Zoo Road, Guwahati',
            'status' => 'vacant',
        ]);

        $roomType = RoomType::create(['name' => 'Bedroom', 'slug' => 'bedroom']);
        $roomDef = RoomDefinition::create(['room_type_id' => $roomType->id, 'name' => 'Bedroom', 'slug' => 'bedroom']);

        $bedroom = PropertyRoom::create([
            'property_id' => $property->id,
            'room_definition_id' => $roomDef->id,
            'custom_name' => 'Master Bedroom',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::APPROVED,
        ]);

        $categoryRooms = AuditCategory::create([
            'audit_id' => $audit->id,
            'name' => 'Rooms',
        ]);

        $categoryInv = AuditCategory::create([
            'audit_id' => $audit->id,
            'name' => 'Inventory',
        ]);

        AuditItem::create([
            'audit_category_id' => $categoryRooms->id,
            'name' => 'Master Bedroom',
            'source_type' => get_class($bedroom),
            'source_id' => $bedroom->id,
            'condition' => ItemCondition::GOOD,
        ]);

        AuditItem::create([
            'audit_category_id' => $categoryInv->id,
            'name' => 'King Size Bed (Master Bedroom)',
            'snapshot_data' => [
                'property_room_id' => $bedroom->id,
            ],
            'condition' => ItemCondition::EXCELLENT,
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'audit_id' => $audit->id,
            'code' => 'TNC-2026-7777',
            'status' => 'draft',
            'rent_amount' => 15000.00,
            'security_deposit' => 30000.00,
        ]);

        $pdfService = app(TenancyAgreementPdfService::class);
        $grouped = $pdfService->organizeAuditByRoom($agreement->fresh());

        $this->assertNotEmpty($grouped);
        $this->assertEquals('Master Bedroom', $grouped[0]['room_name']);
        $this->assertCount(1, $grouped[0]['items']);
        $this->assertEquals('King Size Bed', $grouped[0]['items'][0]->display_name);
    }

    public function test_first_month_rent_supports_manual_input_and_pro_rated_calculation()
    {
        // 1. Pro-rated calculation test (mid-month start date: Aug 16th, 31 days in Aug, 16 active days => 20000 / 31 * 16 = 10322.58)
        $proRated = TenancyAgreementForm::calculateProRatedFirstMonthRent('2026-08-16', 20000.00);
        $this->assertEquals(10322.58, $proRated);

        // 2. Full month test (1st of month => 20000.00)
        $fullMonth = TenancyAgreementForm::calculateProRatedFirstMonthRent('2026-08-01', 20000.00);
        $this->assertEquals(20000.00, $fullMonth);

        // 3. Manual override saving test on TenancyAgreement
        $property = Property::create([
            'building_name' => 'Green Heights 102',
            'address_line_1' => 'GS Road, Guwahati',
            'status' => 'vacant',
        ]);

        $user = User::factory()->create();

        $action = app(DraftTenancyAgreementAction::class);
        $agreement = $action->execute($property, [
            'start_date' => '2026-08-16',
            'rent_amount' => 20000.00,
            'first_month_rent' => 12500.00, // Manual custom override
        ], [], $user);

        $this->assertEquals(12500.00, $agreement->first_month_rent);
    }

    public function test_create_tenancy_agreement_only_shows_vacant_properties()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 1. Create properties with different statuses
        $vacantProperty1 = Property::create([
            'building_name' => 'Vacant Apartment 101',
            'code' => 'VAC-101',
            'address_line_1' => 'Zoo Road, Guwahati',
            'status' => 'vacant',
        ]);

        $vacantProperty2 = Property::create([
            'building_name' => 'Vacant Apartment 102',
            'code' => 'VAC-102',
            'address_line_1' => 'Beltola, Guwahati',
            'status' => 'Vacant',
        ]);

        $occupiedProperty = Property::create([
            'building_name' => 'Occupied Apartment 201',
            'code' => 'OCC-201',
            'address_line_1' => 'GS Road, Guwahati',
            'status' => 'occupied',
        ]);

        $draftProperty = Property::create([
            'building_name' => 'Draft Apartment 301',
            'code' => 'DFT-301',
            'address_line_1' => 'Six Mile, Guwahati',
            'status' => 'draft',
        ]);

        // 2. Test Create Tenancy Agreement Page Form
        $testable = Livewire::test(CreateTenancyAgreement::class);

        $form = $testable->instance()->form;
        $propertyComponent = $form->getComponent('property_id');
        $this->assertNotNull($propertyComponent);

        $options = $propertyComponent->getOptions();

        // 3. Assert only vacant properties are present in the options list
        $this->assertArrayHasKey($vacantProperty1->id, $options);
        $this->assertArrayHasKey($vacantProperty2->id, $options);
        $this->assertArrayNotHasKey($occupiedProperty->id, $options);
        $this->assertArrayNotHasKey($draftProperty->id, $options);

        // 4. Assert that non-vacant property cannot be submitted due to validation
        $testable
            ->fillForm([
                'property_id' => $occupiedProperty->id,
                'create_new_tenant' => true,
                'new_tenant' => [
                    'display_name' => 'Test Tenant',
                    'phone' => '9876543210',
                    'parent_name' => 'Father Name',
                    'address_line_1' => 'Address 1',
                    'aadhaar_number' => '123456789012',
                    'pan_number' => 'ABCDE1234F',
                ],
                'start_date' => '2026-09-01',
                'end_date' => '2027-08-31',
                'first_month_rent' => 15000,
                'booking_amount' => 5000,
            ])
            ->call('create')
            ->assertHasFormErrors(['property_id']);
    }

    public function test_docx_generation_produces_complete_valid_document()
    {
        $property = Property::create([
            'building_name' => 'Word Doc Tower 101',
            'address_line_1' => 'Beltola Road, Guwahati',
            'status' => 'vacant',
        ]);

        $tenant = Party::create([
            'display_name' => 'Bikash Kalita',
            'phone' => '9876543210',
            'party_type' => 'individual',
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'TNC-2026-DOCX',
            'status' => 'draft',
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
            'rent_amount' => 18000.00,
            'security_deposit' => 36000.00,
            'lock_in_period_months' => 6,
            'notice_period_days' => 30,
            'apdcl_consumer_id' => '100555666',
            'special_terms' => 'Tenant must park vehicle in slot #4.',
            'secondary_tenants' => [
                ['name' => 'Pooja Kalita', 'relationship' => 'Spouse'],
            ],
            'tenant_bank_details' => [
                'account_holder_name' => 'Bikash Kalita',
                'bank_name' => 'State Bank of India',
                'bank_address' => 'Beltola Branch',
                'account_number' => '30123456789',
                'account_type' => 'Saving',
                'ifsc_code' => 'SBIN0001234',
                'pan_number' => 'ABCDE1234F',
            ],
        ]);

        $agreement->roles()->create([
            'party_id' => $tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        $docxService = app(TenancyAgreementDocxService::class);
        $binary = $docxService->generateDocx($agreement);

        $this->assertNotEmpty($binary);
        $this->assertStringStartsWith('PK', $binary); // Zip/Docx magic bytes
    }

    public function test_can_render_tenancy_agreements_list_page_with_tabs()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ListTenancyAgreements::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([]);
    }
}
