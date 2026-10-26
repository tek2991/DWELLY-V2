<?php

namespace Tests\Feature;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Agreement\Services\TenancyDeboardingService;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Models\Audit;
use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Properties\Pages\ListProperties;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class PropertyMaintenanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Business Owner');

        $org = Organization::create([
            'name' => 'Dwelly Living Private Limited',
            'legal_name' => 'Dwelly Living Private Limited',
        ]);

        $this->branch = Branch::create([
            'organization_id' => $org->id,
            'name' => 'Bangalore Central',
            'code' => 'BLR-01',
            'city' => 'Bangalore',
        ]);

        $this->admin->branches()->attach($this->branch->id);
    }

    public function test_property_without_open_maintenance_does_not_appear_in_maintenance_tab(): void
    {
        $property = Property::create([
            'branch_id' => $this->branch->id,
            'code' => 'BLR-PROP-01',
            'building_name' => 'Brigade Lakefront 101',
            'status' => 'Vacant',
        ]);

        $this->actingAs($this->admin);

        $this->assertFalse($property->openMaintenanceRequests()->exists());
        $this->assertEquals(0, Property::withOpenMaintenance()->count());

        Livewire::test(ListProperties::class)
            ->assertSuccessful()
            ->set('activeTab', 'maintenance')
            ->assertDontSee('Brigade Lakefront 101');
    }

    public function test_property_with_open_maintenance_ticket_appears_in_maintenance_tab_with_age(): void
    {
        $property = Property::create([
            'branch_id' => $this->branch->id,
            'code' => 'BLR-PROP-02',
            'building_name' => 'Sobha Dream Acres 202',
            'status' => 'Vacant',
        ]);

        // Raise an open maintenance ticket created 3 days ago
        $ticket = MaintenanceRequest::create([
            'branch_id' => $this->branch->id,
            'property_id' => $property->id,
            'ticket_number' => 'MN-2001',
            'title' => 'Living Room Wall Repainting',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::IN_PROGRESS,
            'created_at' => now()->subDays(3),
        ]);

        $this->actingAs($this->admin);

        $this->assertTrue($property->openMaintenanceRequests()->exists());
        $this->assertEquals(1, Property::withOpenMaintenance()->count());

        Livewire::test(ListProperties::class)
            ->assertSuccessful()
            ->set('activeTab', 'maintenance')
            ->assertSee('Sobha Dream Acres 202')
            ->assertSee('MN-2001');
    }

    public function test_resolved_maintenance_tickets_do_not_flag_property_under_maintenance(): void
    {
        $property = Property::create([
            'branch_id' => $this->branch->id,
            'code' => 'BLR-PROP-03',
            'building_name' => 'Prestige Ferns 303',
            'status' => 'Vacant',
        ]);

        $ticket = MaintenanceRequest::create([
            'branch_id' => $this->branch->id,
            'property_id' => $property->id,
            'ticket_number' => 'MN-2002',
            'title' => 'Geyser Thermostat Replacement',
            'priority' => MaintenancePriority::MEDIUM,
            'status' => MaintenanceStatus::IN_PROGRESS,
        ]);

        $this->assertEquals(1, Property::withOpenMaintenance()->count());

        // Resolve ticket
        $ticket->update(['status' => MaintenanceStatus::RESOLVED]);

        $this->assertEquals(0, Property::withOpenMaintenance()->count());
        $this->assertFalse($property->openMaintenanceRequests()->exists());

        $this->actingAs($this->admin);

        Livewire::test(ListProperties::class)
            ->assertSuccessful()
            ->set('activeTab', 'maintenance')
            ->assertDontSee('Prestige Ferns 303');
    }

    public function test_deboarding_completion_returns_property_to_vacant_cleanly(): void
    {
        $property = Property::create([
            'branch_id' => $this->branch->id,
            'code' => 'BLR-PROP-04',
            'building_name' => 'Godrej Woodsman 404',
            'status' => 'Occupied',
        ]);

        $party = Party::create([
            'display_name' => 'Rohit Sharma',
            'party_type' => 'individual',
            'phone' => '+919988776655',
        ]);

        $agreement = TenancyAgreement::create([
            'branch_id' => $this->branch->id,
            'property_id' => $property->id,
            'code' => 'TNC-TEST-001',
            'status' => 'active',
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'rent_amount' => 25000,
            'security_deposit' => 50000,
        ]);

        $service = app(TenancyDeboardingService::class);
        $deboarding = $service->initiateDeboarding($agreement, [
            'notice_date' => now()->toDateString(),
            'vacating_date' => now()->toDateString(),
            'deboarding_reason' => 'Mutual Agreement',
        ]);

        $deboarding->moveOutAudit->update(['status' => AuditStatus::APPROVED]);

        $service->completeDeboardingAndVacate(
            $deboarding,
            'vacant',
            [
                'net_refund' => 45000,
                'settlement_status' => 'settled',
            ],
            $this->admin
        );

        $property->refresh();
        $this->assertEquals('vacant', $property->status);

        // Without an open maintenance ticket, property is NOT in the maintenance tab
        $this->assertFalse($property->openMaintenanceRequests()->exists());
        $this->assertEquals(0, Property::withOpenMaintenance()->count());

        // Now link a maintenance request for turnover repairs
        $ticket = MaintenanceRequest::create([
            'branch_id' => $this->branch->id,
            'property_id' => $property->id,
            'tenant_deboarding_id' => $deboarding->id,
            'ticket_number' => 'MN-DEBOARD-01',
            'title' => 'Post-Deboarding Wall Patchwork',
            'status' => MaintenanceStatus::SUBMITTED,
        ]);

        // Property status remains vacant, but dynamically appears in maintenance tab
        $property->refresh();
        $this->assertEquals('vacant', $property->status);
        $this->assertTrue($property->openMaintenanceRequests()->exists());
        $this->assertEquals(1, Property::withOpenMaintenance()->count());
    }
}
