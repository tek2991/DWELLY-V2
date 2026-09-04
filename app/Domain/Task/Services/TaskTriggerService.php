<?php

namespace App\Domain\Task\Services;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Property\Models\Property;
use App\Domain\Task\Enums\TaskCategory;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Models\TaskTemplate;

class TaskTriggerService
{
    public function __construct(
        protected TaskService $taskService
    ) {}

    /**
     * Triggered when a Tenancy Agreement is activated.
     * Generates Tenant Police Verification and 7-Day Move-In Check-In tasks.
     */
    public function onAgreementActivated(TenancyAgreement $agreement): void
    {
        $property = $agreement->property;
        if (! $property) {
            return;
        }

        $tenantName = $agreement->primaryTenant?->party?->display_name ?? 'Tenant';

        // 1. Police Verification Task
        $policeTemplate = TaskTemplate::where('code', 'POLICE_VERIFICATION')->first();
        if ($policeTemplate) {
            $this->taskService->createFromTemplate($policeTemplate, $property, [
                'taskable_type' => TenancyAgreement::class,
                'taskable_id' => $agreement->id,
                'title' => "Tenant Police Verification — {$tenantName} ({$agreement->code})",
            ]);
        } else {
            $this->taskService->createTask([
                'property_id' => $property->id,
                'taskable_type' => TenancyAgreement::class,
                'taskable_id' => $agreement->id,
                'category' => TaskCategory::COMPLIANCE,
                'title' => "Tenant Police Verification — {$tenantName} ({$agreement->code})",
                'description' => "Complete tenant police verification submission for {$tenantName} and upload stamped acknowledgment challan.",
                'priority' => TaskPriority::HIGH,
                'due_date' => now()->addDays(7),
                'checklist_items' => [
                    ['title' => 'Collect Tenant Aadhaar, Photo & Permanent Address Proof', 'is_mandatory' => true],
                    ['title' => 'Fill Police Verification Form & Attach Agreement Copy', 'is_mandatory' => true],
                    ['title' => 'Submit to Local Police Station & Collect Stamped Challan', 'is_mandatory' => true],
                    ['title' => 'Upload Stamped Challan to Task Completion Proofs', 'is_mandatory' => true],
                ],
            ]);
        }

        // 2. 7-Day Move-In Check-In Call
        $this->taskService->createTask([
            'property_id' => $property->id,
            'taskable_type' => TenancyAgreement::class,
            'taskable_id' => $agreement->id,
            'category' => TaskCategory::LIFECYCLE,
            'title' => "7-Day Move-In Check-In Call — {$tenantName}",
            'description' => "Call tenant to verify move-in comfort, appliance functioning, and resolve any initial settle-in questions.",
            'priority' => TaskPriority::MEDIUM,
            'due_date' => now()->addDays(7),
            'checklist_items' => [
                ['title' => 'Call Tenant and verify keys, remotes & society access cards', 'is_mandatory' => true],
                ['title' => 'Confirm electricity submeter readings match move-in audit', 'is_mandatory' => true],
                ['title' => 'Check if any minor appliance or fixture issue was discovered', 'is_mandatory' => false],
                ['title' => 'Log call feedback and update resolution notes', 'is_mandatory' => true],
            ],
        ]);
    }

    /**
     * Triggered when a Property status changes to Vacant.
     * Generates To-Let Signboard and Marketing Media capture task.
     */
    public function onPropertyVacant(Property $property): void
    {
        $this->taskService->createTask([
            'property_id' => $property->id,
            'category' => TaskCategory::FIELD_WORK,
            'title' => "Install To-Let Board & Capture Listing Media — {$property->code}",
            'description' => "Visit property, install Dwelly To-Let signboard on balcony/gate, and capture updated marketing photos.",
            'priority' => TaskPriority::HIGH,
            'due_date' => now()->addDays(3),
            'checklist_items' => [
                ['title' => 'Install Dwelly To-Let Board with contact number', 'is_mandatory' => true],
                ['title' => 'Capture clear photos of living room, bedrooms, kitchen, bathrooms', 'is_mandatory' => true],
                ['title' => 'Verify master keys are in designated lockbox or office vault', 'is_mandatory' => true],
                ['title' => 'Verify flat is clean, aired, and ready for prospective tenant visits', 'is_mandatory' => true],
            ],
        ]);
    }

    /**
     * Triggered when a Deboarding Notice is initiated.
     * Generates Key Handover & Exit Inspection prep task.
     */
    public function onDeboardingInitiated(TenantDeboarding $deboarding): void
    {
        $property = $deboarding->property;
        if (! $property) {
            return;
        }

        $this->taskService->createTask([
            'property_id' => $property->id,
            'taskable_type' => TenantDeboarding::class,
            'taskable_id' => $deboarding->id,
            'category' => TaskCategory::QUALITY_ASSURANCE,
            'title' => "Exit Handover & Key Retrieval Prep — {$property->code}",
            'description' => "Coordinate key handover with tenant, verify physical condition against move-in baseline, and retrieve all spare sets.",
            'priority' => TaskPriority::HIGH,
            'due_date' => $deboarding->target_vacating_date ? \Carbon\Carbon::parse($deboarding->target_vacating_date) : now()->addDays(30),
            'checklist_items' => [
                ['title' => 'Confirm exact vacating time with tenant and schedule field executive', 'is_mandatory' => true],
                ['title' => 'Conduct physical move-out inspection against move-in audit baseline', 'is_mandatory' => true],
                ['title' => 'Collect all physical keys, gate remotes, and society access badges', 'is_mandatory' => true],
                ['title' => 'Take final electricity and water submeter readings', 'is_mandatory' => true],
            ],
        ]);
    }

    /**
     * Triggered when a Maintenance Request is completed.
     * Generates Post-Maintenance Quality Check & Tenant Satisfaction task.
     */
    public function onMaintenanceCompleted(MaintenanceRequest $request): void
    {
        $property = $request->property;
        if (! $property) {
            return;
        }

        $this->taskService->createTask([
            'property_id' => $property->id,
            'taskable_type' => MaintenanceRequest::class,
            'taskable_id' => $request->id,
            'category' => TaskCategory::QUALITY_ASSURANCE,
            'title' => "Post-Maintenance Quality Check — Ticket #{$request->ticket_number}",
            'description' => "Verify quality of completed repair work for '{$request->title}' and obtain tenant satisfaction signoff.",
            'priority' => TaskPriority::MEDIUM,
            'due_date' => now()->addDays(2),
            'checklist_items' => [
                ['title' => 'Inspect repaired fixture/area or review high-res completion photos', 'is_mandatory' => true],
                ['title' => 'Verify site was left clean and debris removed by vendor', 'is_mandatory' => true],
                ['title' => 'Confirm tenant satisfaction with the repair quality', 'is_mandatory' => true],
            ],
        ]);
    }
}
