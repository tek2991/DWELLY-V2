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
use App\Filament\Resources\TenancyAgreements\Pages\ManageAgreementDocuments;
use App\Filament\Resources\TenancyAgreements\Pages\ManageSecondaryTenants;
use App\Filament\Resources\TenancyAgreements\Pages\ManageTenantKyc;
use App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm;
use Database\Seeders\KycDocumentTypeSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_create_tenancy_agreement_has_merged_identity_and_kyc_fields()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $testable = Livewire::test(CreateTenancyAgreement::class);
        $form = $testable->instance()->form;

        // Verify photo, aadhaar, and pan fields are present in the merged KYC section
        $this->assertNotNull($form->getComponent('tenant_photo'));
        $this->assertNotNull($form->getComponent('new_tenant.aadhaar_number'));
        $this->assertNotNull($form->getComponent('tenant_aadhaar'));
        $this->assertNotNull($form->getComponent('new_tenant.pan_number'));
        $this->assertNotNull($form->getComponent('tenant_pan'));

        // Verify document type options helper returns options for primary tenant
        $docTypes = \App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm::getDocumentTypeOptions('primary_tenant');
        $this->assertNotEmpty($docTypes);
        $this->assertArrayHasKey('aadhaar', $docTypes);
    }

    public function test_create_tenancy_agreement_property_dropdown_options_format_code_then_name()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'code' => 'GAU 001',
            'building_name' => 'Tarun Nagar GF1',
            'address_line_1' => 'H/N 27, Tarun Nagar, Guwahati',
            'status' => 'vacant',
        ]);

        $testable = Livewire::test(CreateTenancyAgreement::class);
        $form = $testable->instance()->form;

        /** @var \Filament\Forms\Components\Select $propertySelect */
        $propertySelect = $form->getComponent('property_id');
        $this->assertNotNull($propertySelect);

        $options = $propertySelect->getOptions();
        $this->assertArrayHasKey($property->id, $options);
        $this->assertEquals('GAU 001 - Tarun Nagar GF1', $options[$property->id]);
    }

    public function test_manage_agreement_documents_has_dynamic_tenant_kyc_and_prefills_identity_numbers()
    {
        $this->seed(KycDocumentTypeSeeder::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Kyc Test Building',
            'address_line_1' => 'Bhangagarh, Guwahati',
            'status' => 'vacant',
        ]);

        $tenant = Party::create([
            'display_name' => 'Rahul Talukdar',
            'phone' => '9876543210',
            'party_type' => 'individual',
        ]);

        $tenant->individual()->create([
            'name' => 'Rahul Talukdar',
            'aadhaar_number' => '112233445566',
            'pan_number' => 'ABCDE9999Z',
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'TNC-2026-KYC-01',
            'status' => 'draft',
            'start_date' => '2026-10-01',
            'end_date' => '2027-08-31',
            'rent_amount' => 15000,
            'security_deposit' => 30000,
        ]);

        $agreement->roles()->create([
            'party_id' => $tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        $testable = Livewire::test(ManageTenantKyc::class, ['record' => $agreement->id]);
        $form = $testable->instance()->form;

        // Verify dynamic KYC components exist
        $this->assertNotNull($form->getComponent('tenant_photo'));
        $this->assertNotNull($form->getComponent('new_tenant.aadhaar_number'));
        $this->assertNotNull($form->getComponent('tenant_aadhaar'));
        $this->assertNotNull($form->getComponent('new_tenant.pan_number'));
        $this->assertNotNull($form->getComponent('tenant_pan'));
        $this->assertNotNull($form->getComponent('cancelled_cheque'));

        // Verify identity numbers pre-fill from party individual
        $testable->assertFormSet([
            'new_tenant.aadhaar_number' => '112233445566',
            'new_tenant.pan_number' => 'ABCDE9999Z',
        ]);
    }

    public function test_manage_tenant_kyc_can_save_updated_tenant_identity_numbers()
    {
        $this->seed(KycDocumentTypeSeeder::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Save Kyc Test Building',
            'address_line_1' => 'Ulubari, Guwahati',
            'status' => 'vacant',
        ]);

        $tenant = Party::create([
            'display_name' => 'Naveen Bora',
            'phone' => '9876543211',
            'party_type' => 'individual',
        ]);

        $tenant->individual()->create([
            'name' => 'Naveen Bora',
            'aadhaar_number' => '111122223333',
            'pan_number' => 'AAAAA1111A',
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'TNC-2026-KYC-02',
            'status' => 'draft',
            'start_date' => '2026-10-01',
            'end_date' => '2027-08-31',
            'rent_amount' => 15000,
            'security_deposit' => 30000,
        ]);

        $agreement->roles()->create([
            'party_id' => $tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        $file = UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf');
        $agreement->addMedia($file)->toMediaCollection('signed_agreement');
        $agreement->update([
            'signed_at' => '2026-10-01 10:00:00',
            'signed_by_tenant' => true,
        ]);

        Livewire::test(ManageTenantKyc::class, ['record' => $agreement->id])
            ->fillForm([
                'new_tenant.aadhaar_number' => '999988887777',
                'new_tenant.pan_number' => 'BBBBB2222B',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('999988887777', $tenant->individual->fresh()->aadhaar_number);
        $this->assertEquals('BBBBB2222B', $tenant->individual->fresh()->pan_number);
    }

    public function test_manage_tenant_kyc_upload_document_modal_attaches_media_and_syncs_number()
    {
        $this->seed(KycDocumentTypeSeeder::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Modal Upload Test Building',
            'address_line_1' => 'Zoo Road, Guwahati',
            'status' => 'vacant',
        ]);

        $tenant = Party::create([
            'display_name' => 'Dipankar Barman',
            'phone' => '9876543299',
            'party_type' => 'individual',
        ]);

        $tenant->individual()->create([
            'name' => 'Dipankar Barman',
            'aadhaar_number' => '555566667777',
            'pan_number' => 'ABCDE1111X',
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'TNC-2026-KYC-03',
            'status' => 'draft',
            'start_date' => '2026-10-01',
            'end_date' => '2027-08-31',
            'rent_amount' => 15000,
            'security_deposit' => 30000,
        ]);

        $agreement->roles()->create([
            'party_id' => $tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        \Illuminate\Support\Facades\Storage::fake('public');
        $uploaded = UploadedFile::fake()->create('voter_card.pdf', 100, 'application/pdf');
        $storedPath = $uploaded->store('temp-uploads', 'public');

        // Optional document is not rendered initially on fresh form
        $testable = Livewire::test(ManageTenantKyc::class, ['record' => $agreement->id]);
        $this->assertNull($testable->instance()->form->getComponent('tenant_voter_id'));

        // Action definition check
        $action = TenancyAgreementForm::getTenantKycUploadAction();
        $this->assertEquals('uploadDocumentModal', $action->getName());

        // Execute action callback directly
        $actionClosure = $action->getActionFunction();
        $actionClosure([
            'document_type' => 'voter_id',
            'document_number' => 'VOTER998877',
            'files' => [$storedPath],
        ], $agreement);

        $agreement->refresh();
        $this->assertTrue($agreement->hasMedia('tenant_voter_id'));
        $this->assertEquals('VOTER998877', $tenant->individual->fresh()->voter_id);

        // After upload, voter id component is visible on form
        $updatedTestable = Livewire::test(ManageTenantKyc::class, ['record' => $agreement->id]);
        $this->assertNotNull($updatedTestable->instance()->form->getComponent('tenant_voter_id'));
        $this->assertNotNull($updatedTestable->instance()->form->getComponent('new_tenant.voter_id'));
    }

    public function test_manage_agreement_documents_generates_versioned_draft_documents_and_populates_media()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Drafts Test Building',
            'address_line_1' => 'Ganesguri, Guwahati',
            'status' => 'vacant',
        ]);

        $tenant = Party::create([
            'display_name' => 'Draft Test Tenant',
            'phone' => '9876543212',
            'party_type' => 'individual',
        ]);

        $tenant->individual()->create([
            'name' => 'Draft Test Tenant',
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'TNC-2026-DFT-01',
            'status' => 'draft',
            'start_date' => '2026-11-01',
            'end_date' => '2027-09-30',
            'rent_amount' => 18000,
            'security_deposit' => 36000,
            'version' => 1,
        ]);

        $agreement->roles()->create([
            'party_id' => $tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        $testable = Livewire::test(ManageAgreementDocuments::class, ['record' => $agreement->id]);

        // First generation
        $testable->call('generateDraftDocuments');

        $agreement->refresh();
        $this->assertTrue($agreement->hasMedia('draft_pdf'));
        $this->assertTrue($agreement->hasMedia('draft_word'));
        $this->assertEquals(1, $agreement->version);

        $latestPdf = $agreement->getLatestDraftPdf();
        $this->assertNotNull($latestPdf);
        $this->assertEquals(1, $latestPdf->getCustomProperty('version'));

        // Second generation increments version
        $testable->call('generateDraftDocuments');
        $agreement->refresh();
        $this->assertEquals(2, $agreement->version);
        $this->assertCount(2, $agreement->getMedia('draft_pdf'));
        $this->assertCount(2, $agreement->getMedia('draft_word'));

        $newLatestPdf = $agreement->getLatestDraftPdf();
        $this->assertEquals(2, $newLatestPdf->getCustomProperty('version'));
    }

    public function test_manage_agreement_documents_can_preview_and_download_drafts()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Preview Test Building',
            'address_line_1' => 'Panbazar, Guwahati',
            'status' => 'vacant',
        ]);

        $tenant = Party::create([
            'display_name' => 'Preview Tenant',
            'phone' => '9876543213',
            'party_type' => 'individual',
        ]);

        $tenant->individual()->create([
            'name' => 'Preview Tenant',
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'TNC-2026-DFT-02',
            'status' => 'draft',
            'start_date' => '2026-11-01',
            'end_date' => '2027-09-30',
            'rent_amount' => 20000,
            'security_deposit' => 40000,
            'version' => 1,
        ]);

        $agreement->roles()->create([
            'party_id' => $tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        $testable = Livewire::test(ManageAgreementDocuments::class, ['record' => $agreement->id]);

        // Preview mounts viewHistoryPdf action
        $testable->call('previewDraftPdf')
            ->assertActionMounted('viewHistoryPdf');

        // Download draft pdf returns a download response
        $pdfResponse = $testable->instance()->downloadDraftPdf();
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $pdfResponse);

        // Download draft word returns a download response
        $wordResponse = $testable->instance()->downloadDraftWord();
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $wordResponse);
    }

    public function test_tenancy_agreement_draft_customizer_action_and_compilation()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Customizer Test Building',
            'address_line_1' => 'Ulubari, Guwahati',
            'status' => 'vacant',
        ]);

        $tenant = Party::create([
            'display_name' => 'Customizer Tenant',
            'phone' => '9876543214',
            'party_type' => 'individual',
        ]);

        $tenant->individual()->create([
            'name' => 'Customizer Tenant',
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'TNC-2026-CUST-01',
            'status' => 'draft',
            'start_date' => '2026-11-01',
            'end_date' => '2027-09-30',
            'rent_amount' => 25000,
            'security_deposit' => 50000,
            'version' => 1,
        ]);

        $agreement->roles()->create([
            'party_id' => $tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        $customClause = '<p>Customized Clause: The tenant agrees to absolute quiet hours between 10 PM and 7 AM.</p>';

        $testable = Livewire::test(ManageAgreementDocuments::class, ['record' => $agreement->id])
            ->assertActionVisible('draftAgreement')
            ->mountAction('draftAgreement')
            ->setActionData([
                'agreement_content' => $customClause,
            ])
            ->callMountedAction();

        $agreement->refresh();
        $this->assertStringContainsString('Customized Clause: The tenant agrees to absolute quiet hours', $agreement->agreement_content);
        $this->assertNotNull($agreement->agreement_customized_at);
        $this->assertTrue($agreement->hasMedia('draft_pdf'));
        $this->assertTrue($agreement->hasMedia('draft_word'));

        // Verify PDF template compiler resolves custom content
        $templateService = app(\App\Domain\Agreement\Services\TenancyAgreementTemplateService::class);
        $compiled = $templateService->compileForPdf($agreement);
        $this->assertStringContainsString('Customized Clause: The tenant agrees to absolute quiet hours', $compiled);
    }

    public function test_tenancy_agreement_reset_customizer_template()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Reset Test Building',
            'address_line_1' => 'Dispur, Guwahati',
            'status' => 'vacant',
        ]);

        $tenant = Party::create([
            'display_name' => 'Reset Tenant',
            'phone' => '9876543215',
            'party_type' => 'individual',
        ]);

        $tenant->individual()->create([
            'name' => 'Reset Tenant',
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'TNC-2026-RST-01',
            'status' => 'draft',
            'start_date' => '2026-11-01',
            'end_date' => '2027-09-30',
            'rent_amount' => 22000,
            'security_deposit' => 44000,
            'version' => 1,
            'agreement_content' => '<p>Some custom content that should be cleared</p>',
            'agreement_customized_at' => now(),
        ]);

        $agreement->roles()->create([
            'party_id' => $tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        $this->assertNotNull($agreement->agreement_content);

        // Mount customizer action and verify action is mounted
        $testable = Livewire::test(ManageAgreementDocuments::class, ['record' => $agreement->id])
            ->mountAction('draftAgreement')
            ->assertActionMounted('draftAgreement');

        // Verify resetting clears custom content and restores default template
        $agreement->update([
            'agreement_content' => null,
            'agreement_customized_at' => null,
        ]);

        $agreement->refresh();
        $this->assertNull($agreement->agreement_content);
        $this->assertNull($agreement->agreement_customized_at);

        $templateService = app(\App\Domain\Agreement\Services\TenancyAgreementTemplateService::class);
        $fresh = $templateService->getDefaultContent($agreement, true);
        $this->assertStringContainsString('Reset Tenant', $fresh);
        $this->assertStringContainsString('Reset Test Building', $fresh);
    }

    public function test_tenancy_agreement_merged_tenant_kyc_and_occupants_tab()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Subnav Test Building',
            'address_line_1' => 'Six Mile, Guwahati',
            'status' => 'vacant',
        ]);

        $tenant = Party::create([
            'display_name' => 'Subnav Tenant',
            'phone' => '9876543216',
            'party_type' => 'individual',
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'TNC-2026-NAV-01',
            'status' => 'draft',
            'start_date' => '2026-11-01',
            'end_date' => '2027-09-30',
            'rent_amount' => 18000,
            'security_deposit' => 36000,
        ]);

        $agreement->roles()->create([
            'party_id' => $tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        // ManageSecondaryTenants is now the merged tab (3. Tenant KYC & Occupants)
        $testable = Livewire::test(ManageSecondaryTenants::class, ['record' => $agreement->id])
            ->assertSuccessful();

        $form = $testable->instance()->form;
        // Contains Primary Tenant KYC components
        $this->assertNotNull($form->getComponent('tenant_photo'));
        $this->assertNotNull($form->getComponent('new_tenant.aadhaar_number'));
        $this->assertNotNull($form->getComponent('tenant_aadhaar'));

        // Contains Secondary Tenants repeater
        $this->assertNotNull($form->getComponent('secondary_tenants'));

        // Can mount ManageAgreementDocuments page directly as step 4
        Livewire::test(ManageAgreementDocuments::class, ['record' => $agreement->id])
            ->assertSuccessful();
    }

    public function test_secondary_tenants_kyc_dynamic_cards_and_saving()
    {
        $this->seed(KycDocumentTypeSeeder::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Secondary KYC Building',
            'address_line_1' => 'Ulubari, Guwahati',
            'status' => 'vacant',
        ]);

        $tenant = Party::create([
            'display_name' => 'Primary Tenant Person',
            'phone' => '9876543299',
            'party_type' => 'individual',
        ]);

        $disk = config('filament.default_filesystem_disk', 'public');
        Storage::fake($disk);
        Storage::disk($disk)->put('tenancy-secondary-kyc/priya_photo.jpg', 'fake-photo');
        Storage::disk($disk)->put('tenancy-secondary-kyc/priya_aadhaar.pdf', 'fake-aadhaar');
        Storage::disk($disk)->put('tenancy-secondary-kyc/priya_pan.jpg', 'fake-pan');
        Storage::disk($disk)->put('tenancy-secondary-kyc/priya_voter.jpg', 'fake-voter');
        Storage::disk($disk)->put('tenancy-secondary-kyc/marriage_certificate.pdf', 'fake-cert');

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'TNC-SEC-01',
            'status' => 'draft',
            'start_date' => '2026-11-01',
            'end_date' => '2027-09-30',
            'rent_amount' => 20000,
            'security_deposit' => 40000,
            'secondary_tenants' => [
                [
                    'name' => 'Priya Sharma',
                    'relationship' => 'Spouse',
                    'phone' => '9876543201',
                    'email' => 'priya@example.com',
                    'aadhaar_number' => '1234-5678-9012',
                    'pan_number' => 'ABCDE1234F',
                    'voter_id' => 'VTR1234567',
                    'photo_file' => 'tenancy-secondary-kyc/priya_photo.jpg',
                    'aadhaar_file' => 'tenancy-secondary-kyc/priya_aadhaar.pdf',
                    'pan_file' => 'tenancy-secondary-kyc/priya_pan.jpg',
                    'voter_id_file' => 'tenancy-secondary-kyc/priya_voter.jpg',
                    'other_kyc_files' => ['tenancy-secondary-kyc/marriage_certificate.pdf'],
                ],
            ],
        ]);

        $agreement->roles()->create([
            'party_id' => $tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        // Verify options for secondary tenant KYC modal
        $options = TenancyAgreementForm::getDocumentTypeOptions('secondary_tenant');
        $this->assertArrayHasKey('passport_photo', $options);
        $this->assertArrayHasKey('aadhaar', $options);
        $this->assertArrayHasKey('pan', $options);
        $this->assertArrayHasKey('other', $options);
        $this->assertArrayNotHasKey('cancelled_cheque', $options); // Disabled for secondary

        // Verify ManageSecondaryTenants mounts and loads data
        $testable = Livewire::test(ManageSecondaryTenants::class, ['record' => $agreement->id])
            ->assertSuccessful();

        $form = $testable->instance()->form;
        $secondaryComponent = $form->getComponent('secondary_tenants');
        $this->assertNotNull($secondaryComponent);

        // Save page and verify persisted
        $testable->call('save')->assertHasNoErrors();

        $agreement->refresh();
        $this->assertCount(1, $agreement->secondary_tenants);
        $this->assertEquals('Priya Sharma', $agreement->secondary_tenants[0]['name']);
        $this->assertEquals('1234-5678-9012', $agreement->secondary_tenants[0]['aadhaar_number']);
        $this->assertEquals('tenancy-secondary-kyc/priya_aadhaar.pdf', $agreement->secondary_tenants[0]['aadhaar_file']);
        $this->assertEquals('tenancy-secondary-kyc/marriage_certificate.pdf', $agreement->secondary_tenants[0]['other_kyc_files'][0]);
    }

    public function test_primary_tenant_profile_card_in_tab_3_and_saving()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Profile Test Property',
            'address_line_1' => 'Zoo Road, Guwahati',
            'status' => 'vacant',
        ]);

        $tenantParty = Party::create([
            'display_name' => 'Original Tenant Name',
            'phone' => '9876500001',
            'email' => 'original@example.com',
            'party_type' => 'individual',
        ]);

        $tenantParty->individual()->create([
            'name' => 'Original Tenant Name',
            'parent_name' => 'S/o Rajesh Kumar',
            'aadhaar_number' => '1111-2222-3333',
            'pan_number' => 'ABCDE1111A',
        ]);

        $tenantParty->addresses()->create([
            'address_line_1' => 'Old Address Line 1',
            'is_primary' => true,
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'TNC-PROF-01',
            'status' => 'draft',
            'start_date' => '2026-11-01',
            'end_date' => '2027-09-30',
            'rent_amount' => 15000,
            'security_deposit' => 30000,
        ]);

        $agreement->roles()->create([
            'party_id' => $tenantParty->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        // Test ManageSecondaryTenants (Tab 3: Tenant KYC & Occupants)
        $testable = Livewire::test(ManageSecondaryTenants::class, ['record' => $agreement->id])
            ->assertSuccessful();

        $form = $testable->instance()->form;

        // Verify components exist in Tab 3
        $this->assertNotNull($form->getComponent('new_tenant.display_name'));
        $this->assertNotNull($form->getComponent('new_tenant.phone'));
        $this->assertNotNull($form->getComponent('new_tenant.email'));
        $this->assertNotNull($form->getComponent('new_tenant.parent_name'));
        $this->assertNotNull($form->getComponent('new_tenant.address_line_1'));

        // Verify initial hydration
        $testable->assertSchemaStateSet([
            'new_tenant.display_name' => 'Original Tenant Name',
            'new_tenant.phone' => '9876500001',
            'new_tenant.email' => 'original@example.com',
            'new_tenant.parent_name' => 'S/o Rajesh Kumar',
            'new_tenant.address_line_1' => 'Old Address Line 1',
        ]);

        // Update fields and save
        $testable->fillForm([
            'new_tenant.display_name' => 'Updated Tenant Name',
            'new_tenant.phone' => '9876599999',
            'new_tenant.email' => 'updated@example.com',
            'new_tenant.parent_name' => 'S/o Late Suresh Kumar',
            'new_tenant.address_line_1' => 'New Permanent Flat 101, Guwahati',
        ]);

        $testable->call('save')->assertHasNoErrors();

        // Verify underlying database models were updated
        $tenantParty->refresh();
        $this->assertEquals('Updated Tenant Name', $tenantParty->display_name);
        $this->assertEquals('9876599999', $tenantParty->phone);
        $this->assertEquals('updated@example.com', $tenantParty->email);

        $individual = $tenantParty->individual;
        $this->assertEquals('Updated Tenant Name', $individual->name);
        $this->assertEquals('S/o Late Suresh Kumar', $individual->parent_name);

        $primaryAddress = $tenantParty->addresses()->where('is_primary', true)->first();
        $this->assertEquals('New Permanent Flat 101, Guwahati', $primaryAddress->address_line_1);
    }
}
