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
}
