<?php

namespace Tests\Feature;

use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Operations\MaintenanceRequestResource;
use App\Filament\Resources\Properties\Pages\ListProperties;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class PropertyMaintenanceTableLinkTest extends TestCase
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

    public function test_single_open_maintenance_ticket_links_directly_to_ticket_edit_page(): void
    {
        $property = Property::create([
            'branch_id' => $this->branch->id,
            'code' => 'PROP-MNT-01',
            'building_name' => 'Prestige Falcon City 101',
            'status' => 'Vacant',
        ]);

        $ticket = MaintenanceRequest::create([
            'branch_id' => $this->branch->id,
            'property_id' => $property->id,
            'ticket_number' => 'MNT-10001',
            'title' => 'Geyser Thermostat Issue',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::SUBMITTED,
            'created_at' => now()->subDays(2),
        ]);

        $this->actingAs($this->admin);

        $expectedUrl = MaintenanceRequestResource::getUrl('edit', ['record' => $ticket]);

        Livewire::test(ListProperties::class)
            ->assertSuccessful()
            ->assertSee('Prestige Falcon City 101')
            ->assertSee('MNT-10001')
            ->assertSee('Geyser Thermostat Issue')
            ->assertSee($expectedUrl);
    }

    public function test_multiple_open_maintenance_tickets_render_modal_with_all_tickets_and_links(): void
    {
        $property = Property::create([
            'branch_id' => $this->branch->id,
            'code' => 'PROP-MNT-02',
            'building_name' => 'Sobha Silicon Oasis 202',
            'status' => 'Occupied',
        ]);

        $ticket1 = MaintenanceRequest::create([
            'branch_id' => $this->branch->id,
            'property_id' => $property->id,
            'ticket_number' => 'MNT-20001',
            'title' => 'Living Room AC Not Cooling',
            'priority' => MaintenancePriority::EMERGENCY,
            'status' => MaintenanceStatus::IN_PROGRESS,
            'created_at' => now()->subDays(4),
        ]);

        $ticket2 = MaintenanceRequest::create([
            'branch_id' => $this->branch->id,
            'property_id' => $property->id,
            'ticket_number' => 'MNT-20002',
            'title' => 'Kitchen Sink Drain Leakage',
            'priority' => MaintenancePriority::MEDIUM,
            'status' => MaintenanceStatus::SUBMITTED,
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($this->admin);

        $ticket1Url = MaintenanceRequestResource::getUrl('edit', ['record' => $ticket1]);
        $ticket2Url = MaintenanceRequestResource::getUrl('edit', ['record' => $ticket2]);

        $livewire = Livewire::test(ListProperties::class)
            ->assertSuccessful()
            ->assertSee('Sobha Silicon Oasis 202')
            ->assertSee('2 Tickets')
            // Mount the action on the table for this property
            ->mountTableAction('openMaintenanceTickets', $property->id);

        $action = $livewire->instance()->getMountedAction();
        $this->assertNotNull($action);
        $this->assertEquals('openMaintenanceTickets', $action->getName());

        $content = (string) $action->getModalContent();
        $this->assertStringContainsString('MNT-20001', $content);
        $this->assertStringContainsString('Living Room AC Not Cooling', $content);
        $this->assertStringContainsString($ticket1Url, $content);
        $this->assertStringContainsString('MNT-20002', $content);
        $this->assertStringContainsString('Kitchen Sink Drain Leakage', $content);
        $this->assertStringContainsString($ticket2Url, $content);
    }

    public function test_property_without_maintenance_tickets_has_no_maintenance_links(): void
    {
        $property = Property::create([
            'branch_id' => $this->branch->id,
            'code' => 'PROP-CLEAN-01',
            'building_name' => 'Brigade Millennium 303',
            'status' => 'Vacant',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(ListProperties::class)
            ->assertSuccessful()
            ->assertSee('Brigade Millennium 303')
            ->assertTableActionHidden('maintenanceTickets', $property->id);
    }
}
