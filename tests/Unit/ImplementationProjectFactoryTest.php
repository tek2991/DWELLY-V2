<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Implementation\Models\WorkflowTemplate;
use App\Domain\Implementation\Models\WorkflowStage;
use App\Domain\Implementation\Services\ImplementationProjectFactory;
use App\Domain\Property\Models\Property;

class ImplementationProjectFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_project_snapshot_from_template()
    {
        // Setup Template
        $template = WorkflowTemplate::create([
            'name' => 'Apartment Onboarding',
            'type' => 'Property Onboarding',
            'version' => 1,
            'is_active' => true,
            'estimated_duration_days' => 14,
        ]);

        $stage = $template->stages()->create([
            'name' => 'Planning',
            'order' => 1,
        ]);

        $workPackage = $template->workPackages()->create([
            'name' => 'General Info'
        ]);

        $task = $template->taskTemplates()->create([
            'work_package_id' => $workPackage->id,
            'stage_id' => $stage->id,
            'title' => 'Verify Address',
            'weight' => 5
        ]);

        $task->checklists()->create(['item_text' => 'Checked with owner']);

        // Setup Entity using User instead of Property to avoid migration complexites in this test
        $entity = \App\Models\User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Action
        $factory = new ImplementationProjectFactory();
        $project = $factory->createFromTemplate($entity, $template);

        // Assert
        $this->assertEquals($template->id, $project->workflow_template_id);
        $this->assertEquals(\App\Models\User::class, $project->entity_type);
        $this->assertEquals($entity->id, $project->entity_id);
        
        $this->assertCount(1, $project->stages);
        $this->assertEquals('Planning', $project->stages->first()->name);

        $this->assertCount(1, $project->tasks);
        $this->assertEquals('Verify Address', $project->tasks->first()->title);
        $this->assertEquals(5, $project->tasks->first()->weight);

        $this->assertCount(1, $project->tasks->first()->checklists);
        $this->assertEquals('Checked with owner', $project->tasks->first()->checklists->first()->item_text);

        // Verify Snapshot Integrity (Modifying template doesn't affect instance)
        $stage->update(['name' => 'Changed Name']);
        $this->assertEquals('Planning', $project->fresh()->stages->first()->name);
    }
}
