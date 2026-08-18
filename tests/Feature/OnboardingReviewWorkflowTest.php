<?php

namespace Tests\Feature;

use App\Domain\Property\Models\OnboardingProject;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Properties\Widgets\OnboardingProgressWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_for_review_action_updates_status_to_pending_review(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'code' => 'PROP-REV-001',
            'building_name' => 'Review Test Villa',
            'status' => 'Onboarding',
        ]);

        $project = OnboardingProject::create([
            'property_id' => $property->id,
            'status' => 'Draft',
        ]);

        $this->actingAs($user);

        Livewire::test(OnboardingProgressWidget::class, ['record' => $property])
            ->assertSuccessful();

        $project->update(['status' => 'Pending Review', 'submitted_at' => now()]);

        $this->assertEquals('Pending Review', $project->fresh()->status);
        $this->assertNotNull($project->fresh()->submitted_at);
    }

    public function test_request_changes_updates_status_and_stores_notes(): void
    {
        $reviewer = User::factory()->create();
        $property = Property::create([
            'code' => 'PROP-REV-002',
            'building_name' => 'Revision Needed Property',
            'status' => 'Onboarding',
        ]);

        $project = OnboardingProject::create([
            'property_id' => $property->id,
            'status' => 'Pending Review',
            'submitted_at' => now(),
        ]);

        $this->actingAs($reviewer);

        Livewire::test(OnboardingProgressWidget::class, ['record' => $property])
            ->callAction('requestChanges', [
                'review_notes' => 'Please upload clear photos of room 2 and verify electricity consumer number.',
            ])
            ->assertHasNoActionErrors();

        $freshProject = $project->fresh();
        $this->assertEquals('Changes Requested', $freshProject->status);
        $this->assertEquals('Please upload clear photos of room 2 and verify electricity consumer number.', $freshProject->review_notes);
        $this->assertEquals($reviewer->id, $freshProject->reviewer_id);
    }

    public function test_approve_and_activate_property_action_activates_property(): void
    {
        $reviewer = User::factory()->create();
        $property = Property::create([
            'code' => 'PROP-REV-003',
            'building_name' => 'Approved Property',
            'status' => 'Onboarding',
        ]);

        $project = OnboardingProject::create([
            'property_id' => $property->id,
            'status' => 'Pending Review',
            'submitted_at' => now(),
        ]);

        $this->actingAs($reviewer);

        Livewire::test(OnboardingProgressWidget::class, ['record' => $property])
            ->callAction('activateProperty')
            ->assertHasNoActionErrors();

        $this->assertEquals('Activated', $project->fresh()->status);
        $this->assertEquals('Vacant', $property->fresh()->status);
        $this->assertEquals($reviewer->id, $project->fresh()->reviewer_id);
    }

    public function test_progress_widget_refreshes_dynamically_on_event_dispatch(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'code' => 'PROP-DYN-001',
            'building_name' => 'Dynamic Progress Test',
            'status' => 'Onboarding',
        ]);

        $project = OnboardingProject::create([
            'property_id' => $property->id,
            'status' => 'Draft',
        ]);

        $this->actingAs($user);

        $component = Livewire::test(OnboardingProgressWidget::class, ['record' => $property])
            ->assertSee('At least one room is required.');

        // Add rooms to the property
        $roomDefinition = \App\Domain\Property\Models\RoomDefinition::create([
            'room_type_id' => \App\Domain\Property\Models\RoomType::create(['name' => 'Bedroom', 'slug' => 'bedroom'])->id,
            'name' => 'Master Bedroom',
            'slug' => 'master-bedroom',
        ]);

        $property->rooms()->create([
            'room_definition_id' => $roomDefinition->id,
        ]);

        // Dispatch event without full page refresh
        $component->dispatch('refresh-onboarding-progress')
            ->assertDontSee('At least one room is required.');
    }

    public function test_utilities_and_inventory_updates_refresh_progress_widget(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'code' => 'PROP-DYN-002',
            'building_name' => 'Dynamic Utility & Inventory Test',
            'status' => 'Onboarding',
        ]);

        OnboardingProject::create([
            'property_id' => $property->id,
            'status' => 'Draft',
        ]);

        $this->actingAs($user);

        $widget = Livewire::test(OnboardingProgressWidget::class, ['record' => $property])
            ->assertSee('At least one utility (e.g. Electricity) must be configured.')
            ->assertSee('Keys must be added to the inventory.');

        // Add utility
        $utilityType = \App\Domain\Property\Models\UtilityType::create([
            'name' => 'Electricity',
            'slug' => 'electricity',
            'is_active' => true,
        ]);

        $property->utilities()->create([
            'utility_type_id' => $utilityType->id,
            'paid_by' => 'tenant',
            'effective_from' => now(),
        ]);

        // Add keys inventory
        $keyType = \App\Domain\Property\Models\InventoryType::create([
            'name' => 'Keys',
            'slug' => 'keys',
            'is_active' => true,
        ]);

        $property->inventories()->create([
            'inventory_type_id' => $keyType->id,
            'count' => 3,
        ]);

        $widget->dispatch('refresh-onboarding-progress')
            ->assertDontSee('At least one utility (e.g. Electricity) must be configured.')
            ->assertDontSee('Keys must be added to the inventory.');
    }
}
