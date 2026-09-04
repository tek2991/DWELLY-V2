<?php

namespace Tests\Feature\Finance;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenancyRole;
use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Finance\Services\OwnerPayoutService;
use App\Domain\Finance\Services\RentBillingService;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class OwnerPayoutBillingPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $user;
    protected Party $owner;
    protected Party $tenant;
    protected Property $property;

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
            'display_name' => 'Joshep (Tenant)',
            'email' => 'joshep@example.com',
            'phone' => '9876543211',
        ]);
        $this->tenant->tenantProfile()->create([]);
        $this->tenant->individual()->create(['name' => 'Joshep', 'pan_number' => 'FGHIJ5678K']);

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
    }

    /**
     * Test 1: First Month Owner Payout calculated from Key Handover Date with prorated gross rent.
     * Handover 15th August 2026 -> 17 days active -> Gross rent: ₹17,000, 10% Mgmt Fee: ₹1,700, Net Payout: ₹15,300.
     */
    public function test_first_month_owner_payout_proration_from_key_handover_date()
    {
        $agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-PO1',
            'rent_amount' => 31000.00,
            'security_deposit' => 60000.00,
            'status' => 'active',
            'start_date' => '2026-08-15',
            'end_date' => '2027-08-14',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-08-15 10:00:00',
        ]);
        TenancyRole::create(['tenancy_agreement_id' => $agreement->id, 'party_id' => $this->tenant->id, 'role_type' => 'Primary Tenant', 'is_primary' => true]);

        // Generate the rent invoice first
        app(RentBillingService::class)->generateRentInvoice($agreement, 8, 2026);

        $payoutService = app(OwnerPayoutService::class);
        $details = $payoutService->calculatePayoutDetails($this->property, 8, 2026);

        $this->assertTrue($details['eligible']);
        $this->assertTrue($details['is_first_month']);
        $this->assertTrue($details['is_prorated']);
        $this->assertEquals('2026-08-15', $details['billing_period_start']);
        $this->assertEquals('2026-08-31', $details['billing_period_end']);
        $this->assertEquals(17000.00, $details['gross_rent']);
        $this->assertEquals(1700.00, $details['management_fee']);
        $this->assertEquals(15300.00, $details['net_payout']);
    }

    /**
     * Test 2: Subsequent Month Owner Payout starts on 1st with full rent.
     */
    public function test_subsequent_month_owner_payout_starts_from_1st()
    {
        $agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-PO2',
            'rent_amount' => 20000.00,
            'security_deposit' => 40000.00,
            'status' => 'active',
            'start_date' => '2026-08-15',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-08-15',
        ]);
        TenancyRole::create(['tenancy_agreement_id' => $agreement->id, 'party_id' => $this->tenant->id, 'role_type' => 'Primary Tenant', 'is_primary' => true]);

        $payoutService = app(OwnerPayoutService::class);
        $details = $payoutService->calculatePayoutDetails($this->property, 9, 2026);

        $this->assertTrue($details['eligible']);
        $this->assertFalse($details['is_first_month']);
        $this->assertFalse($details['is_prorated']);
        $this->assertEquals('2026-09-01', $details['billing_period_start']);
        $this->assertEquals('2026-09-30', $details['billing_period_end']);
        $this->assertEquals(20000.00, $details['gross_rent']);
        $this->assertEquals(2000.00, $details['management_fee']);
        $this->assertEquals(18000.00, $details['net_payout']);
    }

    /**
     * Test 3: Bulk Owner Payouts Preview and Execution with Double-Entry Ledger.
     */
    public function test_bulk_owner_payouts_preview_and_execution()
    {
        $agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-PO3',
            'rent_amount' => 10000.00,
            'security_deposit' => 20000.00,
            'status' => 'active',
            'start_date' => '2026-08-01',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-08-01',
        ]);
        TenancyRole::create(['tenancy_agreement_id' => $agreement->id, 'party_id' => $this->tenant->id, 'role_type' => 'Primary Tenant', 'is_primary' => true]);

        $payoutService = app(OwnerPayoutService::class);

        // Preview
        $preview = $payoutService->getBulkPayoutPreview(8, 2026);
        $this->assertEquals(1, $preview['summary']['ready_count']);
        $this->assertEquals(9000.00, $preview['summary']['total_net_payout']);
        $this->assertEquals(1000.00, $preview['summary']['total_management_fee']);

        // Execute Bulk Payout
        $count = $payoutService->bulkProcessOwnerPayouts(8, 2026, $this->user);
        $this->assertEquals(1, $count);

        $payout = OwnerPayout::where('property_id', $this->property->id)->first();
        $this->assertNotNull($payout);
        $this->assertEquals(9000.00, $payout->amount);
        $this->assertEquals(1000.00, $payout->management_fee);
        $this->assertEquals('completed', $payout->status);
        $this->assertEquals('2026-08-01', $payout->period_start->toDateString());
        $this->assertEquals('2026-08-31', $payout->period_end->toDateString());

        // Verify Commission Sales Invoice
        $this->assertNotNull($payout->commission_invoice_id);
        $this->assertNotNull($payout->commissionInvoice);
        $this->assertEquals(1000.00, (float) $payout->commissionInvoice->grand_total);
        $this->assertEquals(\Tek2991\Accounting\Enums\InvoiceStatus::Paid, $payout->commissionInvoice->status);

        // Verify PDF and Snapshot Storage
        $this->assertTrue($payout->hasStoredPdf());
        $this->assertNotNull($payout->pdf_checksum);
        $this->assertEquals(64, strlen($payout->pdf_checksum));
        $this->assertIsArray($payout->document_snapshot);
        $this->assertEquals('owner_payout_statement', $payout->document_snapshot['document_type']);

        // Idempotency: re-running should find 0 ready
        $previewAfter = $payoutService->getBulkPayoutPreview(8, 2026);
        $this->assertEquals(0, $previewAfter['summary']['ready_count']);
        $this->assertEquals(1, $previewAfter['summary']['already_processed_count']);
    }
}
