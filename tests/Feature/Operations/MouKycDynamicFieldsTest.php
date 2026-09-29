<?php

namespace Tests\Feature\Operations;

use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Shared\Enums\KycRequirementLevel;
use App\Domain\Shared\Models\KycDocumentType;
use App\Filament\Resources\Operations\MOUResource\Pages\EditMOU;
use App\Filament\Resources\Operations\MOUResource\Schemas\MOUForm;
use App\Models\User;
use Database\Seeders\KycDocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MouKycDynamicFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
    }

    public function test_get_document_type_options_returns_active_configured_documents(): void
    {
        $this->seed(KycDocumentTypeSeeder::class);

        $ownerOptions = MOUForm::getDocumentTypeOptions('owner');
        $this->assertArrayHasKey('aadhaar', $ownerOptions);
        $this->assertArrayHasKey('pan', $ownerOptions);
        $this->assertArrayHasKey('cancelled_cheque', $ownerOptions);
        $this->assertArrayHasKey('electricity_bill', $ownerOptions);

        $signatoryOptions = MOUForm::getDocumentTypeOptions('signatory');
        $this->assertArrayHasKey('aadhaar', $signatoryOptions);
        $this->assertArrayHasKey('pan', $signatoryOptions);
        $this->assertArrayHasKey('power_of_attorney', $signatoryOptions);
    }

    public function test_edit_mou_renders_dynamic_kyc_fields_for_owner(): void
    {
        $this->seed(KycDocumentTypeSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-KYC-001',
            'title' => 'Test Opportunity KYC',
            'owner_name' => 'Rajesh Sharma',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::NEW,
        ]);

        $mou = Mou::create([
            'number' => 'MOU-KYC-001',
            'opportunity_id' => $opportunity->id,
            'status' => MouStatus::DRAFT,
            'owner_name' => 'Rajesh Sharma',
            'owner_phone' => '9876543210',
            'owner_details' => [
                'name' => 'Rajesh Sharma',
                'phone' => '9876543210',
            ],
            'legal_terms' => [
                'rent_amount' => 45000,
                'fee_percentage' => 10,
                'address' => '123 MG Road, Bangalore',
                'electricity_consumer_id' => 'CA-123456',
            ],
        ]);

        Livewire::test(EditMOU::class, ['record' => $mou->getKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('kyc_documents.owner.aadhaar.number')
            ->assertFormFieldExists('kyc_documents.owner.pan.number')
            ->assertFormFieldExists('kyc_documents.owner.electricity_bill.number')
            ->assertFormFieldExists('owner_aadhaar')
            ->assertFormFieldExists('owner_pan')
            ->assertFormFieldExists('cancelled_cheque')
            ->assertFormFieldExists('electricity_bill');
    }

    public function test_signatory_kyc_fields_rendered_when_signatory_is_different(): void
    {
        $this->seed(KycDocumentTypeSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-KYC-002',
            'title' => 'Test Opportunity KYC 2',
            'owner_name' => 'Owner Person',
            'owner_phone' => '9876543211',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::NEW,
        ]);

        $mou = Mou::create([
            'number' => 'MOU-KYC-002',
            'opportunity_id' => $opportunity->id,
            'status' => MouStatus::DRAFT,
            'is_signatory_different' => true,
            'owner_name' => 'Owner Person',
            'owner_phone' => '9876543211',
            'owner_details' => [
                'name' => 'Owner Person',
                'phone' => '9876543211',
            ],
            'signatory_details' => [
                'name' => 'Signatory Person',
                'relation' => 'POA Holder',
                'phone' => '9876543299',
                'email' => 'signatory@example.com',
            ],
            'legal_terms' => [
                'rent_amount' => 50000,
                'fee_percentage' => 10,
                'address' => '456 Residency Road, Bangalore',
                'electricity_consumer_id' => 'CA-654321',
            ],
        ]);

        Livewire::test(EditMOU::class, ['record' => $mou->getKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('kyc_documents.signatory.aadhaar.number')
            ->assertFormFieldExists('kyc_documents.signatory.pan.number')
            ->assertFormFieldExists('signatory_aadhaar')
            ->assertFormFieldExists('signatory_pan')
            ->assertFormFieldExists('signatory_poa');
    }

    public function test_form_data_mutation_syncs_kyc_numbers_to_legacy_fields(): void
    {
        $editPage = new class extends EditMOU {
            public function invokeMutate(array $data): array
            {
                return $this->mutateFormDataBeforeSave($data);
            }
        };

        $inputData = [
            'is_signatory_different' => true,
            'owner_details' => [
                'name' => 'John Doe',
                'phone' => '9988776655',
            ],
            'signatory_details' => [
                'name' => 'Jane Doe',
            ],
            'kyc_documents' => [
                'owner' => [
                    'aadhaar' => ['number' => '1234 5678 9012'],
                    'pan' => ['number' => 'ABCDE1234F'],
                ],
                'signatory' => [
                    'aadhaar' => ['number' => '9876 5432 1098'],
                    'pan' => ['number' => 'XYZPK9999Z'],
                ],
            ],
        ];

        $result = $editPage->invokeMutate($inputData);

        $this->assertEquals('1234 5678 9012', $result['owner_details']['aadhar_number']);
        $this->assertEquals('ABCDE1234F', $result['owner_details']['pan_number']);
        $this->assertEquals('9876 5432 1098', $result['signatory_details']['aadhar_number']);
        $this->assertEquals('XYZPK9999Z', $result['signatory_details']['pan_number']);
    }

    public function test_mou_model_helpers_and_json_persistence(): void
    {
        $opportunity = Opportunity::create([
            'number' => 'OPP-KYC-003',
            'title' => 'Test Opportunity KYC 3',
            'owner_name' => 'Persistence Test',
            'owner_phone' => '9876543212',
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::NEW,
        ]);

        $mou = Mou::create([
            'number' => 'MOU-KYC-003',
            'opportunity_id' => $opportunity->id,
            'status' => MouStatus::DRAFT,
            'kyc_documents' => [
                'owner' => [
                    'aadhaar' => ['number' => '1111 2222 3333'],
                    'pan' => ['number' => 'AAAAA1111A'],
                ],
            ],
        ]);

        $this->assertEquals('owner_aadhaar', $mou->getDocumentCollectionName('aadhaar', 'owner'));
        $this->assertEquals('owner_pan', $mou->getDocumentCollectionName('pan', 'owner'));
        $this->assertEquals('signatory_poa', $mou->getDocumentCollectionName('power_of_attorney', 'signatory'));
        $this->assertEquals('owner_passport', $mou->getDocumentCollectionName('passport', 'owner'));

        $this->assertEquals('1111 2222 3333', $mou->kyc_documents['owner']['aadhaar']['number']);
        $this->assertEquals('AAAAA1111A', $mou->kyc_documents['owner']['pan']['number']);
    }

    public function test_electricity_consumer_id_not_in_legal_terms_and_start_date_in_legal_terms(): void
    {
        $this->seed(KycDocumentTypeSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-KYC-004',
            'title' => 'Test Opportunity KYC 4',
            'owner_name' => 'Field Layout Test',
            'owner_phone' => '9876543213',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::NEW,
        ]);

        $mou = Mou::create([
            'number' => 'MOU-KYC-004',
            'opportunity_id' => $opportunity->id,
            'status' => MouStatus::DRAFT,
            'start_date' => now()->format('Y-m-d'),
            'owner_name' => 'Field Layout Test',
            'owner_phone' => '9876543213',
        ]);

        $test = Livewire::test(EditMOU::class, ['record' => $mou->getKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('start_date')
            ->assertFormFieldExists('legal_terms.financial_model_id')
            ->assertFormFieldDoesNotExist('legal_terms.electricity_consumer_id');
    }

    public function test_optional_documents_are_not_rendered_by_default_until_added(): void
    {
        $this->seed(KycDocumentTypeSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-KYC-005',
            'title' => 'Test Opportunity KYC 5',
            'owner_name' => 'Optional Doc Test',
            'owner_phone' => '9876543214',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::NEW,
        ]);

        // Fresh MOU without optional documents
        $mou = Mou::create([
            'number' => 'MOU-KYC-005',
            'opportunity_id' => $opportunity->id,
            'status' => MouStatus::DRAFT,
            'start_date' => now()->format('Y-m-d'),
            'owner_name' => 'Optional Doc Test',
            'owner_phone' => '9876543214',
        ]);

        // Passport is optional in seeder (mou_owner_requirement = optional)
        $component = Livewire::test(EditMOU::class, ['record' => $mou->getKey()])
            ->assertSuccessful()
            // Mandatory documents are present
            ->assertFormFieldExists('owner_aadhaar')
            ->assertFormFieldExists('owner_pan')
            ->assertFormFieldExists('cancelled_cheque')
            ->assertFormFieldExists('electricity_bill')
            // Optional document is NOT rendered on fresh form
            ->assertFormFieldDoesNotExist('owner_passport');

        // Now simulate adding the optional document
        $file = \Illuminate\Http\UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');
        $mou->addMedia($file)->toMediaCollection('owner_passport');
        $mou->refresh();

        // Once added, it is visible on the main form!
        Livewire::test(EditMOU::class, ['record' => $mou->getKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('owner_passport');
    }

    public function test_electricity_consumer_id_synced_from_kyc_electricity_bill_number(): void
    {
        $editPage = new class extends EditMOU {
            public function invokeMutate(array $data): array
            {
                return $this->mutateFormDataBeforeSave($data);
            }
        };

        $inputData = [
            'is_signatory_different' => true,
            'legal_terms' => [],
            'kyc_documents' => [
                'owner' => [
                    'electricity_bill' => ['number' => 'CA-ELEC-987654'],
                ],
            ],
        ];

        $result = $editPage->invokeMutate($inputData);
        $this->assertEquals('CA-ELEC-987654', $result['legal_terms']['electricity_consumer_id']);
    }

    public function test_custom_icon_configured_on_kyc_document_type_is_used(): void
    {
        $this->seed(KycDocumentTypeSeeder::class);

        $doc = KycDocumentType::where('code', 'aadhaar')->first();
        $doc->update(['icon' => 'heroicon-m-finger-print']);

        $fields = MOUForm::getDynamicOwnerKycFields();
        $aadhaarCard = collect($fields)->first(function ($field) {
            return method_exists($field, 'getHeading') && str_contains((string) $field->getHeading(), 'Aadhaar Card');
        });

        $this->assertNotNull($aadhaarCard);
        $this->assertEquals('heroicon-m-finger-print', $aadhaarCard->getIcon());
    }
}
