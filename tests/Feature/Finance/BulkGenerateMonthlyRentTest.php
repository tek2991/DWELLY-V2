<?php

namespace Tests\Feature\Finance;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenancyRole;
use App\Domain\Finance\Services\RentBillingService;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Pages\Billing\BulkGenerateMonthlyRent;
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

class BulkGenerateMonthlyRentTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $user;
    protected Party $owner;
    protected Party $tenant1;
    protected Party $tenant2;
    protected Property $property;
    protected TenancyAgreement $agreement1;
    protected TenancyAgreement $agreement2;

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
            'display_name' => 'Anthony Owner',
            'email' => 'anthony@example.com',
            'phone' => '9876543210',
        ]);
        $this->owner->ownerProfile()->create([]);
        $this->owner->individual()->create(['name' => 'Anthony', 'pan_number' => 'ABCDE1234F']);

        $this->tenant1 = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Bruce Wayne',
            'email' => 'bruce@wayne.corp',
            'phone' => '9123456789',
        ]);
        $this->tenant1->tenantProfile()->create([]);
        $this->tenant1->individual()->create(['name' => 'Bruce', 'pan_number' => 'XYZPW1234K']);

        $this->tenant2 = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Clark Kent',
            'email' => 'clark@dailyplanet.com',
            'phone' => '9988776655',
        ]);
        $this->tenant2->tenantProfile()->create([]);
        $this->tenant2->individual()->create(['name' => 'Clark', 'pan_number' => 'SUPER1234Z']);

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

        $this->agreement1 = TenancyAgreement::create([
            'code' => 'AGR-2026-001',
            'property_id' => $this->property->id,
            'security_deposit' => 60000.00,
            'rent_amount' => 20000.00,
            'status' => 'active',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-01-01 10:00:00',
        ]);

        TenancyRole::create([
            'tenancy_agreement_id' => $this->agreement1->id,
            'party_id' => $this->tenant1->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        $this->agreement2 = TenancyAgreement::create([
            'code' => 'AGR-2026-002',
            'property_id' => $this->property->id,
            'security_deposit' => 90000.00,
            'rent_amount' => 30000.00,
            'status' => 'active',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-01-01 10:00:00',
        ]);

        TenancyRole::create([
            'tenancy_agreement_id' => $this->agreement2->id,
            'party_id' => $this->tenant2->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);
    }

    public function test_page_renders_successfully(): void
    {
        Livewire::test(BulkGenerateMonthlyRent::class)
            ->assertSuccessful()
            ->assertSee('Bulk Generate Monthly Rent')
            ->assertSee('Active Tenancies')
            ->assertSee('Ready to Generate')
            ->assertSee('Already Invoiced')
            ->assertSee('AGR-2026-001')
            ->assertSee('AGR-2026-002')
            ->assertSee('Bruce Wayne')
            ->assertSee('Clark Kent');
    }

    public function test_month_navigation_controls(): void
    {
        $component = Livewire::test(BulkGenerateMonthlyRent::class);
        $initialMonth = $component->get('month');

        $component->call('nextMonth');
        $expectedMonth = $initialMonth === 12 ? 1 : $initialMonth + 1;
        $this->assertEquals($expectedMonth, $component->get('month'));

        $component->call('previousMonth');
        $this->assertEquals($initialMonth, $component->get('month'));

        $component->call('currentMonth');
        $this->assertEquals((int) date('n'), $component->get('month'));
    }

    public function test_selection_and_deselection_methods(): void
    {
        $component = Livewire::test(BulkGenerateMonthlyRent::class);
        
        $this->assertContains((string) $this->agreement1->id, $component->get('selectedAgreements'));
        $this->assertContains((string) $this->agreement2->id, $component->get('selectedAgreements'));

        $component->call('deselectAll');
        $this->assertEmpty($component->get('selectedAgreements'));

        $component->call('selectAllReady');
        $this->assertCount(2, $component->get('selectedAgreements'));

        $component->call('toggleAgreement', (string) $this->agreement1->id);
        $this->assertNotContains((string) $this->agreement1->id, $component->get('selectedAgreements'));
        $this->assertContains((string) $this->agreement2->id, $component->get('selectedAgreements'));
    }

    public function test_single_rent_generation(): void
    {
        $component = Livewire::test(BulkGenerateMonthlyRent::class);
        $component->set('month', 8);
        $component->set('year', 2026);

        $component->call('generateSingle', (string) $this->agreement1->id);

        $invoice = Invoice::where('reference_type', TenancyAgreement::class)
            ->where('reference_id', $this->agreement1->id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertEquals(20000.00, (float) $invoice->grand_total);
        $this->assertEquals(InvoiceStatus::Sent, $invoice->status);
        $this->assertNotNull($invoice->transaction_id);
    }

    public function test_bulk_generation_for_selected_agreements(): void
    {
        $component = Livewire::test(BulkGenerateMonthlyRent::class);
        $component->set('month', 8);
        $component->set('year', 2026);
        $component->set('selectedAgreements', [(string) $this->agreement1->id, (string) $this->agreement2->id]);

        $component->call('generateSelected');

        $invoices = Invoice::where('reference_type', TenancyAgreement::class)->get();
        $this->assertCount(2, $invoices);

        $summary = $component->get('lastGenerationSummary');
        $this->assertNotNull($summary);
        $this->assertEquals(2, $summary['count']);
        $this->assertEquals(50000.00, $summary['total_amount']);
    }

    public function test_search_and_status_filtering(): void
    {
        $component = Livewire::test(BulkGenerateMonthlyRent::class);
        $component->set('search', 'Clark Kent');

        $filtered = $component->instance()->getFilteredItems();
        $this->assertCount(1, $filtered);
        $this->assertEquals('AGR-2026-002', $filtered[0]['agreement_code']);

        $component->set('search', '');
        $component->set('statusFilter', 'ready');
        $filtered = $component->instance()->getFilteredItems();
        $this->assertCount(2, $filtered);
    }

    public function test_already_generated_invoices_are_detected(): void
    {
        $service = app(RentBillingService::class);
        $service->generateRentInvoice($this->agreement1, 8, 2026);

        $component = Livewire::test(BulkGenerateMonthlyRent::class);
        $component->set('month', 8);
        $component->set('year', 2026);

        $preview = $component->instance()->getPreviewData();
        $this->assertEquals(1, $preview['summary']['already_generated_count']);
        $this->assertEquals(1, $preview['summary']['ready_count']);
    }

    public function test_monthly_demand_notice_data_compilation(): void
    {
        $service = app(RentBillingService::class);
        $demand = $service->generateRentDemand($this->agreement1, 8, 2026);

        $noticeData = $service->getMonthlyDemandNoticeData($demand);

        $this->assertNotNull($noticeData);
        $this->assertEquals(20000.00, $noticeData['current_demand']);
        $this->assertEquals(20000.00, $noticeData['balance_due']);
        $this->assertEquals(0.00, $noticeData['previous_balance']);
        $this->assertEquals(20000.00, $noticeData['total_payable']);
        $this->assertEquals('Anthony Owner', $noticeData['owner']->display_name);
        $this->assertEquals('Bruce Wayne', $noticeData['tenant']->display_name);

        // Verify Document Snapshot is persisted in database
        $this->assertNotNull($demand->document_snapshot);
        $this->assertIsArray($demand->document_snapshot);
        $this->assertEquals('rent_demand_notice', $demand->document_snapshot['document_type']);
        $this->assertEquals('Bruce Wayne', $demand->document_snapshot['tenant']['display_name']);
        $this->assertEquals('Anthony Owner', $demand->document_snapshot['owner']['display_name']);
        $this->assertEquals(20000.00, $demand->document_snapshot['current_demand']);

        // Verify Immutable PDF file was generated and stored on disk
        $this->assertNotNull($demand->pdf_path);
        $this->assertNotNull($demand->pdf_checksum);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($demand->pdf_path));
        $this->assertTrue($demand->hasStoredPdf());
    }

    public function test_demand_notice_pdf_and_receipt_routes(): void
    {
        $service = app(RentBillingService::class);
        $demand = $service->generateRentDemand($this->agreement1, 8, 2026);

        // Test Demand Notice PDF stream from stored immutable file
        $response = $this->get(route('billing.demand.pdf', ['invoice' => $demand->id]));
        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));

        // Test Record Payment and Receipt PDF stream
        $payment = $service->recordPayment($demand, 20000.00, null, '2026-08-05', 'UTR99887766', 'Rent payment');
        $this->assertNotNull($payment);

        $receiptResponse = $this->get(route('billing.receipt.pdf', ['invoice' => $demand->id, 'payment' => $payment->id]));
        $receiptResponse->assertStatus(200);
        $this->assertEquals('application/pdf', $receiptResponse->headers->get('content-type'));
    }

    public function test_tenant_maintenance_charges_recovery_in_monthly_rent_demand(): void
    {
        // 1. Create a maintenance request billed to tenant
        $maintRequest = \App\Domain\Maintenance\Models\MaintenanceRequest::create([
            'ticket_number' => 'TKT-2026-08-TENANT-1',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant1->id,
            'title' => 'Broken Window Glass',
            'category' => 'carpentry',
            'status' => 'resolved',
            'tenant_amount' => 1200.00,
            'total_cost' => 1200.00,
        ]);

        $maintInvoice = app(\App\Domain\Maintenance\Services\MaintenanceBillingService::class)->createMaintenanceInvoice($maintRequest, 'tenant_invoice');
        $this->assertNotNull($maintInvoice);
        $this->assertEquals(InvoiceStatus::Draft, $maintInvoice->status);

        // 2. Billing preview should detect the maintenance charge
        $service = app(RentBillingService::class);
        $preview = $service->getBulkGenerationPreview(8, 2026);
        $item1 = collect($preview['items'])->firstWhere('agreement_id', $this->agreement1->id);

        $this->assertNotNull($item1);
        $this->assertEquals(20000.00, $item1['rent_amount']);
        $this->assertEquals(1200.00, $item1['maintenance_amount']);
        $this->assertEquals(21200.00, $item1['total_amount']);
        $this->assertCount(1, $item1['maintenance_invoices']);

        // 3. Generate rent demand
        $demand = $service->generateRentDemand($this->agreement1, 8, 2026);

        // Verify grand total includes maintenance recovery
        $this->assertEquals(21200.00, $demand->grand_total);
        $this->assertEquals(InvoiceStatus::Sent, $demand->status);

        // Verify itemized line item
        $maintLine = $demand->items->first(fn ($li) => str_contains($li->description, 'TKT-2026-08-TENANT-1'));
        $this->assertNotNull($maintLine);
        $this->assertEquals(1200.00, $maintLine->line_total);

        // Verify underlying maintenance invoice is settled as Paid
        $maintInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $maintInvoice->status);
        $this->assertEquals(0.0, $maintInvoice->balance_due);
    }
}

