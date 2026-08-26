<?php

namespace Tests\Feature\Finance;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenancyRole;
use App\Domain\Finance\Services\RentBillingService;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Models\Branch;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class RentBillingPeriodTest extends TestCase
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
     * Test 1: First Month Rent Billing starts from Key Handover Date to End of Month with Proration.
     * Handover on 15th August 2026 -> Period: 15 Aug 2026 to 31 Aug 2026 (17 days active).
     */
    public function test_first_month_billing_period_starts_from_key_handover_date_with_proration()
    {
        $agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-001',
            'rent_amount' => 31000.00,
            'security_deposit' => 60000.00,
            'status' => 'active',
            'start_date' => '2026-08-15',
            'end_date' => '2027-08-14',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-08-15 10:00:00',
        ]);

        TenancyRole::create([
            'tenancy_agreement_id' => $agreement->id,
            'party_id' => $this->tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        $rentBilling = app(RentBillingService::class);

        // Check calculation details for August 2026
        $calc = $rentBilling->calculateBillingDetails($agreement, 8, 2026);
        $this->assertTrue($calc['eligible']);
        $this->assertTrue($calc['is_first_month']);
        $this->assertTrue($calc['is_prorated']);
        $this->assertEquals('2026-08-15', $calc['billing_period_start']);
        $this->assertEquals('2026-08-31', $calc['billing_period_end']);
        $this->assertEquals(17, $calc['days_active']);
        $this->assertEquals(31, $calc['total_days_in_month']);
        // 31,000 / 31 * 17 = 17,000.00
        $this->assertEquals(17000.00, $calc['rent_amount']);

        // Generate invoice for August 2026
        $invoice = $rentBilling->generateRentInvoice($agreement, 8, 2026);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals('2026-08-15', $invoice->billing_period_start->toDateString());
        $this->assertEquals('2026-08-31', $invoice->billing_period_end->toDateString());
        $this->assertEquals('15 Aug 2026 – 31 Aug 2026', $invoice->billing_period_formatted);
        $this->assertEquals(17000.00, $invoice->grand_total);
        $this->assertStringContainsString('15 Aug 2026 – 31 Aug 2026', $invoice->notes);
        $this->assertStringContainsString('Prorated - 17 days', $invoice->items->first()->description);
    }

    /**
     * Test 2: Explicit first_month_rent on agreement takes precedence over formula.
     */
    public function test_explicit_first_month_rent_override_on_agreement()
    {
        $agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-002',
            'rent_amount' => 30000.00,
            'first_month_rent' => 15500.00, // Custom agreed prorated amount
            'security_deposit' => 60000.00,
            'status' => 'active',
            'start_date' => '2026-08-16',
            'end_date' => '2027-08-15',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-08-16',
        ]);

        TenancyRole::create([
            'tenancy_agreement_id' => $agreement->id,
            'party_id' => $this->tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        $rentBilling = app(RentBillingService::class);
        $calc = $rentBilling->calculateBillingDetails($agreement, 8, 2026);

        $this->assertEquals(15500.00, $calc['rent_amount']);

        $invoice = $rentBilling->generateRentInvoice($agreement, 8, 2026);
        $this->assertEquals(15500.00, $invoice->grand_total);
    }

    /**
     * Test 3: From next month onwards, billing period starts on the 1st of the month with full monthly rent.
     */
    public function test_subsequent_months_billing_period_starts_on_1st_with_full_rent()
    {
        $agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-003',
            'rent_amount' => 25000.00,
            'security_deposit' => 50000.00,
            'status' => 'active',
            'start_date' => '2026-08-15',
            'end_date' => '2027-08-14',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-08-15',
        ]);

        TenancyRole::create([
            'tenancy_agreement_id' => $agreement->id,
            'party_id' => $this->tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        $rentBilling = app(RentBillingService::class);

        // Next month: September 2026
        $calc = $rentBilling->calculateBillingDetails($agreement, 9, 2026);
        $this->assertTrue($calc['eligible']);
        $this->assertFalse($calc['is_first_month']);
        $this->assertFalse($calc['is_prorated']);
        $this->assertEquals('2026-09-01', $calc['billing_period_start']);
        $this->assertEquals('2026-09-30', $calc['billing_period_end']);
        $this->assertEquals(25000.00, $calc['rent_amount']);

        $invoice = $rentBilling->generateRentInvoice($agreement, 9, 2026);
        $this->assertEquals('2026-09-01', $invoice->billing_period_start->toDateString());
        $this->assertEquals('2026-09-30', $invoice->billing_period_end->toDateString());
        $this->assertEquals('01 Sep 2026 – 30 Sep 2026', $invoice->billing_period_formatted);
        $this->assertEquals(25000.00, $invoice->grand_total);
    }

    /**
     * Test 4: Bulk Generation Preview and bulk execution with idempotency.
     */
    public function test_bulk_generation_preview_and_execution()
    {
        // Agr 1: August handover
        $agr1 = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-A1',
            'rent_amount' => 20000.00,
            'security_deposit' => 40000.00,
            'status' => 'active',
            'start_date' => '2026-08-10',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-08-10',
        ]);
        TenancyRole::create(['tenancy_agreement_id' => $agr1->id, 'party_id' => $this->tenant->id, 'role_type' => 'Primary Tenant', 'is_primary' => true]);

        // Agr 2: Future handover in September (should be skipped for August)
        $agr2 = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-A2',
            'rent_amount' => 18000.00,
            'security_deposit' => 36000.00,
            'status' => 'active',
            'start_date' => '2026-09-01',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-09-01',
        ]);
        TenancyRole::create(['tenancy_agreement_id' => $agr2->id, 'party_id' => $this->tenant->id, 'role_type' => 'Primary Tenant', 'is_primary' => true]);

        $rentBilling = app(RentBillingService::class);

        // Preview for August 2026
        $preview = $rentBilling->getBulkGenerationPreview(8, 2026);
        $this->assertEquals(1, $preview['summary']['ready_count']);
        $this->assertEquals(1, $preview['summary']['ineligible_count']);

        // Execute bulk generation
        $count = $rentBilling->bulkGenerateRentInvoices(8, 2026);
        $this->assertEquals(1, $count);

        // Verify invoice was created for Agr 1
        $this->assertTrue(Invoice::where('reference_id', $agr1->id)->exists());
        $this->assertFalse(Invoice::where('reference_id', $agr2->id)->exists());

        // Re-running preview should show 0 ready and 1 already generated
        $previewAfter = $rentBilling->getBulkGenerationPreview(8, 2026);
        $this->assertEquals(0, $previewAfter['summary']['ready_count']);
        $this->assertEquals(1, $previewAfter['summary']['already_generated_count']);

        // Bulk generation should generate 0 invoices on re-run (idempotent)
        $countReRun = $rentBilling->bulkGenerateRentInvoices(8, 2026);
        $this->assertEquals(0, $countReRun);
    }

    /**
     * Test 5: Invoice PDF template streams correctly with Billing Period.
     */
    public function test_invoice_pdf_contains_billing_period()
    {
        $agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-PDF',
            'rent_amount' => 15000.00,
            'security_deposit' => 30000.00,
            'status' => 'active',
            'start_date' => '2026-08-10',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-08-10',
        ]);
        TenancyRole::create(['tenancy_agreement_id' => $agreement->id, 'party_id' => $this->tenant->id, 'role_type' => 'Primary Tenant', 'is_primary' => true]);

        $rentBilling = app(RentBillingService::class);
        $invoice = $rentBilling->generateRentInvoice($agreement, 8, 2026);

        $invoice->loadMissing(['items.tax', 'items.item', 'contact', 'branch.organization']);

        $view = view('accounting::pdf.invoice', ['invoice' => $invoice])->render();
        $this->assertStringContainsString('Billing Period:', $view);
        $this->assertStringContainsString('10 Aug 2026 – 31 Aug 2026', $view);
    }

    /**
     * Test 6: Bulk generation with selective agreement IDs generates invoices only for selected tenancies.
     */
    public function test_bulk_generation_with_selective_agreement_ids()
    {
        $agr1 = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-SEL1',
            'rent_amount' => 20000.00,
            'security_deposit' => 40000.00,
            'status' => 'active',
            'start_date' => '2026-08-01',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-08-01',
        ]);
        TenancyRole::create(['tenancy_agreement_id' => $agr1->id, 'party_id' => $this->tenant->id, 'role_type' => 'Primary Tenant', 'is_primary' => true]);

        $agr2 = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-SEL2',
            'rent_amount' => 22000.00,
            'security_deposit' => 44000.00,
            'status' => 'active',
            'start_date' => '2026-08-01',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-08-01',
        ]);
        TenancyRole::create(['tenancy_agreement_id' => $agr2->id, 'party_id' => $this->tenant->id, 'role_type' => 'Primary Tenant', 'is_primary' => true]);

        $rentBilling = app(RentBillingService::class);

        // Preview should show 2 ready
        $preview = $rentBilling->getBulkGenerationPreview(8, 2026);
        $this->assertEquals(2, $preview['summary']['ready_count']);

        // Pass only $agr1->id to selective bulk generation
        $count = $rentBilling->bulkGenerateRentInvoices(8, 2026, [$agr1->id]);
        $this->assertEquals(1, $count);

        // Verify only agr1 has invoice
        $this->assertTrue(Invoice::where('reference_id', $agr1->id)->exists());
        $this->assertFalse(Invoice::where('reference_id', $agr2->id)->exists());

        // Subsequent preview should show 1 ready (agr2) and 1 already generated (agr1)
        $previewAfter = $rentBilling->getBulkGenerationPreview(8, 2026);
        $this->assertEquals(1, $previewAfter['summary']['ready_count']);
        $this->assertEquals(1, $previewAfter['summary']['already_generated_count']);
    }

    /**
     * Test 7: Property filter scopes both preview and bulk generation.
     */
    public function test_bulk_generation_preview_and_execution_filtered_by_property()
    {
        $property2 = Property::create([
            'building_name' => 'Green Heights Villa 202',
            'code' => 'PROP-202',
            'status' => 'occupied',
        ]);

        $agr1 = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-P1',
            'rent_amount' => 20000.00,
            'security_deposit' => 40000.00,
            'status' => 'active',
            'start_date' => '2026-08-01',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-08-01',
        ]);
        TenancyRole::create(['tenancy_agreement_id' => $agr1->id, 'party_id' => $this->tenant->id, 'role_type' => 'Primary Tenant', 'is_primary' => true]);

        $agr2 = TenancyAgreement::create([
            'property_id' => $property2->id,
            'code' => 'AGR-2026-P2',
            'rent_amount' => 25000.00,
            'security_deposit' => 50000.00,
            'status' => 'active',
            'start_date' => '2026-08-01',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-08-01',
        ]);
        TenancyRole::create(['tenancy_agreement_id' => $agr2->id, 'party_id' => $this->tenant->id, 'role_type' => 'Primary Tenant', 'is_primary' => true]);

        $rentBilling = app(RentBillingService::class);

        // Preview filtered by Property 2
        $previewProp2 = $rentBilling->getBulkGenerationPreview(8, 2026, $property2->id);
        $this->assertEquals(1, $previewProp2['summary']['total_agreements']);
        $this->assertEquals(1, $previewProp2['summary']['ready_count']);
        $this->assertEquals($agr2->id, $previewProp2['items'][0]['agreement_id']);

        // Bulk generate filtered by Property 2
        $count = $rentBilling->bulkGenerateRentInvoices(8, 2026, null, $property2->id);
        $this->assertEquals(1, $count);

        $this->assertFalse(Invoice::where('reference_id', $agr1->id)->exists());
        $this->assertTrue(Invoice::where('reference_id', $agr2->id)->exists());
    }
}
