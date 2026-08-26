<?php

namespace Tests\Feature\Finance;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenancyRole;
use App\Domain\Finance\Services\RentBillingService;
use App\Domain\Finance\Services\SecurityDepositService;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Pages\Billing\FinancialOperationsHub;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class FinancialOperationsHubTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $user;
    protected Party $owner;
    protected Party $tenant;
    protected Property $property;
    protected TenancyAgreement $agreement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DefaultChartOfAccountsSeeder::class);

        $org = Organization::create([
            'name' => 'Dwelly Living Private Limited',
            'legal_name' => 'Dwelly Living Private Limited',
        ]);

        $this->branch = Branch::create([
            'organization_id' => $org->id,
            'name' => 'Main HQ Branch',
            'code' => 'HQ-01',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->owner = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Anthony (Owner)',
            'email' => 'anthony@example.com',
            'phone' => '9876543210',
        ]);
        $this->owner->ownerProfile()->create([]);
        $this->owner->individual()->create(['name' => 'Anthony', 'pan_number' => 'ABCDE1234F']);

        $this->tenant = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Bruce Wayne',
            'email' => 'bruce@wayne.corp',
            'phone' => '9123456789',
        ]);
        $this->tenant->tenantProfile()->create([]);
        $this->tenant->individual()->create(['name' => 'Bruce', 'pan_number' => 'XYZPW1234K']);

        $this->property = Property::create([
            'code' => 'PROP-GOTHAM-01',
            'building_name' => 'Wayne Manor Suite',
            'address_line_1' => '1007 Mountain Drive',
            'city' => 'Gotham',
            'status' => 'occupied',
        ]);

        $opp = \App\Domain\Opportunity\Models\Opportunity::create([
            'number' => 'OPP-GOTHAM-001',
            'title' => 'Wayne Manor Lead',
            'status' => 'converted',
        ]);

        \App\Domain\Mou\Models\Mou::create([
            'number' => 'MOU-GOTHAM-001',
            'property_id' => $this->property->id,
            'opportunity_id' => $opp->id,
            'party_id' => $this->owner->id,
            'type' => 'onboarding',
            'status' => 'verified',
        ]);

        $this->agreement = TenancyAgreement::create([
            'code' => 'AGR-2026-001',
            'property_id' => $this->property->id,
            'security_deposit' => 60000.00,
            'rent_amount' => 20000.00,
            'status' => 'active',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        TenancyRole::create([
            'tenancy_agreement_id' => $this->agreement->id,
            'party_id' => $this->tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);
    }

    public function test_financial_operations_hub_renders_successfully(): void
    {
        Livewire::test(FinancialOperationsHub::class)
            ->assertSuccessful()
            ->assertSee('Financial Operations & Collections Hub')
            ->assertSee('Security Deposits')
            ->assertSee('Maintenance Invoices')
            ->assertSee('Overdue Rent')
            ->assertSee('Vendor Payables')
            ->assertSee('Owner Advances');
    }

    public function test_tab_switching_works(): void
    {
        Livewire::test(FinancialOperationsHub::class)
            ->assertSet('activeTab', 'security_deposits')
            ->call('setTab', 'maintenance_invoices')
            ->assertSet('activeTab', 'maintenance_invoices')
            ->call('setTab', 'rent_invoices')
            ->assertSet('activeTab', 'rent_invoices');
    }

    public function test_metrics_and_counts_calculate_correctly(): void
    {
        Invoice::create([
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-RENT-001',
            'reference_type' => TenancyAgreement::class,
            'reference_id' => $this->agreement->id,
            'status' => InvoiceStatus::Sent,
            'grand_total' => 20000.00,
            'amount_paid' => 5000.00,
            'balance_due' => 15000.00,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-05',
        ]);

        $component = Livewire::test(FinancialOperationsHub::class);
        
        $metrics = $component->instance()->getMetrics();
        $this->assertEquals(15000.00, $metrics['total_rent_due']);
        $this->assertEquals(60000.00, $metrics['total_active_deposits']);
    }

    public function test_record_deposit_receipt_action(): void
    {
        $bankAccount = Account::where('type', 'asset')->first();

        Livewire::test(FinancialOperationsHub::class)
            ->callAction('recordDepositReceipt', [
                'tenancy_agreement_id' => $this->agreement->id,
                'amount' => 60000.00,
                'bank_account_id' => $bankAccount->id,
                'payment_date' => '2026-08-26',
                'reference' => 'UTR-DEP-TEST-001',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas((new \Tek2991\Accounting\Models\Transaction)->getTable(), [
            'reference' => 'UTR-DEP-TEST-001',
        ]);
    }

    public function test_record_deposit_placement_action(): void
    {
        $bankAccount = Account::where('type', 'asset')->first();

        Livewire::test(FinancialOperationsHub::class)
            ->callAction('recordDepositPlacement', [
                'tenancy_agreement_id' => $this->agreement->id,
                'owner_amount' => 40000.00,
                'fd_amount' => 20000.00,
                'bank_account_id' => $bankAccount->id,
                'placement_date' => '2026-08-26',
                'reference' => 'UTR-FD-PLACE-001',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas((new \Tek2991\Accounting\Models\Transaction)->getTable(), [
            'reference' => 'UTR-FD-PLACE-001',
        ]);
    }

    public function test_record_deposit_settlement_action(): void
    {
        Livewire::test(FinancialOperationsHub::class)
            ->callAction('recordDepositSettlement', [
                'tenancy_agreement_id' => $this->agreement->id,
                'deduction_amount' => 5000.00,
                'fd_liquidation' => 20000.00,
                'owner_refund' => 35000.00,
                'settlement_date' => '2026-08-26',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas((new \Tek2991\Accounting\Models\Transaction)->getTable(), [
            'reference' => "DEP-DEDUCT-{$this->agreement->id}",
        ]);
    }

    public function test_record_invoice_payment_action(): void
    {
        $invoice = Invoice::create([
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-MAINT-001',
            'reference_type' => \App\Domain\Maintenance\Models\MaintenanceRequest::class,
            'reference_id' => '01m0z1234567890abcdefghijk',
            'status' => InvoiceStatus::Sent,
            'grand_total' => 5000.00,
            'amount_paid' => 0.00,
            'balance_due' => 5000.00,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-10',
            'notes' => 'Maintenance Painting Invoice',
        ]);

        $bankAccount = Account::where('type', 'asset')->first();

        Livewire::test(FinancialOperationsHub::class)
            ->callAction('recordInvoicePayment', [
                'invoice_id' => $invoice->id,
                'amount' => 5000.00,
                'bank_account_id' => $bankAccount->id,
                'payment_date' => '2026-08-26',
                'reference' => 'UTR-INV-PAY-001',
                'notes' => 'Full payment received',
            ])
            ->assertHasNoActionErrors();

        $invoice->refresh();
        $this->assertEquals(0.00, $invoice->balance_due);
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_subfilter_and_search_in_deposits(): void
    {
        $component = Livewire::test(FinancialOperationsHub::class)
            ->set('search', 'Wayne')
            ->assertSee('Wayne Manor Suite')
            ->set('depositSubFilter', 'pending_collection')
            ->assertSee('Pending Collection');

        $this->assertCount(1, $component->instance()->getSecurityDeposits());

        $component->set('search', 'NonExistentXYZ');
        $this->assertCount(0, $component->instance()->getSecurityDeposits());
    }
}
