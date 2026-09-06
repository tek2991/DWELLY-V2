<?php

namespace Tests\Feature\Operations;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Models\Audit;
use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Pages\Operations\OperationsDashboard;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class OperationsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DefaultChartOfAccountsSeeder::class);

        $org = Organization::create([
            'name' => 'Dwelly Living Private Limited',
            'legal_name' => 'Dwelly Living Pvt Ltd',
            'currency_code' => 'INR',
        ]);

        $this->branch = Branch::create([
            'organization_id' => $org->id,
            'name' => 'Main Branch',
            'code' => 'MB-01',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->branches()->attach($this->branch->id);
    }

    public function test_operations_dashboard_page_loads(): void
    {
        Livewire::actingAs($this->user)
            ->test(OperationsDashboard::class)
            ->assertSuccessful()
            ->assertSet('activeTab', 'pipeline');
    }

    public function test_tab_switching_works_smoothly(): void
    {
        Livewire::actingAs($this->user)
            ->test(OperationsDashboard::class)
            ->call('setTab', 'maintenance')
            ->assertSet('activeTab', 'maintenance')
            ->call('setTab', 'audits')
            ->assertSet('activeTab', 'audits')
            ->call('setTab', 'moveins_moveouts')
            ->assertSet('activeTab', 'moveins_moveouts')
            ->call('setTab', 'pipeline')
            ->assertSet('activeTab', 'pipeline');
    }

    public function test_pipeline_data_reflects_property_stages(): void
    {
        Property::create([
            'building_name' => 'Silver Oak',
            'code' => 'PROP-SO1',
            'status' => 'Vacant',
            'branch_id' => $this->branch->id,
        ]);

        Property::create([
            'building_name' => 'Golden Palms',
            'code' => 'PROP-GP1',
            'status' => 'Occupied',
            'branch_id' => $this->branch->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(OperationsDashboard::class);

        $data = $component->instance()->getPipelineData();
        $this->assertEquals(2, $data['total']);
        $this->assertEquals(1, $data['vacant']);
        $this->assertEquals(1, $data['occupied']);
        $this->assertEquals(50.0, $data['occupancy_rate']);
    }

    public function test_maintenance_data_categorizes_by_priority(): void
    {
        $property = Property::create([
            'building_name' => 'Maple Tower',
            'code' => 'PROP-MT1',
            'status' => 'Occupied',
            'branch_id' => $this->branch->id,
        ]);

        MaintenanceRequest::create([
            'property_id' => $property->id,
            'ticket_number' => 'TKT-101',
            'title' => 'Gas Leakage Emergency',
            'priority' => MaintenancePriority::EMERGENCY,
            'status' => MaintenanceStatus::SUBMITTED,
            'branch_id' => $this->branch->id,
        ]);

        MaintenanceRequest::create([
            'property_id' => $property->id,
            'ticket_number' => 'TKT-102',
            'title' => 'Broken AC Cooling',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::IN_PROGRESS,
            'branch_id' => $this->branch->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(OperationsDashboard::class);

        $data = $component->instance()->getMaintenanceData();
        $this->assertEquals(2, $data['total_open']);
        $this->assertEquals(1, $data['p1_emergency']);
        $this->assertEquals(1, $data['p2_high']);
    }

    public function test_audits_data_identifies_pending_reviews(): void
    {
        $property = Property::create([
            'building_name' => 'Pine Residencies',
            'code' => 'PROP-PR1',
            'status' => 'Occupied',
            'branch_id' => $this->branch->id,
        ]);

        Audit::create([
            'property_id' => $property->id,
            'audit_number' => 'AUD-001',
            'audit_type' => \App\Domain\Audit\Enums\AuditType::PERIODIC,
            'status' => AuditStatus::PENDING_REVIEW,
            'branch_id' => $this->branch->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(OperationsDashboard::class);

        $data = $component->instance()->getAuditsData();
        $this->assertEquals(1, $data['pending_review_count']);
    }

    public function test_move_ins_and_deboardings_are_tracked(): void
    {
        $property = Property::create([
            'building_name' => 'Cedar Court',
            'code' => 'PROP-CC1',
            'status' => 'Occupied',
            'branch_id' => $this->branch->id,
        ]);

        $tenant = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Rajesh Kumar',
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'AGR-2026-001',
            'status' => 'active',
            'start_date' => now()->addDays(5)->toDateString(),
            'rent_amount' => 35000,
            'security_deposit' => 70000,
            'branch_id' => $this->branch->id,
        ]);

        TenantDeboarding::create([
            'tenancy_agreement_id' => $agreement->id,
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'code' => 'DEB-001',
            'status' => DeboardingStatus::AUDIT_PENDING,
            'notice_date' => now()->toDateString(),
            'target_vacating_date' => now()->addDays(10)->toDateString(),
            'branch_id' => $this->branch->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(OperationsDashboard::class);

        $data = $component->instance()->getMoveInsMoveOutsData();
        $this->assertGreaterThanOrEqual(1, $data['move_ins_count']);
        $this->assertGreaterThanOrEqual(1, $data['deboardings_count']);
    }
}
