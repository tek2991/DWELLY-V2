<?php

namespace Tests\Feature\Operations;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Property\Models\Property;
use App\Filament\Pages\Operations\OperationsDashboard;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class OperationsRenewalsTabTest extends TestCase
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

    public function test_renewals_tab_can_be_activated(): void
    {
        Livewire::actingAs($this->user)
            ->test(OperationsDashboard::class)
            ->call('setTab', 'renewals')
            ->assertSet('activeTab', 'renewals')
            ->assertSuccessful();
    }

    public function test_renewals_data_buckets_leases_by_expiry_horizon(): void
    {
        $prop = Property::create([
            'building_name' => 'Cyber Heights',
            'code' => 'PROP-CH-01',
            'status' => 'Occupied',
            'branch_id' => $this->branch->id,
        ]);

        // 1 Critical lease (< 30 days)
        TenancyAgreement::create([
            'property_id' => $prop->id,
            'code' => 'TNC-R-01',
            'status' => 'active',
            'start_date' => now()->subMonths(11)->toDateString(),
            'end_date' => now()->addDays(15)->toDateString(),
            'rent_amount' => 30000,
            'security_deposit' => 60000,
            'branch_id' => $this->branch->id,
        ]);

        // 1 Upcoming lease (31 - 60 days)
        TenancyAgreement::create([
            'property_id' => $prop->id,
            'code' => 'TNC-R-02',
            'status' => 'active',
            'start_date' => now()->subMonths(10)->toDateString(),
            'end_date' => now()->addDays(45)->toDateString(),
            'rent_amount' => 40000,
            'security_deposit' => 80000,
            'branch_id' => $this->branch->id,
        ]);

        // 1 Pipeline lease (61 - 90 days)
        TenancyAgreement::create([
            'property_id' => $prop->id,
            'code' => 'TNC-R-03',
            'status' => 'active',
            'start_date' => now()->subMonths(9)->toDateString(),
            'end_date' => now()->addDays(75)->toDateString(),
            'rent_amount' => 50000,
            'security_deposit' => 100000,
            'branch_id' => $this->branch->id,
        ]);

        // 1 Distant lease (150 days - should not be in <90d pipeline)
        TenancyAgreement::create([
            'property_id' => $prop->id,
            'code' => 'TNC-R-04',
            'status' => 'active',
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->addDays(150)->toDateString(),
            'rent_amount' => 60000,
            'security_deposit' => 120000,
            'branch_id' => $this->branch->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(OperationsDashboard::class);

        $counts = $component->instance()->getTabCounts();
        $this->assertArrayHasKey('renewals', $counts);
        $this->assertEquals(3, $counts['renewals']);

        $renewals = $component->instance()->getRenewalsData();
        $this->assertEquals(3, $renewals['total_expiring']);
        $this->assertEquals(1, $renewals['critical_count']);
        $this->assertEquals(1, $renewals['upcoming_count']);
        $this->assertEquals(1, $renewals['pipeline_count']);
        $this->assertEquals(120000.0, $renewals['rent_at_risk']);
    }
}
