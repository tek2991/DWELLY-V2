<?php

namespace Tests\Feature;

use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Models\Opportunity;
use App\Filament\Resources\Operations\MOUResource;
use App\Filament\Resources\Properties\PropertyResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MOUResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_converting_mou_to_property_redirects_to_property_resource_page()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-100',
            'title' => 'Test Opportunity for MOU Conversion',
            'owner_name' => 'John Seller',
            'owner_phone' => '9998887776',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::NEW,
        ]);

        $mou = Mou::create([
            'number' => 'MOU-100',
            'opportunity_id' => $opportunity->id,
            'status' => MouStatus::VERIFIED,
            'owner_name' => 'John Seller',
            'owner_phone' => '9998887776',
        ]);

        Livewire::test(MOUResource\Pages\ListMOUs::class)
            ->callTableAction('convertToProperty', $mou)
            ->assertRedirect();
            
        $this->assertDatabaseHas('mous', [
            'id' => $mou->id,
            'status' => MouStatus::CONVERTED,
        ]);

        $this->assertDatabaseHas('properties', [
            'building_name' => 'Test Opportunity for MOU Conversion',
            'status' => 'draft',
        ]);
    }

    public function test_view_mou_page_converting_mou_to_property_redirects_to_property_resource_page()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-101',
            'title' => 'Test Opportunity 101',
            'owner_name' => 'Jane Seller',
            'owner_phone' => '9998887775',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::NEW,
        ]);

        $mou = Mou::create([
            'number' => 'MOU-101',
            'opportunity_id' => $opportunity->id,
            'status' => MouStatus::VERIFIED,
            'owner_name' => 'Jane Seller',
            'owner_phone' => '9998887775',
        ]);

        Livewire::test(MOUResource\Pages\ViewMOU::class, [
            'record' => $mou->getKey(),
        ])
            ->callAction('convertToProperty')
            ->assertRedirect();
            
        $this->assertDatabaseHas('mous', [
            'id' => $mou->id,
            'status' => MouStatus::CONVERTED,
        ]);
    }

    public function test_archiving_unverified_mou_marks_opportunity_closed_lost()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-102',
            'title' => 'Test Opportunity 102',
            'owner_name' => 'Mark Owner',
            'owner_phone' => '9998887774',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::MOU_CREATED,
        ]);

        $mou = Mou::create([
            'number' => 'MOU-102',
            'opportunity_id' => $opportunity->id,
            'status' => MouStatus::DRAFT,
            'owner_name' => 'Mark Owner',
            'owner_phone' => '9998887774',
        ]);

        Livewire::test(MOUResource\Pages\ListMOUs::class)
            ->callTableAction('archive', $mou);

        $this->assertSoftDeleted('mous', [
            'id' => $mou->id,
            'status' => MouStatus::CANCELLED,
        ]);

        $this->assertDatabaseHas('opportunities', [
            'id' => $opportunity->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::CLOSED_LOST,
        ]);
    }

    public function test_archive_action_is_hidden_for_verified_mou()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-103',
            'title' => 'Test Opportunity 103',
            'owner_name' => 'Sarah Owner',
            'owner_phone' => '9998887773',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::MOU_SIGNED,
        ]);

        $mou = Mou::create([
            'number' => 'MOU-103',
            'opportunity_id' => $opportunity->id,
            'status' => MouStatus::VERIFIED,
            'verified_at' => now(),
            'owner_name' => 'Sarah Owner',
            'owner_phone' => '9998887773',
        ]);

        Livewire::test(MOUResource\Pages\ListMOUs::class)
            ->assertTableActionHidden('archive', $mou);

        Livewire::test(MOUResource\Pages\ViewMOU::class, [
            'record' => $mou->getKey(),
        ])
            ->assertActionHidden('archive');
    }

    public function test_service_throws_exception_when_archiving_verified_mou()
    {
        $opportunity = Opportunity::create([
            'number' => 'OPP-104',
            'title' => 'Test Opportunity 104',
            'owner_name' => 'David Owner',
            'owner_phone' => '9998887772',
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::MOU_SIGNED,
        ]);

        $mou = Mou::create([
            'number' => 'MOU-104',
            'opportunity_id' => $opportunity->id,
            'status' => MouStatus::VERIFIED,
            'verified_at' => now(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot archive MOU once the agreement has been verified.');

        app(\App\Domain\Mou\Services\MouWorkflowService::class)->archive($mou);
    }

    public function test_can_update_party_details_after_resolution_in_view_mou_page()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $state = \Tek2991\Accounting\Models\State::create([
            'country_id' => 1,
            'name' => 'Assam',
            'code' => 'AS',
        ]);

        $opportunity = Opportunity::create([
            'number' => 'OPP-105',
            'title' => 'Test Opportunity 105',
            'owner_name' => 'Initial Owner',
            'owner_phone' => '9998887771',
            'owner_email' => 'initial@example.com',
            'address' => 'Initial Address',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::READY_FOR_MOU,
        ]);

        $mou = app(\App\Domain\Mou\Services\MouService::class)->createDraftFromOpportunity($opportunity);

        // Resolve party initially
        app(\App\Domain\Mou\Services\MouService::class)->resolveParty($mou, [
            'action_type' => 'create_new',
            'party_type' => 'individual',
            'name' => 'Initial Owner',
            'parent_name' => 'Father Name',
            'phone' => '9998887771',
            'email' => 'initial@example.com',
            'pan_number' => 'ABCDE1111A',
            'aadhar_number' => '111122223333',
            'address' => 'Initial Address',
            'state_id' => $state->id,
        ]);

        $mou->refresh();
        $mou->update(['status' => MouStatus::PDF_GENERATED]);

        // Test ViewMOU page has updateParty action visible and resolveParty hidden
        Livewire::test(MOUResource\Pages\ViewMOU::class, [
            'record' => $mou->getKey(),
        ])
            ->assertActionVisible('updateParty')
            ->assertFormComponentActionDoesNotExist('mou_summary', 'resolveParty')
            ->callAction('updateParty', [
                'action_type' => 'update_current',
                'party_type' => 'individual',
                'name' => 'Updated Owner Name',
                'parent_name' => 'Updated Father Name',
                'phone' => '9998887779',
                'email' => 'updated@example.com',
                'pan_number' => 'XYZPA9999Z',
                'aadhar_number' => '999988887777',
                'address' => 'Updated Street 456',
                'state_id' => $state->id,
            ])
            ->assertHasNoActionErrors();

        $mou->refresh();
        $party = $mou->party;

        $this->assertEquals('Updated Owner Name', $party->display_name);
        $this->assertEquals('9998887779', $party->phone);
        $this->assertEquals('updated@example.com', $party->email);

        $this->assertEquals('Updated Owner Name', $party->individual->name);
        $this->assertEquals('Updated Father Name', $party->individual->parent_name);
        $this->assertEquals('XYZPA9999Z', $party->individual->pan_number);
        $this->assertEquals('999988887777', $party->individual->aadhaar_number);

        $this->assertEquals('Updated Owner Name', $mou->owner_details['name']);
        $this->assertEquals('Updated Father Name', $mou->owner_details['parent_name']);
        $this->assertEquals('Updated Street 456', $mou->owner_details['address']);
        $this->assertEquals('XYZPA9999Z', $mou->owner_details['pan_number']);

        // Signatory details should also be synced since signatory is not different
        $this->assertEquals('Updated Owner Name', $mou->signatory_details['name']);
    }

    public function test_can_switch_to_different_party_in_update_party_action()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-106',
            'title' => 'Test Opportunity 106',
            'owner_name' => 'First Owner',
            'owner_phone' => '9998887770',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::READY_FOR_MOU,
        ]);

        $mou = app(\App\Domain\Mou\Services\MouService::class)->createDraftFromOpportunity($opportunity);

        app(\App\Domain\Mou\Services\MouService::class)->resolveParty($mou, [
            'action_type' => 'create_new',
            'party_type' => 'individual',
            'name' => 'First Owner',
            'parent_name' => 'First Father',
            'phone' => '9998887770',
            'email' => 'first@example.com',
            'address' => 'First Address',
        ]);

        $secondParty = \App\Domain\Party\Models\Party::create([
            'party_type' => 'individual',
            'display_name' => 'Second Preexisting Owner',
            'phone' => '8887776665',
            'email' => 'second@example.com',
        ]);
        \App\Domain\Party\Models\PartyIndividual::create([
            'party_id' => $secondParty->id,
            'name' => 'Second Preexisting Owner',
            'parent_name' => 'Second Father',
            'pan_number' => 'SECON1234F',
        ]);

        Livewire::test(MOUResource\Pages\ViewMOU::class, [
            'record' => $mou->getKey(),
        ])
            ->callAction('updateParty', [
                'action_type' => 'select_existing',
                'existing_party_id' => $secondParty->id,
            ])
            ->assertHasNoActionErrors();

        $mou->refresh();
        $this->assertEquals($secondParty->id, $mou->party_id);
        $this->assertEquals('Second Preexisting Owner', $mou->owner_details['name']);
        $this->assertEquals('Second Father', $mou->owner_details['parent_name']);
        $this->assertEquals('SECON1234F', $mou->owner_details['pan_number']);
        $this->assertEquals($secondParty->id, $mou->opportunity->owner_party_id);
    }

    public function test_update_party_action_is_hidden_for_verified_mou()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-107',
            'title' => 'Test Opportunity 107',
            'owner_name' => 'Verified Owner',
            'owner_phone' => '9998887770',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::MOU_SIGNED,
        ]);

        $party = \App\Domain\Party\Models\Party::create([
            'party_type' => 'individual',
            'display_name' => 'Verified Owner',
        ]);

        $mou = Mou::create([
            'number' => 'MOU-107',
            'opportunity_id' => $opportunity->id,
            'party_id' => $party->id,
            'status' => MouStatus::VERIFIED,
            'verified_at' => now(),
        ]);

        Livewire::test(MOUResource\Pages\ViewMOU::class, [
            'record' => $mou->getKey(),
        ])
            ->assertActionHidden('updateParty');

        Livewire::test(MOUResource\Pages\ListMOUs::class)
            ->assertTableActionHidden('updateParty', $mou);
    }

    public function test_can_update_organization_party_details_after_resolution()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $state = \Tek2991\Accounting\Models\State::create([
            'country_id' => 1,
            'name' => 'Assam',
            'code' => 'AS',
        ]);

        $opportunity = Opportunity::create([
            'number' => 'OPP-108',
            'title' => 'Test Opportunity 108',
            'owner_name' => 'Acme Corp',
            'owner_phone' => '9998887779',
            'owner_email' => 'contact@acme.com',
            'address' => 'Acme Office',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::READY_FOR_MOU,
        ]);

        $mou = app(\App\Domain\Mou\Services\MouService::class)->createDraftFromOpportunity($opportunity);

        app(\App\Domain\Mou\Services\MouService::class)->resolveParty($mou, [
            'action_type' => 'create_new',
            'party_type' => 'organization',
            'legal_name' => 'Acme Corp Pvt Ltd',
            'contact_person_name' => 'John Doe',
            'contact_person_phone' => '9998887779',
            'pan_number' => 'AAACA1111A',
            'gst_number' => '18AAACA1111A1Z5',
            'phone' => '9998887779',
            'email' => 'contact@acme.com',
            'address' => '100 Industrial Area',
            'state_id' => $state->id,
        ]);

        $mou->refresh();

        Livewire::test(MOUResource\Pages\ViewMOU::class, [
            'record' => $mou->getKey(),
        ])
            ->callAction('updateParty', [
                'action_type' => 'update_current',
                'party_type' => 'organization',
                'legal_name' => 'Acme Global Enterprises Pvt Ltd',
                'contact_person_name' => 'Jane Smith',
                'contact_person_phone' => '8889990000',
                'pan_number' => 'AAACA2222B',
                'gst_number' => '18AAACA2222B1Z5',
                'phone' => '8889990000',
                'email' => 'info@acmeglobal.com',
                'address' => '200 Tech Park',
                'state_id' => $state->id,
            ])
            ->assertHasNoActionErrors();

        $mou->refresh();
        $party = $mou->party;

        $this->assertEquals('Acme Global Enterprises Pvt Ltd', $party->display_name);
        $this->assertEquals('8889990000', $party->phone);
        $this->assertEquals('info@acmeglobal.com', $party->email);

        $this->assertEquals('Acme Global Enterprises Pvt Ltd', $party->organization->legal_name);
        $this->assertEquals('Jane Smith', $party->organization->contact_person_name);
        $this->assertEquals('8889990000', $party->organization->contact_person_phone);
        $this->assertEquals('AAACA2222B', $party->organization->pan);
        $this->assertEquals('18AAACA2222B1Z5', $party->organization->gstin);

        $this->assertEquals('Acme Global Enterprises Pvt Ltd', $mou->owner_details['name']);
        $this->assertEquals('Jane Smith', $mou->owner_details['contact_person_name']);
        $this->assertEquals('8889990000', $mou->owner_details['contact_person_phone']);
        $this->assertEquals('200 Tech Park', $mou->owner_details['address']);
        $this->assertEquals('AAACA2222B', $mou->owner_details['pan_number']);
        $this->assertEquals('18AAACA2222B1Z5', $mou->owner_details['gstin']);
    }

    public function test_mandatory_fields_are_validated_when_resolving_individual_party()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-109',
            'title' => 'Test Opportunity 109',
            'owner_name' => 'Test Owner',
            'owner_phone' => '9998887770',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::READY_FOR_MOU,
        ]);

        $mou = app(\App\Domain\Mou\Services\MouService::class)->createDraftFromOpportunity($opportunity);

        Livewire::test(MOUResource\Pages\ViewMOU::class, [
            'record' => $mou->getKey(),
        ])
            ->callFormComponentAction('mou_summary', 'resolveParty', [
                'action_type' => 'create_new',
                'party_type' => 'individual',
                'name' => '',
                'parent_name' => '',
                'aadhar_number' => '',
                'pan_number' => '',
                'phone' => '',
                'email' => '',
                'address' => '',
                'state_id' => null,
            ])
            ->assertHasFormComponentActionErrors([
                'name' => 'required',
                'parent_name' => 'required',
                'aadhar_number' => 'required',
                'pan_number' => 'required',
                'phone' => 'required',
                'email' => 'required',
                'address' => 'required',
                'state_id' => 'required',
            ]);
    }

    public function test_mandatory_fields_are_validated_when_resolving_organization_party()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-110',
            'title' => 'Test Opportunity 110',
            'owner_name' => 'Test Corp',
            'owner_phone' => '9998887770',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::READY_FOR_MOU,
        ]);

        $mou = app(\App\Domain\Mou\Services\MouService::class)->createDraftFromOpportunity($opportunity);

        Livewire::test(MOUResource\Pages\ViewMOU::class, [
            'record' => $mou->getKey(),
        ])
            ->callFormComponentAction('mou_summary', 'resolveParty', [
                'action_type' => 'create_new',
                'party_type' => 'organization',
                'legal_name' => '',
                'contact_person_name' => '',
                'contact_person_phone' => '',
                'pan_number' => '',
                'phone' => '',
                'email' => '',
                'address' => '',
                'state_id' => null,
            ])
            ->assertHasFormComponentActionErrors([
                'legal_name' => 'required',
                'contact_person_name' => 'required',
                'contact_person_phone' => 'required',
                'pan_number' => 'required',
                'phone' => 'required',
                'email' => 'required',
                'address' => 'required',
                'state_id' => 'required',
            ]);
    }
}

