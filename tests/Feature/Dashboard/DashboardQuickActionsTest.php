<?php

namespace Tests\Feature\Dashboard;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Property\Models\Property;
use App\Filament\Widgets\DashboardActionAlertsWidget;
use App\Filament\Widgets\DashboardQuickActionsWidget;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class DashboardQuickActionsTest extends TestCase
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

    public function test_quick_actions_widget_renders_all_actions(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(DashboardQuickActionsWidget::class)
            ->assertSuccessful();

        $actions = $component->instance()->getQuickActions();
        $this->assertCount(7, $actions);

        $labels = array_column($actions, 'label');
        $this->assertContains('Add Property', $labels);
        $this->assertContains('New Tenancy', $labels);
        $this->assertContains('Log Repair Ticket', $labels);
        $this->assertContains('Schedule Audit', $labels);
        $this->assertContains('Generate Rent', $labels);
        $this->assertContains('Disburse Payouts', $labels);
        $this->assertContains('Renewals Console', $labels);
    }

    public function test_expiring_leases_alert_calculates_correctly(): void
    {
        $prop = Property::create([
            'building_name' => 'Sunrise Villa',
            'code' => 'PROP-SN-01',
            'status' => 'Occupied',
            'branch_id' => $this->branch->id,
        ]);

        // 1 active lease expiring in 20 days
        TenancyAgreement::create([
            'property_id' => $prop->id,
            'code' => 'TNC-EXP-01',
            'status' => 'active',
            'start_date' => now()->subMonths(11)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'rent_amount' => 35000,
            'security_deposit' => 70000,
            'branch_id' => $this->branch->id,
        ]);

        // 1 active lease expiring in 40 days
        TenancyAgreement::create([
            'property_id' => $prop->id,
            'code' => 'TNC-EXP-02',
            'status' => 'active',
            'start_date' => now()->subMonths(10)->toDateString(),
            'end_date' => now()->addDays(40)->toDateString(),
            'rent_amount' => 45000,
            'security_deposit' => 90000,
            'branch_id' => $this->branch->id,
        ]);

        // 1 active lease expiring in 120 days (outside 60-day window)
        TenancyAgreement::create([
            'property_id' => $prop->id,
            'code' => 'TNC-EXP-03',
            'status' => 'active',
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->addDays(120)->toDateString(),
            'rent_amount' => 50000,
            'security_deposit' => 100000,
            'branch_id' => $this->branch->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(DashboardActionAlertsWidget::class)
            ->assertSuccessful();

        $alerts = $component->instance()->getAlertsData();
        $this->assertArrayHasKey('expiring_leases', $alerts);
        $this->assertEquals(2, $alerts['expiring_leases']['count']);
        $this->assertEquals(80000.0, $alerts['expiring_leases']['total']);
    }
}
