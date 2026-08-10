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
}
