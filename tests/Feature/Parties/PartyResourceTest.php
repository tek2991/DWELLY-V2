<?php

namespace Tests\Feature\Parties;

use App\Domain\Party\Models\Party;
use App\Filament\Resources\Parties\Pages\CreateParty;
use App\Filament\Resources\Parties\Pages\EditParty;
use App\Filament\Resources\Parties\Pages\ListParties;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tek2991\Accounting\Models\State;
use Tests\TestCase;

class PartyResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected State $state;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole('Business Owner');
        $this->actingAs($this->user);

        $this->state = State::firstOrCreate(['gst_state_code' => '29'], [
            'country_id' => '1',
            'name' => 'Karnataka',
            'code' => 'KA',
        ]);
    }

    public function test_party_form_does_not_contain_aadhaar_or_voter_id_fields(): void
    {
        Livewire::test(CreateParty::class)
            ->fillForm([
                'party_type' => 'individual',
                'is_tax_registered' => true,
            ])
            ->assertFormFieldDoesNotExist('individual_data.aadhaar_number')
            ->assertFormFieldDoesNotExist('individual_data.voter_id')
            ->assertFormFieldExists('individual_data.pan_number')
            ->assertFormFieldExists('individual_data.gstin');
    }

    public function test_can_create_individual_party_with_pan(): void
    {
        Livewire::test(CreateParty::class)
            ->fillForm([
                'party_type' => 'individual',
                'display_name' => 'Rahul Sharma',
                'phone' => '9876543210',
                'email' => 'rahul@example.com',
                'roles' => ['owner'],
                'individual_data' => [
                    'name' => 'Rahul Sharma',
                    'pan_number' => 'ABCDE1234F',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $party = Party::where('display_name', 'Rahul Sharma')->first();
        $this->assertNotNull($party);
        $this->assertEquals('individual', $party->party_type);
        $this->assertNotNull($party->individual);
        $this->assertEquals('Rahul Sharma', $party->individual->name);
        $this->assertEquals('ABCDE1234F', $party->individual->pan_number);
        // Sensitive document fields remain null/empty
        $this->assertNull($party->individual->aadhaar_number);
        $this->assertNull($party->individual->voter_id);
    }

    public function test_pan_number_validation_rejects_invalid_format(): void
    {
        Livewire::test(CreateParty::class)
            ->fillForm([
                'party_type' => 'individual',
                'display_name' => 'Invalid Pan User',
                'phone' => '9876543210',
                'roles' => ['owner'],
                'individual_data' => [
                    'name' => 'Invalid Pan User',
                    'pan_number' => 'INVALID123',
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['individual_data.pan_number']);
    }

    public function test_can_create_organization_party_with_pan_and_gstin(): void
    {
        Livewire::test(CreateParty::class)
            ->fillForm([
                'party_type' => 'organization',
                'display_name' => 'Acme Technologies Pvt Ltd',
                'phone' => '9876543211',
                'email' => 'billing@acme.com',
                'state_id' => $this->state->id,
                'is_tax_registered' => true,
                'roles' => ['owner'],
                'organization_data' => [
                    'legal_name' => 'Acme Technologies Private Limited',
                    'pan' => 'AAACA1234B',
                    'gstin' => '29AAACA1234B1Z5',
                    'contact_person_name' => 'John Doe',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $party = Party::where('display_name', 'Acme Technologies Pvt Ltd')->first();
        $this->assertNotNull($party);
        $this->assertEquals('organization', $party->party_type);
        $this->assertNotNull($party->organization);
        $this->assertEquals('Acme Technologies Private Limited', $party->organization->legal_name);
        $this->assertEquals('AAACA1234B', $party->organization->pan);
        $this->assertEquals('29AAACA1234B1Z5', $party->organization->gstin);
    }

    public function test_parties_table_displays_pan_and_gstin_columns_and_allows_search(): void
    {
        $partyInd = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Vikram Malhotra',
            'phone' => '9876500001',
            'state_id' => $this->state->id,
            'is_tax_registered' => false,
        ]);
        $partyInd->individual()->create([
            'name' => 'Vikram Malhotra',
            'pan_number' => 'ABCDE9999Z',
        ]);

        $partyOrg = Party::create([
            'party_type' => 'organization',
            'display_name' => 'Zenith Estates LLP',
            'phone' => '9876500002',
            'state_id' => $this->state->id,
            'is_tax_registered' => true,
        ]);
        $partyOrg->organization()->create([
            'legal_name' => 'Zenith Estates LLP',
            'pan' => 'AAACZ8888Y',
            'gstin' => '29AAACZ8888Y1Z9',
        ]);

        Livewire::test(ListParties::class)
            ->assertCanSeeTableRecords([$partyInd, $partyOrg])
            ->searchTable('ABCDE9999Z')
            ->assertCanSeeTableRecords([$partyInd])
            ->assertCanNotSeeTableRecords([$partyOrg]);
    }

    public function test_can_update_individual_party_pan_in_edit_page(): void
    {
        $party = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Suresh Raina',
            'phone' => '9876500003',
            'state_id' => $this->state->id,
            'is_tax_registered' => false,
        ]);
        $party->individual()->create([
            'name' => 'Suresh Raina',
            'pan_number' => 'ABCDE1111A',
        ]);
        $party->ownerProfile()->create();

        Livewire::test(EditParty::class, ['record' => $party->id])
            ->assertFormSet(['individual_data.pan_number' => 'ABCDE1111A'])
            ->fillForm([
                'individual_data' => [
                    'name' => 'Suresh Raina',
                    'pan_number' => 'ABCDE2222B',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $party->refresh();
        $this->assertEquals('ABCDE2222B', $party->individual->pan_number);
    }
}
