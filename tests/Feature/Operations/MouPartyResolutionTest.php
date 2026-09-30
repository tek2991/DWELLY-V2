<?php

namespace Tests\Feature\Operations;

use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyIndividual;
use App\Domain\Party\Models\PartyOrganization;
use App\Domain\Party\Services\IdentityResolutionService;
use App\Filament\Resources\Operations\MOUResource\Pages\ViewMOU;
use App\Models\User;
use Database\Seeders\KycDocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tek2991\Accounting\Models\State;
use Tests\TestCase;

class MouPartyResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
    }

    public function test_mou_helper_methods_extract_kyc_document_numbers(): void
    {
        $mou = new Mou([
            'kyc_documents' => [
                'owner' => [
                    'pan' => [
                        'number' => 'abcde1234f',
                        'file' => 'kyc/owner/pan.pdf',
                    ],
                    'aadhaar' => [
                        'number' => '1234 5678 9012',
                        'file' => 'kyc/owner/aadhaar.pdf',
                    ],
                    'voter_id' => [
                        'number' => 'xyz9876543',
                        'file' => 'kyc/owner/voter.pdf',
                    ],
                ],
            ],
        ]);

        $this->assertEquals('ABCDE1234F', $mou->getResolvedPanNumber());
        $this->assertEquals('1234 5678 9012', $mou->getResolvedAadhaarNumber());
        $this->assertEquals('XYZ9876543', $mou->getResolvedVoterId());
    }

    public function test_mou_helper_methods_return_null_when_documents_missing(): void
    {
        $mou = new Mou(['kyc_documents' => []]);

        $this->assertNull($mou->getResolvedPanNumber());
        $this->assertNull($mou->getResolvedAadhaarNumber());
        $this->assertNull($mou->getResolvedVoterId());
    }

    public function test_identity_resolution_service_finds_party_by_individual_pan(): void
    {
        $party = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Ramesh Kumar',
            'phone' => '9876543210',
            'email' => 'ramesh@example.com',
        ]);

        PartyIndividual::create([
            'party_id' => $party->id,
            'name' => 'Ramesh Kumar',
            'pan_number' => 'ABCDE1234F',
        ]);

        $service = app(IdentityResolutionService::class);

        $matchedParty = $service->findPartyByPan('abcde1234f');
        $this->assertNotNull($matchedParty);
        $this->assertEquals($party->id, $matchedParty->id);

        $this->assertNull($service->findPartyByPan('NOTEXIST12'));
        $this->assertNull($service->findPartyByPan(null));
    }

    public function test_identity_resolution_service_finds_party_by_organization_pan(): void
    {
        $party = Party::create([
            'party_type' => 'organization',
            'display_name' => 'Acme Estates Ltd',
            'phone' => '9876543211',
            'email' => 'acme@example.com',
        ]);

        PartyOrganization::create([
            'party_id' => $party->id,
            'legal_name' => 'Acme Estates Private Limited',
            'pan' => 'AAACA1234C',
        ]);

        $service = app(IdentityResolutionService::class);

        $matchedParty = $service->findPartyByPan('aaaca1234c');
        $this->assertNotNull($matchedParty);
        $this->assertEquals($party->id, $matchedParty->id);
    }

    public function test_resolve_party_modal_prefills_and_matches_existing_party_when_pan_matches(): void
    {
        $this->seed(KycDocumentTypeSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $existingParty = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Matched Owner',
            'phone' => '9876500001',
            'email' => 'matched@example.com',
        ]);

        PartyIndividual::create([
            'party_id' => $existingParty->id,
            'name' => 'Matched Owner',
            'pan_number' => 'ABCDE1234F',
        ]);

        $opportunity = Opportunity::create([
            'number' => 'OPP-RESOLVE-001',
            'title' => 'Test Opportunity PAN Match',
            'owner_name' => 'New Owner Lead',
            'owner_phone' => '9876500002',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::READY_FOR_MOU,
        ]);

        $mou = Mou::create([
            'number' => 'MOU-RES-001',
            'opportunity_id' => $opportunity->id,
            'status' => MouStatus::DRAFT,
            'owner_name' => 'New Owner Lead',
            'owner_phone' => '9876500002',
            'kyc_documents' => [
                'owner' => [
                    'pan' => [
                        'number' => 'ABCDE1234F',
                    ],
                    'aadhaar' => [
                        'number' => '999988887777',
                    ],
                ],
            ],
        ]);

        Livewire::test(ViewMOU::class, ['record' => $mou->getKey()])
            ->mountFormComponentAction('mou_summary', 'resolveParty')
            ->assertFormComponentActionMounted('mou_summary', 'resolveParty')
            ->assertFormComponentActionDataSet([
                'action_type' => 'select_existing',
                'existing_party_id' => $existingParty->id,
            ]);
    }

    public function test_resolve_party_modal_prefills_and_locks_pan_when_creating_new_party(): void
    {
        $this->seed(KycDocumentTypeSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-RESOLVE-002',
            'title' => 'Test Opportunity New Party',
            'owner_name' => 'Brand New Owner',
            'owner_phone' => '9876500003',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::READY_FOR_MOU,
        ]);

        $mou = Mou::create([
            'number' => 'MOU-RES-002',
            'opportunity_id' => $opportunity->id,
            'status' => MouStatus::DRAFT,
            'owner_name' => 'Brand New Owner',
            'owner_phone' => '9876500003',
            'kyc_documents' => [
                'owner' => [
                    'pan' => [
                        'number' => 'ZXCVB9876M',
                    ],
                    'aadhaar' => [
                        'number' => '111122223333',
                    ],
                ],
            ],
        ]);

        Livewire::test(ViewMOU::class, ['record' => $mou->getKey()])
            ->mountFormComponentAction('mou_summary', 'resolveParty')
            ->assertFormComponentActionMounted('mou_summary', 'resolveParty')
            ->assertFormComponentActionDataSet([
                'action_type' => 'create_new',
                'pan_number' => 'ZXCVB9876M',
                'aadhar_number' => '111122223333',
            ]);
    }

    public function test_resolving_new_party_persists_resolved_pan_and_links_mou(): void
    {
        $this->seed(KycDocumentTypeSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $state = State::create([
            'country_id' => 1,
            'name' => 'Karnataka',
            'code' => 'KA',
        ]);

        $opportunity = Opportunity::create([
            'number' => 'OPP-RESOLVE-003',
            'title' => 'Test Opportunity Execute Resolve',
            'owner_name' => 'Deepak Verma',
            'owner_phone' => '9876500004',
            'owner_email' => 'deepak@example.com',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::READY_FOR_MOU,
        ]);

        $mou = Mou::create([
            'number' => 'MOU-RES-003',
            'opportunity_id' => $opportunity->id,
            'status' => MouStatus::DRAFT,
            'owner_name' => 'Deepak Verma',
            'owner_phone' => '9876500004',
            'kyc_documents' => [
                'owner' => [
                    'pan' => [
                        'number' => 'DEPVR1234K',
                    ],
                    'aadhaar' => [
                        'number' => '444455556666',
                    ],
                ],
            ],
        ]);

        Livewire::test(ViewMOU::class, ['record' => $mou->getKey()])
            ->callFormComponentAction('mou_summary', 'resolveParty', [
                'action_type' => 'create_new',
                'party_type' => 'individual',
                'name' => 'Deepak Verma',
                'parent_name' => 'Shri Verma',
                'pan_number' => 'DEPVR1234K',
                'aadhar_number' => '444455556666',
                'phone' => '9876500004',
                'email' => 'deepak@example.com',
                'state_id' => $state->id,
                'address' => '123 Test Street, Bangalore',
            ])
            ->assertHasNoFormComponentActionErrors();

        $mou->refresh();
        $this->assertNotNull($mou->party_id);

        $party = Party::find($mou->party_id);
        $this->assertNotNull($party);
        $this->assertEquals('Deepak Verma', $party->display_name);

        $this->assertDatabaseHas('party_individuals', [
            'party_id' => $party->id,
            'pan_number' => 'DEPVR1234K',
            'aadhaar_number' => '444455556666',
        ]);
    }
}
