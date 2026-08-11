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
}

