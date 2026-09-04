<?php

namespace Tests\Feature\Finance;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenancyRole;
use App\Domain\Finance\Actions\ProcessOwnerPayoutAction;
use App\Domain\Finance\Actions\RecordOwnerAdvanceAction;
use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Finance\Services\RentBillingService;
use App\Domain\Finance\Services\SecurityDepositService;
use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Models\MaintenanceVendorQuote;
use App\Domain\Maintenance\Services\MaintenanceBillingService;
use App\Domain\Party\Enums\BusinessRole;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\SystemRole;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Services\AccountService;
use Tests\TestCase;

class RealEstateFiduciaryAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $user;
    protected Party $owner;
    protected Party $tenant;
    protected Party $painter;
    protected Property $property;
    protected TenancyAgreement $agreement;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed Default Chart of Accounts
        $this->seed(DefaultChartOfAccountsSeeder::class);

        // 2. Setup Organization & Branch & User
        $org = \Tek2991\Accounting\Models\Organization::create([
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

        // 3. Create Parties
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
            'display_name' => 'Joshep (Tenant)',
            'email' => 'joshep@example.com',
            'phone' => '9876543211',
        ]);
        $this->tenant->tenantProfile()->create([]);
        $this->tenant->individual()->create(['name' => 'Joshep', 'pan_number' => 'FGHIJ5678K']);

        $tradeId = (string) \Illuminate\Support\Str::ulid();
        \Illuminate\Support\Facades\DB::table('vendor_trades')->insert([
            'id' => $tradeId,
            'name' => 'Painting',
            'slug' => 'painting',
        ]);

        $this->painter = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Painter Contractor',
            'email' => 'painter@example.com',
            'phone' => '9876543212',
        ]);
        $this->painter->vendorProfile()->create([
            'vendor_trade_id' => $tradeId,
        ]);

        // 4. Create Property & Link Owner via MOU
        $this->property = Property::create([
            'building_name' => 'Palm Heights Villa 101',
            'code' => 'PROP-101',
            'status' => 'occupied',
        ]);

        $opp = \App\Domain\Opportunity\Models\Opportunity::create([
            'number' => 'OPP-2026-001',
            'title' => 'Palm Heights Lead',
            'status' => 'converted',
        ]);

        \App\Domain\Mou\Models\Mou::create([
            'number' => 'MOU-2026-001',
            'property_id' => $this->property->id,
            'opportunity_id' => $opp->id,
            'party_id' => $this->owner->id,
            'type' => 'onboarding',
            'status' => 'verified',
        ]);

        // 5. Create Tenancy Agreement
        $this->agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-001',
            'rent_amount' => 10000.00,
            'security_deposit' => 100000.00,
            'status' => 'active',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        TenancyRole::create([
            'tenancy_agreement_id' => $this->agreement->id,
            'party_id' => $this->tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);
    }

    /**
     * Test 1: Monthly Rent Demand does NOT touch P&L (Zero P&L Bloating).
     * Billed: DR: Tenant AR (Joshep) ₹10,000, CR: Owner AP (Anthony) ₹10,000.
     */
    public function test_rent_invoice_generation_is_pure_pass_through_with_zero_revenue()
    {
        $rentBilling = app(RentBillingService::class);
        $accountService = app(AccountService::class);

        $invoice = $rentBilling->generateRentInvoice($this->agreement, 7, 2026);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(10000.00, $invoice->grand_total);
        $this->assertEquals('sent', $invoice->status->value);

        // Verify that P&L Revenue is exactly 0
        $totalRevenue = $accountService->getTypeTotal(AccountType::Revenue, '2026-07-01', '2026-07-31');
        $this->assertEquals(0, $totalRevenue->getAmount(), "P&L Revenue must be 0 for pass-through rent demand!");

        // Verify Journal Entries
        $transaction = $invoice->transaction;
        $this->assertNotNull($transaction);

        $entries = $transaction->journalEntries;
        $debitEntry = $entries->where('type.value', 'debit')->first();
        $creditEntry = $entries->where('type.value', 'credit')->first();

        // DR: Joshep AR (Tenant Receivable)
        $this->assertEquals(10000.00, $debitEntry->amount);
        $this->assertStringContainsString('Joshep', $debitEntry->account->name);

        // CR: Anthony AP (Owner Payable)
        $this->assertEquals(10000.00, $creditEntry->amount);
        $this->assertEquals(AccountType::Liability, $creditEntry->account->type);
        $this->assertStringContainsString('Anthony', $creditEntry->account->name);
    }

    /**
     * Test 2: Tenant Rent Payment records DR: Bank, CR: Tenant AR.
     */
    public function test_tenant_rent_payment_collection()
    {
        $rentBilling = app(RentBillingService::class);
        $invoice = $rentBilling->generateRentInvoice($this->agreement, 7, 2026);

        $bankAccount = Account::where('type', 'asset')
            ->whereIn('system_role', [SystemRole::Bank, SystemRole::Cash])
            ->first();

        $payment = $rentBilling->recordPayment(
            $invoice,
            10000.00,
            $bankAccount->id,
            '2026-07-04',
            'UTR12345678',
            'Rent received via IMPS'
        );

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status->value);
        $this->assertEquals(10000.00, $invoice->amount_paid);
        $this->assertEquals(0.00, $invoice->balance_due);

        // Payment transaction: DR: Bank, CR: Tenant AR
        $paymentTxn = $payment->transaction;
        $this->assertNotNull($paymentTxn);
        $this->assertEquals(10000.00, $paymentTxn->amount);
    }

    /**
     * Test 3: Owner Payout calculates 10% management fee, offsets advance, and recognizes true Commission Revenue.
     * Journal:
     * DR: Owner AP ₹10,000
     * CR: Commission Income ₹1,000 [Revenue]
     * CR: Bank ₹9,000 [Asset]
     */
    public function test_owner_payout_with_commission_and_advance_offset()
    {
        $rentBilling = app(RentBillingService::class);
        $payoutAction = app(ProcessOwnerPayoutAction::class);
        $advanceAction = app(RecordOwnerAdvanceAction::class);
        $accountService = app(AccountService::class);

        // 1. Advance Geyser purchase of ₹35,000 paid by Dwelly for Owner Ram/Anthony
        $bankAccount = Account::where('type', 'asset')->where('system_role', SystemRole::Bank)->first();
        $advanceAction->execute($this->owner, 35000.00, 'Geyser Purchase', $bankAccount, '2026-07-01');

        // 2. Tenant pays ₹20,000 rent
        $rentBilling->generateRentInvoice($this->agreement, 7, 2026, ['rent_amount' => 20000.00]);

        // 3. Process Owner Payout:
        // Rent Collected: ₹20,000
        // Management Fee (10%): ₹2,000 (P&L Income)
        // Advance Offset (Deduction for Geyser): ₹5,000
        // Net Cash to Owner: ₹13,000
        $payout = $payoutAction->execute(
            $this->property,
            '2026-07-01',
            '2026-07-31',
            $this->user,
            [
                'rent_collected' => 20000.00,
                'management_fee_percent' => 10.00,
                'advance_offset' => 5000.00,
                'bank_account_id' => $bankAccount->id,
            ]
        );

        $this->assertEquals(20000.00, $payout->rent_collected);
        $this->assertEquals(2000.00, $payout->management_fee);
        $this->assertEquals(5000.00, $payout->advance_offset);
        $this->assertEquals(13000.00, $payout->amount);
        $this->assertEquals('completed', $payout->status);

        // Verify Commission Sales Invoice is created for the Owner
        $this->assertNotNull($payout->commission_invoice_id, "Owner payout must generate a linked commission invoice.");
        $commInvoice = $payout->commissionInvoice;
        $this->assertNotNull($commInvoice);
        $this->assertEquals(2000.00, (float) $commInvoice->grand_total);
        $this->assertEquals(\Tek2991\Accounting\Enums\InvoiceStatus::Paid, $commInvoice->status);

        // Verify Document Snapshot and Immutable PDF
        $this->assertTrue($payout->hasStoredPdf(), "Owner Payout Statement PDF must be generated and stored on disk.");
        $this->assertNotNull($payout->pdf_checksum);
        $this->assertEquals(64, strlen($payout->pdf_checksum));
        $this->assertIsArray($payout->document_snapshot);
        $this->assertEquals('owner_payout_statement', $payout->document_snapshot['document_type']);
        $this->assertEquals(2000.00, $payout->document_snapshot['commission_invoice']['amount']);

        // Verify Payout PDF Route
        $response = $this->actingAs($this->user)->get(route('billing.payout.pdf', ['payout' => $payout]));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // Verify P&L Revenue is ONLY the ₹2,000 Commission Fee!
        $totalRevenue = $accountService->getTypeTotal(AccountType::Revenue, '2026-07-01', '2026-07-31');
        $this->assertEquals(2000.00, $totalRevenue->getDecimal(), "P&L Revenue must strictly reflect the ₹2,000 commission fee!");
    }

    /**
     * Test 4: Maintenance Workflow (Margin-Based P&L).
     * Owner Invoiced ₹35k (Maintenance Income), Vendor Billed ₹15k (Maintenance Expense).
     * Net Margin on P&L = ₹20,000.
     */
    public function test_maintenance_service_margin_accounting()
    {
        $maintBilling = app(MaintenanceBillingService::class);
        $accountService = app(AccountService::class);

        $maintRequest = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'ticket_number' => 'TCK-2026-001',
            'title' => 'Villa Painting & Waterproofing',
            'status' => 'work_completed',
            'total_cost' => 35000.00,
            'vendor_cost' => 15000.00,
            'vendor_party_id' => $this->painter->id,
        ]);

        $clientQuote = MaintenanceClientQuote::create([
            'maintenance_request_id' => $maintRequest->id,
            'quote_number' => 'QT-001',
            'total_amount' => 35000.00,
            'owner_amount' => 35000.00,
            'subtotal_amount' => 15000.00,
            'status' => 'approved',
        ]);
        $clientQuote->items()->create([
            'description' => 'Wall Painting & Waterproofing',
            'quantity' => 1,
            'unit_price' => 35000.00,
            'total_price' => 35000.00,
        ]);

        $vendorQuote = MaintenanceVendorQuote::create([
            'maintenance_request_id' => $maintRequest->id,
            'vendor_party_id' => $this->painter->id,
            'trade_title' => 'Painting Services',
            'quoted_cost' => 15000.00,
            'is_awarded' => true,
            'status' => 'awarded',
        ]);

        // 1. Generate Client Invoice to Owner (created as Draft by Operations)
        $invoice = $maintBilling->createMaintenanceInvoice($maintRequest, 'owner_invoice');
        $this->assertEquals(35000.00, $invoice->grand_total);
        $this->assertEquals('draft', $invoice->status->value);

        // 2. Generate Vendor Bill for Painter (created as Draft by Operations)
        $bill = $maintBilling->createVendorBillForQuote($vendorQuote);
        $this->assertEquals(15000.00, $bill->grand_total);
        $this->assertEquals('draft', $bill->status->value);

        // 3. Finance / Accountant reviews and posts both documents to GL
        app(\Tek2991\Accounting\Services\InvoiceService::class)->post($invoice);
        app(\Tek2991\Accounting\Services\BillService::class)->post($bill);

        $this->assertEquals('sent', $invoice->refresh()->status->value);
        $this->assertEquals('received', $bill->refresh()->status->value);

        // Verify P&L
        $revenue = $accountService->getTypeTotal(AccountType::Revenue, '2026-01-01', '2026-12-31');
        $expense = $accountService->getTypeTotal(AccountType::Expense, '2026-01-01', '2026-12-31');

        $this->assertEquals(35000.00, $revenue->getDecimal());
        $this->assertEquals(15000.00, $expense->getDecimal());
        $this->assertEquals(20000.00, $revenue->getDecimal() - $expense->getDecimal(), "Maintenance Net Margin must be ₹20,000!");
    }

    /**
     * Test 5: Security Deposit Lifecycle (Receipt, Placement to Owner & FD, and Move-out Settlement).
     */
    public function test_security_deposit_collection_placement_and_settlement()
    {
        $depositService = app(SecurityDepositService::class);
        $bankAccount = Account::where('type', 'asset')->where('system_role', SystemRole::Bank)->first();

        // 1. Tenant pays ₹100,000 Security Deposit
        $txnReceipt = $depositService->recordDepositReceipt($this->agreement, 100000.00, $bankAccount);
        $this->assertEquals(100000.00, $txnReceipt->amount);

        // 2. Deposit Placement: ₹50,000 transferred to Owner Anthony, ₹50,000 placed in FD Escrow
        $txnPlacement = $depositService->recordDepositPlacement($this->agreement, 50000.00, 50000.00, $bankAccount);
        $this->assertEquals(100000.00, $txnPlacement->amount);

        // 3. Move-out Settlement with ₹35,000 Damage Deduction for Painter
        // Deductions: ₹35,000 for Painter
        // Refund: ₹50,000 from FD + ₹15,000 from Owner = ₹65,000
        $settlementTxns = $depositService->recordDepositSettlement(
            $this->agreement,
            35000.00,
            $this->painter,
            50000.00,
            15000.00
        );

        $this->assertCount(3, $settlementTxns);
    }
}
