<?php

namespace Tests\Feature\Operations;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Property\Models\Property;
use App\Domain\Task\Enums\TaskCategory;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskTemplate;
use App\Domain\Task\Services\TaskService;
use App\Domain\Task\Services\TaskTriggerService;
use App\Models\User;
use Database\Seeders\TaskTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaskTemplateSeeder::class);
    }

    public function test_task_can_be_created_with_auto_generated_task_number(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'code' => 'PROP-TEST-001',
            'building_name' => 'Dwelly Heights',
            'status' => 'active',
        ]);

        $service = app(TaskService::class);
        $task = $service->createTask([
            'property_id' => $property->id,
            'category' => TaskCategory::FIELD_WORK,
            'title' => 'Install To-Let Signboard',
            'priority' => TaskPriority::HIGH,
            'checklist_items' => [
                ['title' => 'Mount signboard on balcony', 'is_mandatory' => true],
                ['title' => 'Take photo verification', 'is_mandatory' => true],
            ],
        ], $user);

        $this->assertNotNull($task->id);
        $this->assertStringStartsWith('TSK-', $task->task_number);
        $this->assertEquals($property->id, $task->property_id);
        $this->assertEquals(TaskStatus::PENDING, $task->status);
        $this->assertEquals(2, $task->checklistItems()->count());
    }

    public function test_task_can_be_instantiated_from_template_with_checklists(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'code' => 'PROP-TEST-002',
            'building_name' => 'Dwelly Villa',
            'status' => 'active',
        ]);

        $template = TaskTemplate::where('code', 'POLICE_VERIFICATION')->firstOrFail();

        $service = app(TaskService::class);
        $task = $service->createFromTemplate($template, $property, [], $user);

        $this->assertEquals('Tenant Police Verification', $task->title);
        $this->assertEquals(TaskCategory::COMPLIANCE, $task->category);
        $this->assertEquals(TaskPriority::HIGH, $task->priority);
        $this->assertEquals(4, $task->checklistItems()->count());
        $this->assertNotNull($task->due_date);
    }

    public function test_cannot_complete_task_if_mandatory_checklists_are_incomplete(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'code' => 'PROP-TEST-003',
            'building_name' => 'Green Garden',
            'status' => 'active',
        ]);

        $service = app(TaskService::class);
        $task = $service->createTask([
            'property_id' => $property->id,
            'category' => TaskCategory::COMPLIANCE,
            'title' => 'Collect NOC from Society',
            'checklist_items' => [
                ['title' => 'Obtain signed NOC from President', 'is_mandatory' => true, 'is_completed' => false],
            ],
        ], $user);

        $this->expectException(ValidationException::class);
        $service->completeTask($task, 'Attempting completion without checklist', $user);
    }

    public function test_can_complete_task_when_all_mandatory_checklists_are_checked(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'code' => 'PROP-TEST-004',
            'building_name' => 'Dwelly Residency',
            'status' => 'active',
        ]);

        $service = app(TaskService::class);
        $task = $service->createTask([
            'property_id' => $property->id,
            'category' => TaskCategory::FIELD_WORK,
            'title' => 'Key Handover',
            'checklist_items' => [
                ['title' => 'Handover 2 sets of master keys', 'is_mandatory' => true, 'is_completed' => false],
                ['title' => 'Optional extra note', 'is_mandatory' => false, 'is_completed' => false],
            ],
        ], $user);

        $mandatoryItem = $task->checklistItems()->where('is_mandatory', true)->first();
        $service->toggleChecklistItem($mandatoryItem, true, $user);

        $completedTask = $service->completeTask($task, 'Keys handed over and receipt signed.', $user);

        $this->assertEquals(TaskStatus::COMPLETED, $completedTask->status);
        $this->assertNotNull($completedTask->completed_at);
        $this->assertEquals('Keys handed over and receipt signed.', $completedTask->resolution_notes);
    }

    public function test_task_trigger_service_creates_tasks_on_agreement_activation(): void
    {
        $property = Property::create([
            'code' => 'PROP-TEST-005',
            'building_name' => 'Sunrise Tower',
            'status' => 'occupied',
        ]);

        $agreement = TenancyAgreement::create([
            'code' => 'TNC-TEST-001',
            'property_id' => $property->id,
            'status' => 'active',
            'rent_amount' => 15000.00,
            'security_deposit' => 30000.00,
            'agreement_start_date' => now()->toDateString(),
            'agreement_end_date' => now()->addYear()->toDateString(),
        ]);

        $triggerService = app(TaskTriggerService::class);
        $triggerService->onAgreementActivated($agreement);

        $this->assertEquals(2, $property->tasks()->count());
        $this->assertTrue($property->tasks()->where('category', TaskCategory::COMPLIANCE->value)->exists());
        $this->assertTrue($property->tasks()->where('category', TaskCategory::LIFECYCLE->value)->exists());
    }

    public function test_filament_task_resource_pages_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'code' => 'PROP-TEST-006',
            'building_name' => 'Pine Wood',
            'status' => 'active',
        ]);

        $task = Task::create([
            'task_number' => 'TSK-2026-00099',
            'property_id' => $property->id,
            'category' => TaskCategory::FIELD_WORK,
            'title' => 'Signboard Installation',
            'status' => TaskStatus::PENDING,
            'priority' => TaskPriority::HIGH,
        ]);

        $this->actingAs($user);

        \Livewire\Livewire::test(\App\Filament\Resources\Operations\TaskResource\Pages\ListTasks::class)
            ->assertSuccessful()
            ->assertSee('TSK-2026-00099')
            ->assertSee('Signboard Installation');

        \Livewire\Livewire::test(\App\Filament\Resources\Operations\TaskResource\Pages\CreateTask::class)
            ->assertSuccessful();

        \Livewire\Livewire::test(\App\Filament\Resources\Operations\TaskResource\Pages\EditTask::class, [
            'record' => $task->id,
        ])
            ->assertSuccessful()
            ->assertSee('TSK-2026-00099');

        \Livewire\Livewire::test(\App\Filament\Resources\Settings\TaskTemplateResource\Pages\ListTaskTemplates::class)
            ->assertSuccessful()
            ->assertSee('Tenant Police Verification');
    }
}
