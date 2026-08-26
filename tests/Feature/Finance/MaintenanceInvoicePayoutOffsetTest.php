<?php

namespace Tests\Feature\Finance;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenancyRole;
use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Finance\Services\OwnerPayoutService;
use App\Domain\Finance\Services\RentBillingService;
use App\Domain\Maintenance\Actions\SettleMaintenanceInvoiceViaReserveAction;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Services\MaintenanceBillingService;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Enums\TransactionType;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\Organization;
use Tek2991\Accounting\Services\TransactionService;
use Tests\TestCase;

class MaintenanceInvoicePayoutOffsetTest extends TestCase
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
            'display_name' => 'Dr. Sharma (Owner)',
            'email' => 'sharma@example.com',
            'phone' => '9876543210',
        ]);
        $this->owner->ownerProfile()->create([]);
        $this->owner->individual()->create(['name' => 'Dr. Sharma', 'pan_number' => 'ABCDE1234F']);

        $this->tenant = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Rahul Verma (Tenant)',
            'email' => 'rahul@example.com',
            'phone' => '9876543211',
        ]);
        $this->tenant->tenantProfile()->create([]);
        $this->tenant->individual()->create(['name' => 'Rahul Verma', 'pan_number' => 'FGHIJ5678K']);

        $this->property = Property::create([
            'building_name' => 'Magnolia Residences 402',
            'code' => 'PROP-MAG-402',
            'status' => 'occupied',
        ]);

        $opp = \App\Domain\Opportunity\Models\Opportunity::create([
            'number' => 'OPP-2026-MAG',
            'title' => 'Magnolia Residences Lead',
            'status' => 'converted',
        ]);

        \App\Domain\Mou\Models\Mou::create([
            'number' => 'MOU-2026-MAG',
            'property_id' => $this->property->id,
            'opportunity_id' => $opp->id,
            'party_id' => $this->owner->id,
            'type' => 'onboarding',
            'status' => 'verified',
        ]);

        $this->agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-MAG',
            'rent_amount' => 30000.00,
            'security_deposit' => 60000.00,
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
    }

    /**
     * Test 1: Maintenance invoice created on property is automatically detected in Owner Payout preview.
     */
    public function test_maintenance_invoice_detected_in_owner_payout_preview()
    {
        $maintRequest = MaintenanceRequest::create([
            'ticket_number' => 'TKT-2026-08-001',
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Geyser Thermostat Replacement',
            'category' => 'electrical',
            'priority' => 'medium',
            'status' => 'resolved',
            'owner_amount' => 2500.00,
            'total_cost' => 2500.00,
        ]);

        $billingService = app(MaintenanceBillingService::class);
        $invoice = $billingService->createMaintenanceInvoice($maintRequest, 'owner_invoice');

        $this->assertEquals(2500.00, $invoice->grand_total);
        $this->assertNotEquals(InvoiceStatus::Paid, $invoice->status);

        $payoutService = app(OwnerPayoutService::class);
        $details = $payoutService->calculatePayoutDetails($this->property, 8, 2026);

        $this->assertTrue($details['eligible']);
        $this->assertEquals(30000.00, $details['gross_rent']);
        $this->assertEquals(3000.00, $details['management_fee']);
        $this->assertEquals(2500.00, $details['maintenance_offset']);
        $this->assertEquals(2500.00, $details['advance_offset']);
        // Net payout: 30000 - 3000 (fee) - 2500 (maint offset) = 24500
        $this->assertEquals(24500.00, $details['net_payout']);
        $this->assertCount(1, $details['maintenance_invoices']);
        $this->assertEquals('TKT-2026-08-001', $details['maintenance_invoices'][0]['ticket_number']);
    }

    /**
     * Test 2: Bulk processing owner payouts automatically settles the maintenance invoice and marks it as Paid.
     */
    public function test_bulk_payout_execution_automatically_marks_maintenance_invoice_paid()
    {
        $maintRequest = MaintenanceRequest::create([
            'ticket_number' => 'TKT-2026-08-002',
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Kitchen Tap Plumbing Repair',
            'category' => 'plumbing',
            'priority' => 'low',
            'status' => 'resolved',
            'owner_amount' => 1500.00,
            'total_cost' => 1500.00,
        ]);

        $billingService = app(MaintenanceBillingService::class);
        $invoice = $billingService->createMaintenanceInvoice($maintRequest, 'owner_invoice');

        $this->assertNotEquals(InvoiceStatus::Paid, $invoice->status);

        $payoutService = app(OwnerPayoutService::class);
        $count = $payoutService->bulkProcessOwnerPayouts(8, 2026, $this->user);

        $this->assertEquals(1, $count);

        $payout = OwnerPayout::where('property_id', $this->property->id)->first();
        $this->assertNotNull($payout);
        $this->assertEquals(25500.00, $payout->amount); // 30000 - 3000 - 1500
        $this->assertEquals(1500.00, $payout->advance_offset);

        // Verify the maintenance invoice was automatically marked as Paid
        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertEquals(1500.00, $invoice->amount_paid);
        $this->assertEquals(0.00, $invoice->balance_due);
        $this->assertStringContainsString('Settled via Owner Payout', $invoice->notes);
    }

    /**
     * Test 3: Settle a maintenance invoice directly via Owner Maintenance Reserve Float drawdown.
     */
    public function test_settle_maintenance_invoice_via_reserve_drawdown()
    {
        $provisioning = app(AccountingProvisioningService::class);
        $reserveAccount = $provisioning->getOwnerReserveAccount($this->owner);

        // Pre-fund the owner's Maintenance Reserve with ₹5,000 float
        $bankAccount = \Tek2991\Accounting\Models\Account::where('type', 'asset')->where('system_role', \Tek2991\Accounting\Enums\SystemRole::Bank)->first()
            ?? \Tek2991\Accounting\Models\Account::where('type', 'asset')->first();

        app(TransactionService::class)->createTransaction([
            'branch_id' => $this->branch->id,
            'type' => TransactionType::Journal,
            'description' => "Owner Maintenance Reserve Deposit by {$this->owner->display_name}",
            'posted_at' => now()->toDateString(),
            'reference' => 'RES-DEP-001',
            'reviewed' => true,
            'pending' => false,
        ], [
            [
                'account_id' => $bankAccount->id,
                'type' => JournalEntryType::Debit,
                'amount' => 5000.00,
                'description' => 'Cash received for maintenance float',
            ],
            [
                'account_id' => $reserveAccount->id,
                'type' => JournalEntryType::Credit,
                'amount' => 5000.00,
                'description' => 'Owner Maintenance Reserve Liability',
            ],
        ]);

        $this->assertEquals(5000.00, $provisioning->getOwnerReserveBalance($this->owner));

        // Create a maintenance request & owner invoice for ₹2,000
        $maintRequest = MaintenanceRequest::create([
            'ticket_number' => 'TKT-2026-08-003',
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'AC Gas Refill',
            'category' => 'ac',
            'priority' => 'high',
            'status' => 'resolved',
            'owner_amount' => 2000.00,
            'total_cost' => 2000.00,
        ]);

        $billingService = app(MaintenanceBillingService::class);
        $invoice = $billingService->createMaintenanceInvoice($maintRequest, 'owner_invoice');

        // Settle via Reserve Action
        $settleAction = app(SettleMaintenanceInvoiceViaReserveAction::class);
        $settledInvoice = $settleAction->execute($invoice, $this->user);

        $this->assertEquals(InvoiceStatus::Paid, $settledInvoice->status);
        $this->assertEquals(2000.00, $settledInvoice->amount_paid);
        $this->assertEquals(0.00, $settledInvoice->balance_due);

        // Reserve balance should now be ₹3,000 (₹5,000 - ₹2,000)
        $this->assertEquals(3000.00, $provisioning->getOwnerReserveBalance($this->owner));
    }
}
