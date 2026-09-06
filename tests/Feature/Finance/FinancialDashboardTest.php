<?php

namespace Tests\Feature\Finance;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Pages\Billing\FinancialDashboard;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class FinancialDashboardTest extends TestCase
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
            'name' => 'Finance HQ',
            'code' => 'FIN-01',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->branches()->attach($this->branch->id);
    }

    public function test_financial_dashboard_page_loads(): void
    {
        Livewire::actingAs($this->user)
            ->test(FinancialDashboard::class)
            ->assertSuccessful()
            ->assertSet('activeTab', 'overview');
    }

    public function test_tab_switching_works_smoothly(): void
    {
        Livewire::actingAs($this->user)
            ->test(FinancialDashboard::class)
            ->call('setTab', 'rent_aging')
            ->assertSet('activeTab', 'rent_aging')
            ->call('setTab', 'payouts')
            ->assertSet('activeTab', 'payouts')
            ->call('setTab', 'deposits')
            ->assertSet('activeTab', 'deposits')
            ->call('setTab', 'maintenance')
            ->assertSet('activeTab', 'maintenance')
            ->call('setTab', 'overview')
            ->assertSet('activeTab', 'overview');
    }

    public function test_ar_aging_buckets_are_calculated_accurately(): void
    {
        $contact = Contact::create([
            'name' => 'Vikram Seth',
            'email' => 'vikram@example.com',
            'type' => 'customer',
        ]);

        // 1. Current invoice (due today)
        Invoice::create([
            'invoice_number' => 'INV-CUR-01',
            'contact_id' => $contact->id,
            'status' => InvoiceStatus::Sent,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'grand_total' => 50000,
            'amount_paid' => 0,
            'balance_due' => 50000,
            'branch_id' => $this->branch->id,
        ]);

        // 2. Overdue 45 days (bucket 31-60)
        Invoice::create([
            'invoice_number' => 'INV-OVD-45',
            'contact_id' => $contact->id,
            'status' => InvoiceStatus::Sent,
            'issue_date' => now()->subDays(60)->toDateString(),
            'due_date' => now()->subDays(45)->toDateString(),
            'grand_total' => 30000,
            'amount_paid' => 0,
            'balance_due' => 30000,
            'branch_id' => $this->branch->id,
        ]);

        // 3. Overdue 100 days (bucket 90+)
        Invoice::create([
            'invoice_number' => 'INV-OVD-100',
            'contact_id' => $contact->id,
            'status' => InvoiceStatus::Sent,
            'issue_date' => now()->subDays(120)->toDateString(),
            'due_date' => now()->subDays(100)->toDateString(),
            'grand_total' => 20000,
            'amount_paid' => 0,
            'balance_due' => 20000,
            'branch_id' => $this->branch->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(FinancialDashboard::class);

        $aging = $component->instance()->getRentAgingData();
        $this->assertEquals(100000.0, $aging['total_receivable']);
        $this->assertEquals(50000.0, $aging['bucket_current']);
        $this->assertEquals(30000.0, $aging['bucket_31_60']);
        $this->assertEquals(20000.0, $aging['bucket_90_plus']);
        $this->assertCount(3, $aging['delinquent_invoices']);
    }

    public function test_payouts_waterfall_and_summary_are_computed(): void
    {
        $property = Property::create([
            'building_name' => 'Palm Grove Estate',
            'code' => 'PROP-PGE',
            'status' => 'Occupied',
            'branch_id' => $this->branch->id,
        ]);

        $owner = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Suresh Raina (Owner)',
        ]);

        OwnerPayout::create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'rent_collected' => 100000,
            'management_fee' => 10000,
            'amount' => 90000,
            'status' => 'completed',
            'processed_at' => now(),
            'branch_id' => $this->branch->id,
        ]);

        OwnerPayout::create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'rent_collected' => 50000,
            'management_fee' => 5000,
            'amount' => 45000,
            'status' => 'draft',
            'branch_id' => $this->branch->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(FinancialDashboard::class);

        $payouts = $component->instance()->getPayoutsData();
        $this->assertEquals(100000.0, $payouts['gross_rent']);
        $this->assertEquals(10000.0, $payouts['management_fees']);
        $this->assertEquals(90000.0, $payouts['net_disbursed']);
        $this->assertEquals(45000.0, $payouts['pending_amount']);
        $this->assertEquals(1, $payouts['pending_count']);
    }

    public function test_deposits_data_tracks_active_custody_balance(): void
    {
        $property = Property::create([
            'building_name' => 'Magnolia Manor',
            'code' => 'PROP-MM1',
            'status' => 'Occupied',
            'branch_id' => $this->branch->id,
        ]);

        TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'AGR-MAG-01',
            'status' => 'active',
            'rent_amount' => 40000,
            'security_deposit' => 80000,
            'branch_id' => $this->branch->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(FinancialDashboard::class);

        $dep = $component->instance()->getDepositsData();
        $this->assertEquals(80000.0, $dep['active_deposits_total']);
        $this->assertEquals(1, $dep['active_deposits_count']);
    }
}
