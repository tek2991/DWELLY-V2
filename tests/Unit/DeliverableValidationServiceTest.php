<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Implementation\Models\ImplementationProject;
use App\Domain\Implementation\Models\ImplementationDeliverable;
use App\Domain\Implementation\Services\DeliverableValidationService;
use App\Domain\Implementation\Managers\DeliverableManager;
use App\Domain\Implementation\Contracts\DeliverableProvider;
use App\Domain\Implementation\Contracts\ValidationResult;
use Illuminate\Database\Eloquent\Model;
use App\Domain\Property\Models\Property;

class MockDeliverableProvider implements DeliverableProvider
{
    public function validate(Model $entity, ?array $parameters): ValidationResult
    {
        if (isset($parameters['should_pass']) && $parameters['should_pass']) {
            return ValidationResult::pass();
        }
        return ValidationResult::fail('Test failure');
    }
}

class DeliverableValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_verifies_deliverables_via_provider()
    {
        $manager = new DeliverableManager();
        $manager->register('mock_provider', new MockDeliverableProvider());

        $service = new DeliverableValidationService($manager);

        // Setup test data
        $template = \App\Domain\Implementation\Models\WorkflowTemplate::create([
            'name' => 'Test Template',
            'type' => 'Test',
        ]);

        $project = ImplementationProject::create([
            'workflow_template_id' => $template->id,
            'entity_type' => Property::class,
            'entity_id' => \Illuminate\Support\Str::ulid(), // mock
        ]);

        $deliverable = ImplementationDeliverable::create([
            'project_id' => $project->id,
            'name' => 'Mock Deliverable',
            'provider_key' => 'mock_provider',
            'validation_parameters' => ['should_pass' => true],
        ]);

        // Need a mock property
        $property = new Property();
        $property->id = $project->entity_id;

        // Action
        $result = $service->verifyDeliverable($deliverable, $property);

        // Assert
        $this->assertTrue($result);
        $this->assertEquals('verified', $deliverable->fresh()->status);
    }
    
    public function test_it_handles_validation_failures()
    {
        $manager = new DeliverableManager();
        $manager->register('mock_provider', new MockDeliverableProvider());

        $service = new DeliverableValidationService($manager);

        // Setup test data
        $template = \App\Domain\Implementation\Models\WorkflowTemplate::create([
            'name' => 'Test Template',
            'type' => 'Test',
        ]);

        $project = ImplementationProject::create([
            'workflow_template_id' => $template->id,
            'entity_type' => Property::class,
            'entity_id' => \Illuminate\Support\Str::ulid(), // mock
        ]);

        $deliverable = ImplementationDeliverable::create([
            'project_id' => $project->id,
            'name' => 'Mock Deliverable',
            'provider_key' => 'mock_provider',
            'validation_parameters' => ['should_pass' => false],
        ]);

        $property = new Property();
        $property->id = $project->entity_id;

        // Action
        $result = $service->verifyDeliverable($deliverable, $property);

        // Assert
        $this->assertFalse($result);
        $this->assertEquals('rejected', $deliverable->fresh()->status);
    }
}
