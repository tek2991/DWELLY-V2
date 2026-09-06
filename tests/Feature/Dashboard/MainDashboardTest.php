<?php

namespace Tests\Feature\Dashboard;

use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Property\Models\Property;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\DashboardActionAlertsWidget;
use App\Filament\Widgets\MonthlyRevenueExpenseChartWidget;
use App\Filament\Widgets\PendingAuditsWidget;
use App\Filament\Widgets\PendingTasksWidget;
use App\Filament\Widgets\PropertyGrowthChartWidget;
use App\Filament\Widgets\PropertyStagesOverviewWidget;
use App\Filament\Widgets\TenantTurnoverChartWidget;
use App\Filament\Widgets\UrgentMaintenanceWidget;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class MainDashboardTest extends TestCase
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

    public function test_main_dashboard_page_loads_for_authenticated_user(): void
    {
        Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->assertSuccessful();
    }

    public function test_property_stages_overview_widget_renders_correctly(): void
    {
        Property::create([
            'building_name' => 'Lotus Villa',
            'code' => 'PROP-001',
            'status' => 'Occupied',
            'branch_id' => $this->branch->id,
        ]);

        Property::create([
            'building_name' => 'Skyline Heights',
            'code' => 'PROP-002',
            'status' => 'Vacant',
            'branch_id' => $this->branch->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(PropertyStagesOverviewWidget::class)
            ->assertSuccessful();
    }

    public function test_action_alerts_widget_calculates_urgent_items(): void
    {
        $prop = Property::create([
            'building_name' => 'Alpha House',
            'code' => 'PROP-003',
            'status' => 'Occupied',
            'branch_id' => $this->branch->id,
        ]);

        MaintenanceRequest::create([
            'property_id' => $prop->id,
            'ticket_number' => 'TKT-999',
            'title' => 'Burst Pipe in Bathroom',
            'priority' => MaintenancePriority::EMERGENCY,
            'status' => MaintenanceStatus::SUBMITTED,
            'branch_id' => $this->branch->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(DashboardActionAlertsWidget::class)
            ->assertSuccessful();

        $alerts = $component->instance()->getAlertsData();
        $this->assertGreaterThanOrEqual(1, $alerts['maintenance']['count']);
        $this->assertGreaterThanOrEqual(1, $alerts['maintenance']['urgent_count']);
    }

    public function test_all_three_trend_charts_render_without_errors(): void
    {
        Livewire::actingAs($this->user)
            ->test(MonthlyRevenueExpenseChartWidget::class)
            ->assertSuccessful();

        Livewire::actingAs($this->user)
            ->test(PropertyGrowthChartWidget::class)
            ->assertSuccessful();

        Livewire::actingAs($this->user)
            ->test(TenantTurnoverChartWidget::class)
            ->assertSuccessful();
    }

    public function test_action_tables_render_cleanly(): void
    {
        Livewire::actingAs($this->user)
            ->test(UrgentMaintenanceWidget::class)
            ->assertSuccessful();

        Livewire::actingAs($this->user)
            ->test(PendingAuditsWidget::class)
            ->assertSuccessful();

        Livewire::actingAs($this->user)
            ->test(PendingTasksWidget::class)
            ->assertSuccessful();
    }
}
