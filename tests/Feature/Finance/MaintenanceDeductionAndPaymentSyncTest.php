<?php

namespace Tests\Feature\Finance;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenancyRole;
use App\Domain\Finance\Actions\ProcessOwnerPayoutAction;
use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Finance\Services\OwnerPayoutService;
use App\Domain\Finance\Services\RentBillingService;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Services\MaintenanceBillingService;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\Organization;
use Tek2991\Accounting\Services\InvoiceService;
use Tests\TestCase;

class MaintenanceDeductionAndPaymentSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $user;
    protected Party $owner;
    protected Party $tenant;
    protected Property $property;
    protected TenancyAgreement $agreement;
    protected OwnerPayoutService $payoutService;
    protected RentBillingService $rentService;
    protected MaintenanceBillingService $maintBillingService;
    protected InvoiceService $invoiceService;

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
            'display_name' => 'Amitabh Bachchan (Owner)',
            'email' => 'amitabh@example.com',
            'phone' => '9876543210',
        ]);
        $this->owner->ownerProfile()->create([]);
        $this->owner->individual()->create(['name' => 'Amitabh Bachchan', 'pan_number' => 'ABCDE1234F']);

        $this->tenant = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Abhishek Bachchan (Tenant)',
            'email' => 'abhishek@example.com',
            'phone' => '9876543211',
        ]);
        $this->tenant->tenantProfile()->create([]);
        $this->tenant->individual()->create(['name' => 'Abhishek Bachchan', 'pan_number' => 'FGHIJ5678K']);

        $this->property = Property::create([
            'building_name' => 'Jalsa Premier 101',
            'code' => 'PROP-JALSA-101',
            'status' => 'occupied',
        ]);

        $opp = Opportunity::create([
            'number' => 'OPP-2026-JALSA',
            'title' => 'Jalsa Premier Deal',
            'status' => 'converted',
        ]);

        Mou::create([
            'number' => 'MOU-2026-JALSA',
            'property_id' => $this->property->id,
            'opportunity_id' => $opp->id,
            'party_id' => $this->owner->id,
            'type' => 'onboarding',
            'status' => 'verified',
        ]);

        $this->agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-JALSA',
            'rent_amount' => 40000.00,
            'security_deposit' => 80000.00,
            'status' => 'active',
            'start_date' => '2026-08-01',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-08-01',
        ]);

        TenancyRole::create([
            'tenancy_agreement_id' => $this->agreement->id,
            'party_id' => $this->tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        $this->payoutService = app(OwnerPayoutService::class);
        $this->rentService = app(RentBillingService::class);
        $this->maintBillingService = app(MaintenanceBillingService::class);
        $this->invoiceService = app(InvoiceService::class);
    }

    /**
     * Test 1: Owner maintenance already paid before payout generation is NOT deducted.
     */
    public function test_owner_maintenance_paid_before_payout_generation_is_not_deducted(): void
    {
        $maintReq = MaintenanceRequest::create([
            'ticket_number' => 'TKT-2026-OWNER-PAID',
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'title' => 'Balcony Waterproofing',
            'status' => 'resolved',
            'owner_amount' => 4000.00,
            'total_cost' => 4000.00,
        ]);

        $maintInvoice = $this->maintBillingService->createMaintenanceInvoice($maintReq, 'owner_invoice');
        $this->assertEquals(InvoiceStatus::Draft, $maintInvoice->status);

        // Post maintenance invoice to Sent so it can receive payments
        $this->invoiceService->post($maintInvoice);

        // Owner pays the maintenance invoice independently before payout generation
        $bankAccount = Account::where('type', 'asset')->first();
        $this->invoiceService->recordPayment($maintInvoice, [
            'amount' => 4000.00,
            'payment_account_id' => $bankAccount->id,
            'payment_date' => now()->toDateString(),
            'notes' => 'Paid directly by Owner via UPI',
        ]);

        $maintInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $maintInvoice->status);
        $this->assertEquals(0.0, (float) $maintInvoice->balance_due);

        // Now calculate/preview Owner Payout for month 8
        $details = $this->payoutService->calculatePayoutDetails($this->property, 8, 2026);

        // Should NOT deduct the paid maintenance invoice
        $this->assertEquals(0.0, $details['maintenance_offset']);
        $this->assertEquals(0.0, $details['advance_offset']);
        $this->assertEmpty($details['maintenance_invoices']);

        // Net payout: Gross 40000 - 10% (4000) = 36000
        $this->assertEquals(36000.00, $details['net_payout']);
    }

    /**
     * Test 2: Unpaid owner maintenance is deducted from payout and settled together when payout is disbursed.
     */
    public function test_owner_maintenance_unpaid_is_deducted_and_settles_upon_payout_disbursement(): void
    {
        $maintReq = MaintenanceRequest::create([
            'ticket_number' => 'TKT-2026-OWNER-UNPAID',
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'title' => 'AC Compressor Repair',
            'status' => 'resolved',
            'owner_amount' => 3500.00,
            'total_cost' => 3500.00,
        ]);

        $maintInvoice = $this->maintBillingService->createMaintenanceInvoice($maintReq, 'owner_invoice');
        $this->assertNotEquals(InvoiceStatus::Paid, $maintInvoice->status);

        // Calculate payout: should deduct 3500
        $details = $this->payoutService->calculatePayoutDetails($this->property, 8, 2026);
        $this->assertEquals(3500.00, $details['maintenance_offset']);
        $this->assertEquals(3500.00, $details['advance_offset']);
        // Net payout: 40000 - 4000 (fee) - 3500 (maint) = 32500
        $this->assertEquals(32500.00, $details['net_payout']);

        // Disburse the payout
        $payout = app(ProcessOwnerPayoutAction::class)->execute(
            $this->property,
            '2026-08-01',
            '2026-08-31',
            $this->user,
            [
                'rent_collected' => 40000.00,
                'advance_offset' => 3500.00,
                'maintenance_invoice_ids' => [(int) $maintInvoice->id],
            ]
        );

        $this->assertEquals('completed', $payout->status);
        $this->assertEquals(32500.00, (float) $payout->amount);

        // Verify maintenance invoice was settled as Paid synchronously with payout
        $maintInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $maintInvoice->status);
        $this->assertEquals(0.0, (float) $maintInvoice->balance_due);
        $this->assertStringContainsString('Settled via Owner Payout', $maintInvoice->notes);
    }

    /**
     * Test 3: Owner maintenance paid independently AFTER payout generation skips duplicate settlement upon disbursement.
     */
    public function test_owner_maintenance_paid_independently_after_payout_generation_skips_duplicate_settlement(): void
    {
        $maintReq = MaintenanceRequest::create([
            'ticket_number' => 'TKT-2026-OWNER-INTERIM',
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'title' => 'Plumbing Overhaul',
            'status' => 'resolved',
            'owner_amount' => 2000.00,
            'total_cost' => 2000.00,
        ]);

        $maintInvoice = $this->maintBillingService->createMaintenanceInvoice($maintReq, 'owner_invoice');
        $this->invoiceService->post($maintInvoice);

        // Payout is drafted with maintenance deduction
        $draft = $this->payoutService->saveDraftPayout($this->property, 8, 2026, [
            'rent_collected' => 40000.00,
            'advance_offset' => 2000.00,
            'selected_maintenance_invoice_ids' => [(int) $maintInvoice->id],
        ], $this->user);

        $this->assertNotNull($draft);

        // Owner pays the maintenance invoice independently in the interim
        $bankAccount = Account::where('type', 'asset')->first();
        $this->invoiceService->recordPayment($maintInvoice, [
            'amount' => 2000.00,
            'payment_account_id' => $bankAccount->id,
            'payment_date' => now()->toDateString(),
            'notes' => 'Independent owner payment via NetBanking',
        ]);
        $maintInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $maintInvoice->status);

        // Now disburse payout
        $payout = app(ProcessOwnerPayoutAction::class)->execute(
            $this->property,
            '2026-08-01',
            '2026-08-31',
            $this->user,
            [
                'draft_payout_id' => $draft->id,
                'rent_collected' => 40000.00,
                'advance_offset' => 2000.00,
                'maintenance_invoice_ids' => [(int) $maintInvoice->id],
            ]
        );

        $this->assertEquals('completed', $payout->status);

        // Maintenance invoice remains cleanly Paid without errors or double settlement notes
        $maintInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $maintInvoice->status);
        $this->assertEquals(0.0, (float) $maintInvoice->balance_due);
    }

    /**
     * Test 4: Tenant maintenance already paid before rent demand generation is NOT bundled into demand.
     */
    public function test_tenant_maintenance_paid_before_rent_demand_is_not_bundled(): void
    {
        $maintReq = MaintenanceRequest::create([
            'ticket_number' => 'TKT-2026-TENANT-PAID',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Window Mesh Replacement',
            'status' => 'resolved',
            'tenant_amount' => 1500.00,
            'total_cost' => 1500.00,
        ]);

        $maintInvoice = $this->maintBillingService->createMaintenanceInvoice($maintReq, 'tenant_invoice');
        $this->invoiceService->post($maintInvoice);

        // Tenant pays independently before rent demand is generated
        $bankAccount = Account::where('type', 'asset')->first();
        $this->invoiceService->recordPayment($maintInvoice, [
            'amount' => 1500.00,
            'payment_account_id' => $bankAccount->id,
            'payment_date' => now()->toDateString(),
            'notes' => 'Paid independently by Tenant',
        ]);
        $maintInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $maintInvoice->status);

        // Generate Rent Demand
        $demand = $this->rentService->generateRentDemand($this->agreement, 8, 2026);

        // Rent demand should NOT include maintenance recovery
        $this->assertEquals(40000.00, (float) $demand->grand_total);
        $maintLine = $demand->items->first(fn ($li) => str_contains($li->description, 'TKT-2026-TENANT-PAID'));
        $this->assertNull($maintLine);
    }

    /**
     * Test 5: Unpaid tenant maintenance is bundled into rent demand but remains OPEN until demand payment is recorded.
     */
    public function test_tenant_maintenance_unpaid_remains_open_upon_rent_demand_generation(): void
    {
        $maintReq = MaintenanceRequest::create([
            'ticket_number' => 'TKT-2026-TENANT-OPEN',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Geyser Thermostat',
            'status' => 'resolved',
            'tenant_amount' => 1800.00,
            'total_cost' => 1800.00,
        ]);

        $maintInvoice = $this->maintBillingService->createMaintenanceInvoice($maintReq, 'tenant_invoice');
        $this->assertNotEquals(InvoiceStatus::Paid, $maintInvoice->status);

        // Generate Rent Demand
        $demand = $this->rentService->generateRentDemand($this->agreement, 8, 2026);

        // Total demand includes maintenance: 40000 + 1800 = 41800
        $this->assertEquals(41800.00, (float) $demand->grand_total);
        $this->assertEquals(InvoiceStatus::Sent, $demand->status);

        // Crucial Check: Underlying maintenance invoice MUST remain open (NOT yet Paid!)
        $maintInvoice->refresh();
        $this->assertNotEquals(InvoiceStatus::Paid, $maintInvoice->status);
        $this->assertEquals(1800.00, (float) $maintInvoice->balance_due);
        $this->assertStringContainsString("Consolidated into Rent Demand #{$demand->invoice_number}", $maintInvoice->notes);
    }

    /**
     * Test 6: Recording payment against Rent Demand settles both Rent Demand and Maintenance Invoice together.
     */
    public function test_tenant_maintenance_settles_together_when_rent_demand_payment_recorded(): void
    {
        $maintReq = MaintenanceRequest::create([
            'ticket_number' => 'TKT-2026-TENANT-SYNC',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Kitchen Sink Trap Repair',
            'status' => 'resolved',
            'tenant_amount' => 2200.00,
            'total_cost' => 2200.00,
        ]);

        $maintInvoice = $this->maintBillingService->createMaintenanceInvoice($maintReq, 'tenant_invoice');

        // Generate Demand: 40000 rent + 2200 maint = 42200
        $demand = $this->rentService->generateRentDemand($this->agreement, 8, 2026);
        $this->assertEquals(42200.00, (float) $demand->grand_total);

        // Maintenance invoice remains open prior to payment
        $maintInvoice->refresh();
        $this->assertNotEquals(InvoiceStatus::Paid, $maintInvoice->status);

        // Record full payment on Rent Demand
        $this->rentService->recordPayment($demand, 42200.00);

        // Verify Rent Demand is Paid
        $demand->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $demand->status);
        $this->assertEquals(0.0, (float) $demand->balance_due);

        // Verify Maintenance Invoice is ALSO settled as Paid synchronously
        $maintInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $maintInvoice->status);
        $this->assertEquals(0.0, (float) $maintInvoice->balance_due);
        $this->assertStringContainsString("Settled via Rent Demand #{$demand->invoice_number} Payment", $maintInvoice->notes);
    }

    /**
     * Test 7: Tenant maintenance paid independently after demand generation skips duplicate payment on demand payment.
     */
    public function test_tenant_maintenance_paid_independently_after_demand_generation_skips_duplicate_settlement(): void
    {
        $maintReq = MaintenanceRequest::create([
            'ticket_number' => 'TKT-2026-TENANT-INDEP',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Lock Replacement',
            'status' => 'resolved',
            'tenant_amount' => 1200.00,
            'total_cost' => 1200.00,
        ]);

        $maintInvoice = $this->maintBillingService->createMaintenanceInvoice($maintReq, 'tenant_invoice');
        $this->invoiceService->post($maintInvoice);

        // Generate Demand
        $demand = $this->rentService->generateRentDemand($this->agreement, 8, 2026);
        $this->assertEquals(41200.00, (float) $demand->grand_total);

        // Tenant pays the maintenance invoice independently at the maintenance desk
        $bankAccount = Account::where('type', 'asset')->first();
        $this->invoiceService->recordPayment($maintInvoice, [
            'amount' => 1200.00,
            'payment_account_id' => $bankAccount->id,
            'payment_date' => now()->toDateString(),
            'notes' => 'Paid independently at desk',
        ]);
        $maintInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $maintInvoice->status);

        // Now tenant pays the rent demand
        $this->rentService->recordPayment($demand, 41200.00);

        // Demand is Paid
        $demand->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $demand->status);

        // Maintenance invoice remains cleanly Paid without duplicate payments
        $maintInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $maintInvoice->status);
        $this->assertEquals(0.0, (float) $maintInvoice->balance_due);
    }

    /**
     * Test 8: Maintenance invoices can always be paid independently at any time.
     */
    public function test_maintenance_invoice_can_always_be_paid_independently_at_any_time(): void
    {
        $maintReq = MaintenanceRequest::create([
            'ticket_number' => 'TKT-2026-INDEPENDENT',
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'title' => 'General Housekeeping',
            'status' => 'resolved',
            'owner_amount' => 1000.00,
            'total_cost' => 1000.00,
        ]);

        $maintInvoice = $this->maintBillingService->createMaintenanceInvoice($maintReq, 'owner_invoice');
        $this->invoiceService->post($maintInvoice);
        $this->assertNotEquals(InvoiceStatus::Paid, $maintInvoice->status);

        $bankAccount = Account::where('type', 'asset')->first();
        $payment = $this->invoiceService->recordPayment($maintInvoice, [
            'amount' => 1000.00,
            'payment_account_id' => $bankAccount->id,
            'payment_date' => now()->toDateString(),
            'notes' => 'Independent direct payment',
        ]);

        $this->assertNotNull($payment);
        $maintInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $maintInvoice->status);
        $this->assertEquals(0.0, (float) $maintInvoice->balance_due);
    }
}
