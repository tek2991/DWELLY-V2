<?php

namespace Tests\Feature\Authorization;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Models\Audit;
use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Pages\Operations\InspectionQueue;
use App\Filament\Pages\Operations\ReviewQueue as AuditReviewQueue;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldOperationsAndMaintenanceRbacTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $cityManager;

    protected User $supplyManager;

    protected User $demandManager;

    protected User $opsManager;

    protected User $opsExecutive;

    protected User $accountant;

    protected Property $property;

    protected Party $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('Business Owner');

        $this->cityManager = User::factory()->create();
        $this->cityManager->assignRole('City Manager');

        $this->supplyManager = User::factory()->create();
        $this->supplyManager->assignRole('Supply Manager');

        $this->demandManager = User::factory()->create();
        $this->demandManager->assignRole('Demand Manager');

        $this->opsManager = User::factory()->create();
        $this->opsManager->assignRole('Operations Manager');

        $this->opsExecutive = User::factory()->create();
        $this->opsExecutive->assignRole('Operations Executive');

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole('Accountant');

        $this->property = Property::create([
            'building_name' => 'Silpukhuri Residency #4B',
            'address_line_1' => 'MG Road, Guwahati',
            'status' => 'occupied',
        ]);

        $this->tenant = Party::create([
            'display_name' => 'Pranjal Saikia',
            'phone' => '+91 91234 56789',
            'party_type' => 'individual',
        ]);
    }

    public function test_field_audits_inspection_queue_and_execution_boundaries(): void
    {
        $audit = Audit::create([
            'property_id' => $this->property->id,
            'audit_type' => AuditType::PERIODIC,
            'status' => AuditStatus::DRAFT,
            'audit_number' => 'AUD-2026-TEST1',
            'inspector_id' => $this->opsExecutive->id,
        ]);

        // Operations Executive has queue and field inspection access
        $this->actingAs($this->opsExecutive);
        $this->assertTrue(InspectionQueue::canAccess());
        $this->assertTrue($this->opsExecutive->can('viewAny', Audit::class));
        $this->assertTrue($this->opsExecutive->can('inspect', $audit));
        $this->assertTrue($this->opsExecutive->can('submit', $audit));

        // Operations Manager has queue and dispatch authority
        $this->actingAs($this->opsManager);
        $this->assertTrue(InspectionQueue::canAccess());
        $this->assertTrue($this->opsManager->can('viewAny', Audit::class));
        $this->assertTrue($this->opsManager->can('create', Audit::class));

        // Supply Manager, Demand Manager, and Accountant are strictly barred
        $this->actingAs($this->supplyManager);
        $this->assertFalse(InspectionQueue::canAccess());
        $this->assertFalse($this->supplyManager->can('viewAny', Audit::class));

        $this->actingAs($this->demandManager);
        $this->assertFalse(InspectionQueue::canAccess());
        $this->assertFalse($this->demandManager->can('viewAny', Audit::class));

        $this->actingAs($this->accountant);
        $this->assertFalse(InspectionQueue::canAccess());
        $this->assertFalse($this->accountant->can('viewAny', Audit::class));
    }

    public function test_four_eyes_audit_review_queue_and_sealing_gate(): void
    {
        $audit = Audit::create([
            'property_id' => $this->property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::PENDING_REVIEW,
            'audit_number' => 'AUD-2026-TEST2',
            'inspector_id' => $this->opsExecutive->id,
            'submitted_at' => now(),
        ]);

        // 4-Eyes Gate: Operations Executive who inspected cannot review or seal baseline
        $this->actingAs($this->opsExecutive);
        $this->assertFalse(AuditReviewQueue::canAccess());
        $this->assertFalse($this->opsExecutive->can('review', $audit));
        $this->assertFalse($this->opsExecutive->can('seal', $audit));

        // Operations Manager acts as the Checker
        $this->actingAs($this->opsManager);
        $this->assertTrue(AuditReviewQueue::canAccess());
        $this->assertTrue($this->opsManager->can('review', $audit));
        $this->assertTrue($this->opsManager->can('seal', $audit));

        // Seal the audit baseline
        $audit->is_locked = true;
        $audit->status = AuditStatus::COMPLETED;
        $audit->locked_at = now();
        $audit->save();

        // Once permanently sealed: Audit is immutable
        $this->assertFalse($this->opsManager->can('update', $audit));
        $this->assertFalse($this->opsManager->can('seal', $audit));
        $this->assertFalse($this->owner->can('seal', $audit));
    }

    public function test_maintenance_ticket_intake_access_boundaries(): void
    {
        // Demand Manager can log tickets on tenant behalf
        $this->assertTrue($this->demandManager->can('create', MaintenanceRequest::class));

        // Operations Executive can log tickets in the field
        $this->assertTrue($this->opsExecutive->can('create', MaintenanceRequest::class));

        // Operations Manager and City Manager can log tickets
        $this->assertTrue($this->opsManager->can('create', MaintenanceRequest::class));
        $this->assertTrue($this->cityManager->can('create', MaintenanceRequest::class));

        // Supply Manager has NO maintenance access (Chinese Wall)
        $this->assertFalse($this->supplyManager->can('viewAny', MaintenanceRequest::class));
        $this->assertFalse($this->supplyManager->can('create', MaintenanceRequest::class));
    }

    public function test_fault_attribution_and_work_order_issuance(): void
    {
        $ticket = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'ticket_number' => 'TKT-2026-TEST1',
            'title' => 'Geyser Thermostat Failure',
            'description' => 'Bathroom 1 geyser is tripping MCB switch.',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::SUBMITTED,
        ]);

        // Fault Attribution (Tenant Fault vs Owner Wear & Tear vs Dwelly Absorbed)
        $this->assertTrue($this->opsManager->can('attributeFault', $ticket));
        $this->assertTrue($this->cityManager->can('attributeFault', $ticket));
        $this->assertTrue($this->owner->can('attributeFault', $ticket));

        // Field executive and Demand Manager cannot attribute legal fault
        $this->assertFalse($this->opsExecutive->can('attributeFault', $ticket));
        $this->assertFalse($this->demandManager->can('attributeFault', $ticket));

        // Work Order Issuance (WO-XXXX)
        $this->assertTrue($this->opsManager->can('issueWorkOrder', $ticket));
        $this->assertTrue($this->cityManager->can('issueWorkOrder', $ticket));

        // Operations Executive and Accountant cannot issue work orders
        $this->assertFalse($this->opsExecutive->can('issueWorkOrder', $ticket));
        $this->assertFalse($this->accountant->can('issueWorkOrder', $ticket));
    }

    public function test_hidden_company_margin_confidentiality_protection(): void
    {
        $ticket = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'ticket_number' => 'TKT-2026-TEST2',
            'title' => 'Main Door Digital Lock Repair',
            'description' => 'Keypad unresponsive.',
            'priority' => MaintenancePriority::MEDIUM,
            'status' => MaintenanceStatus::QUOTED,
        ]);

        $quote = MaintenanceClientQuote::create([
            'maintenance_request_id' => $ticket->id,
            'quote_number' => 'QTE-2026-TEST1',
            'status' => 'draft',
            'subtotal_amount' => 5000.00,
            'margin_percentage' => 20.00,
            'margin_amount' => 1000.00,
            'total_amount' => 6000.00,
        ]);

        // Operations Executive: FORBIDDEN from viewing or setting company margins
        $this->assertFalse($this->opsExecutive->can('viewAny', MaintenanceClientQuote::class));
        $this->assertFalse($this->opsExecutive->can('view', $quote));
        $this->assertFalse($this->opsExecutive->can('applyMargin', $quote));
        $this->assertFalse($this->opsExecutive->can('viewMargin', $quote));

        // Operations Manager: Can set margins and review quotations
        $this->assertTrue($this->opsManager->can('viewAny', MaintenanceClientQuote::class));
        $this->assertTrue($this->opsManager->can('applyMargin', $quote));
        $this->assertTrue($this->opsManager->can('viewMargin', $quote));

        // Accountant: Can review gross profit margin for AP accrual tracking
        $this->assertTrue($this->accountant->can('viewAny', MaintenanceClientQuote::class));
        $this->assertTrue($this->accountant->can('viewMargin', $quote));
        $this->assertFalse($this->accountant->can('applyMargin', $quote));
    }

    public function test_ap_clearance_and_sign_off_gates(): void
    {
        $ticket = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'ticket_number' => 'TKT-2026-TEST3',
            'title' => 'Plumbing Pipe Leakage Repair',
            'description' => 'Kitchen sink siphon pipe replaced.',
            'priority' => MaintenancePriority::MEDIUM,
            'status' => MaintenanceStatus::IN_PROGRESS,
        ]);

        // Operations Manager (quality sign-off) and Accountant (clear for AP release)
        $this->assertTrue($this->opsManager->can('signOff', $ticket));
        $this->assertTrue($this->accountant->can('signOff', $ticket));
        $this->assertTrue($this->owner->can('signOff', $ticket));

        // Field executive and Demand manager cannot release AP payments
        $this->assertFalse($this->opsExecutive->can('signOff', $ticket));
        $this->assertFalse($this->demandManager->can('signOff', $ticket));
    }
}
