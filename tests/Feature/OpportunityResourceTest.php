<?php

namespace Tests\Feature;

use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Models\Opportunity;
use App\Filament\Resources\Operations\OpportunityResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpportunityResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_opportunity_with_mandatory_fields()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OpportunityResource\Pages\CreateOpportunity::class)
            ->fillForm([
                'title' => 'Test Opportunity',
                'owner_name' => 'Jane Doe',
                'owner_phone' => '9876543210',
                'assigned_user_id' => $user->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('opportunities', [
            'title' => 'Test Opportunity',
            'owner_name' => 'Jane Doe',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
        ]);
    }

    public function test_creating_opportunity_does_not_collide_with_existing_numbers()
    {
        $year = now()->year;
        $user = User::factory()->create();
        $this->actingAs($user);

        // Pre-create records with 5-digit and 6-digit legacy padding (like OPP-2026-00051 and OPP-2026-000052)
        Opportunity::create([
            'number' => "OPP-{$year}-00051",
            'title' => 'Opp 51',
            'owner_name' => 'Owner 51',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::NEW,
        ]);

        Opportunity::create([
            'number' => "OPP-{$year}-000052",
            'title' => 'Opp 52',
            'owner_name' => 'Owner 52',
            'owner_phone' => '9876543211',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::NEW,
        ]);

        Livewire::test(OpportunityResource\Pages\CreateOpportunity::class)
            ->fillForm([
                'title' => 'Opp 53',
                'owner_name' => 'Jane Doe 53',
                'owner_phone' => '9876543212',
                'assigned_user_id' => $user->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('opportunities', [
            'number' => "OPP-{$year}-00053",
            'title' => 'Opp 53',
        ]);
    }

    public function test_cannot_create_opportunity_without_owner_phone()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OpportunityResource\Pages\CreateOpportunity::class)
            ->fillForm([
                'title' => 'Test Opportunity',
                'owner_name' => 'Jane Doe',
                'owner_phone' => null,
                'assigned_user_id' => $user->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['owner_phone' => 'required']);
    }

    public function test_cannot_create_opportunity_without_assigned_user()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OpportunityResource\Pages\CreateOpportunity::class)
            ->fillForm([
                'title' => 'Test Opportunity',
                'owner_name' => 'Jane Doe',
                'owner_phone' => '9876543210',
                'assigned_user_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['assigned_user_id' => 'required']);
    }

    public function test_opportunity_with_only_mandatory_fields_can_be_marked_ready_for_mou()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-001',
            'title' => 'Test Opportunity',
            'owner_name' => 'Jane Doe',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::NEW,
        ]);

        Livewire::test(OpportunityResource\Pages\ViewOpportunity::class, [
            'record' => $opportunity->getKey(),
        ])
            ->callAction('markReadyForMou');

        $this->assertEquals(OpportunityStatus::READY_FOR_MOU, $opportunity->fresh()->status);
    }

    public function test_manage_mou_action_redirects_when_opportunity_is_ready_for_mou()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-002',
            'title' => 'Test Opportunity 2',
            'owner_name' => 'John Doe',
            'owner_phone' => '1234567890',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::READY_FOR_MOU,
        ]);

        Livewire::test(OpportunityResource\Pages\ViewOpportunity::class, [
            'record' => $opportunity->getKey(),
        ])
            ->callAction('manageMou')
            ->assertRedirect();
    }

    public function test_opportunity_cannot_be_deleted_via_policy_or_resource(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-003',
            'title' => 'Delete Test Opportunity',
            'owner_name' => 'Jane Doe',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::NEW,
        ]);

        $this->assertFalse(OpportunityResource::canDelete($opportunity));
        $this->assertFalse(OpportunityResource::canDeleteAny());
        $this->assertFalse(OpportunityResource::canForceDelete($opportunity));
        $this->assertFalse(OpportunityResource::canForceDeleteAny());
        $this->assertFalse($user->can('delete', $opportunity));
        $this->assertFalse($user->can('deleteAny', Opportunity::class));
    }

    public function test_edit_opportunity_does_not_have_delete_action_and_can_close_lost(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-004',
            'title' => 'Edit Page Action Test Opportunity',
            'owner_name' => 'Jane Doe',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::NEW,
        ]);

        Livewire::test(OpportunityResource\Pages\EditOpportunity::class, [
            'record' => $opportunity->getKey(),
        ])
            ->assertActionDoesNotExist('delete')
            ->assertActionDoesNotExist('forceDelete')
            ->assertActionDoesNotExist('restore')
            ->assertActionExists('closeLost')
            ->callAction('closeLost', [
                'notes' => 'Owner changed their mind about renting out the flat.',
            ])
            ->assertRedirect(OpportunityResource::getUrl('view', ['record' => $opportunity]));

        $this->assertEquals(OpportunityStatus::CLOSED_LOST, $opportunity->fresh()->status);
    }

    public function test_cannot_close_lost_if_opportunity_already_converted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-005',
            'title' => 'Converted Opportunity Test',
            'owner_name' => 'Jane Doe',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::CONVERTED,
        ]);

        Livewire::test(OpportunityResource\Pages\EditOpportunity::class, [
            'record' => $opportunity->getKey(),
        ])
            ->assertActionHidden('closeLost');
    }

    public function test_edit_action_is_disabled_when_opportunity_is_closed_lost(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-006',
            'title' => 'Closed Lost Opportunity',
            'owner_name' => 'Jane Doe',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::CLOSED_LOST,
        ]);

        $this->assertFalse(OpportunityResource::canEdit($opportunity));
        $this->assertFalse($user->can('update', $opportunity));

        Livewire::test(OpportunityResource\Pages\ViewOpportunity::class, [
            'record' => $opportunity->getKey(),
        ])
            ->assertActionExists('edit')
            ->assertActionDisabled('edit');
    }

    public function test_cannot_access_edit_page_for_closed_lost_opportunity(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-007',
            'title' => 'Closed Lost Access Test',
            'owner_name' => 'Jane Doe',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::CLOSED_LOST,
        ]);

        Livewire::test(OpportunityResource\Pages\EditOpportunity::class, [
            'record' => $opportunity->getKey(),
        ])
            ->assertForbidden();
    }

    public function test_opportunities_table_is_sorted_by_latest_first(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $older = Opportunity::create([
            'number' => 'OPP-OLD',
            'title' => 'Older Opportunity',
            'owner_name' => 'Old Owner',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::NEW,
            'created_at' => now()->subDays(2),
        ]);

        $newer = Opportunity::create([
            'number' => 'OPP-NEW',
            'title' => 'Newer Opportunity',
            'owner_name' => 'New Owner',
            'owner_phone' => '9876543211',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::NEW,
            'created_at' => now(),
        ]);

        Livewire::test(OpportunityResource\Pages\ListOpportunities::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }

    public function test_expected_rent_and_source_columns_are_hidden_by_default(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $test = Livewire::test(OpportunityResource\Pages\ListOpportunities::class);

        $expectedRent = $test->instance()->getTable()->getColumn('expected_rent');
        $source = $test->instance()->getTable()->getColumn('opportunitySource.name');

        $this->assertTrue($expectedRent->isToggledHiddenByDefault());
        $this->assertTrue($expectedRent->isToggledHidden());
        $this->assertTrue($source->isToggledHiddenByDefault());
        $this->assertTrue($source->isToggledHidden());
    }

    public function test_trashed_filter_is_not_present_in_opportunities_table(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $test = Livewire::test(OpportunityResource\Pages\ListOpportunities::class);

        $filters = $test->instance()->getTable()->getFilters();
        $this->assertArrayNotHasKey('trashed', $filters);
    }

    public function test_opportunities_tabs_filter_by_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $activeOpp = Opportunity::create([
            'number' => 'OPP-ACT-01',
            'title' => 'Active Opportunity',
            'owner_name' => 'Active Owner',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::NEW,
        ]);

        $convertedOpp = Opportunity::create([
            'number' => 'OPP-CNV-01',
            'title' => 'Converted Opportunity',
            'owner_name' => 'Converted Owner',
            'owner_phone' => '9876543211',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::CONVERTED,
        ]);

        $closedLostOpp = Opportunity::create([
            'number' => 'OPP-LST-01',
            'title' => 'Lost Opportunity',
            'owner_name' => 'Lost Owner',
            'owner_phone' => '9876543212',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::CLOSED_LOST,
        ]);

        // Default 'all' tab shows all
        Livewire::test(OpportunityResource\Pages\ListOpportunities::class)
            ->assertCanSeeTableRecords([$activeOpp, $convertedOpp, $closedLostOpp]);

        // 'active' tab shows only active
        Livewire::test(OpportunityResource\Pages\ListOpportunities::class)
            ->set('activeTab', 'active')
            ->assertCanSeeTableRecords([$activeOpp])
            ->assertCanNotSeeTableRecords([$convertedOpp, $closedLostOpp]);

        // 'converted' tab shows only converted
        Livewire::test(OpportunityResource\Pages\ListOpportunities::class)
            ->set('activeTab', 'converted')
            ->assertCanSeeTableRecords([$convertedOpp])
            ->assertCanNotSeeTableRecords([$activeOpp, $closedLostOpp]);

        // 'closed_lost' tab shows only closed lost
        Livewire::test(OpportunityResource\Pages\ListOpportunities::class)
            ->set('activeTab', 'closed_lost')
            ->assertCanSeeTableRecords([$closedLostOpp])
            ->assertCanNotSeeTableRecords([$activeOpp, $convertedOpp]);
    }

    public function test_status_filter_options_only_include_active_pipeline_statuses(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $test = Livewire::test(OpportunityResource\Pages\ListOpportunities::class);

        $statusFilter = $test->instance()->getTable()->getFilter('status');
        $options = $statusFilter->getOptions();

        $expectedStatuses = [
            'new' => 'New Lead',
            'ready_for_mou' => 'Ready For MOU',
            'mou_created' => 'MOU Created',
            'mou_signed' => 'MOU Signed',
            'converted' => 'Converted to Property',
            'closed_lost' => 'Closed Lost',
        ];

        $this->assertEquals($expectedStatuses, $options);
    }

    public function test_edit_action_does_not_exist_on_opportunities_table(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $opportunity = Opportunity::create([
            'number' => 'OPP-NO-EDIT',
            'title' => 'No Edit Test',
            'owner_name' => 'Jane Doe',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::NEW,
        ]);

        Livewire::test(OpportunityResource\Pages\ListOpportunities::class)
            ->assertTableActionExists('view')
            ->assertTableActionDoesNotExist('edit');
    }
}
