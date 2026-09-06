<?php

namespace Tests\Feature\Dashboard;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Property\Models\Property;
use App\Filament\Widgets\DashboardCadenceOverviewWidget;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class DashboardCadenceOverviewTest extends TestCase
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

    public function test_cadence_widget_renders_without_data(): void
    {
        Livewire::actingAs($this->user)
            ->test(DashboardCadenceOverviewWidget::class)
            ->assertSuccessful();
    }

    public function test_cadence_widget_calculates_expiring_leases_and_schedules(): void
    {
        $prop = Property::create([
            'building_name' => 'Rose Villa',
            'code' => 'PROP-RV-01',
            'status' => 'Occupied',
            'branch_id' => $this->branch->id,
        ]);

        // 1 active lease expiring in 18 days
        $agreement = TenancyAgreement::create([
            'property_id' => $prop->id,
            'code' => 'TNC-CAD-01',
            'status' => 'active',
            'start_date' => now()->subMonths(11)->toDateString(),
            'end_date' => now()->addDays(18)->toDateString(),
            'rent_amount' => 42000,
            'security_deposit' => 84000,
            'branch_id' => $this->branch->id,
        ]);

        $owner = \App\Domain\Party\Models\Party::create([
            'party_type' => 'individual',
            'display_name' => 'Owner Test',
        ]);

        // 1 Owner Payout
        OwnerPayout::create([
            'property_id' => $prop->id,
            'owner_id' => $owner->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'rent_collected' => 42000,
            'status' => 'completed',
            'amount' => 38000,
            'management_fee' => 4000,
            'processed_at' => now()->subDays(2),
            'branch_id' => $this->branch->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(DashboardCadenceOverviewWidget::class)
            ->assertSuccessful();

        $leases = $component->instance()->getLeaseExpirationsData();
        $this->assertEquals(1, $leases['total_expiring']);
        $this->assertEquals(1, $leases['critical_count']);
        $this->assertEquals(42000.0, $leases['rent_at_risk']);
        $this->assertCount(1, $leases['imminent_leases']);

        $cadence = $component->instance()->getCadenceData();
        $this->assertArrayHasKey('rent', $cadence);
        $this->assertArrayHasKey('payouts', $cadence);
        $this->assertEquals(38000.0, $cadence['payouts']['last_amount']);
        $this->assertNotEmpty($cadence['rent']['recommended_date']);
    }
}
