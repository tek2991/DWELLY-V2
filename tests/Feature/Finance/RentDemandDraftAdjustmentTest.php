<?php

namespace Tests\Feature\Finance;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenancyRole;
use App\Domain\Finance\Services\RentBillingService;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Services\MaintenanceBillingService;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Pages\Billing\BulkGenerateMonthlyRent;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class RentDemandDraftAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $user;
    protected Party $owner;
    protected Party $tenant;
    protected Property $property;
    protected TenancyAgreement $agreement;
    protected RentBillingService $service;

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

        $this->service = app(RentBillingService::class);
    }

    /**
     * Test 1: Can save draft rent demand with custom adjustments and retrieve details.
     */
    public function test_can_save_draft_rent_demand_with_custom_adjustments_and_retrieve_details(): void
    {
        $adjusted = [
            'rent_amount' => 28000.00,
            'utility_amount' => 1500.00,
            'notes' => 'Negotiated temporary rent concession + water charges for August.',
        ];

        $draft = $this->service->saveDraftRentDemand($this->agreement, 8, 2026, $adjusted, $this->user);

        $this->assertNotNull($draft);
        $this->assertEquals(InvoiceStatus::Draft, $draft->status);
        $this->assertStringStartsWith('DRAFT-', $draft->invoice_number);
        $this->assertEquals(29500.00, (float) $draft->grand_total);
        $this->assertEquals('Negotiated temporary rent concession + water charges for August.', $draft->notes);

        // Verify calculateBillingDetails uses the draft
        $details = $this->service->calculateBillingDetails($this->agreement, 8, 2026);
        $this->assertTrue($details['is_adjusted']);
        $this->assertEquals($draft->id, $details['draft_invoice_id']);
        $this->assertEquals(28000.00, $details['rent_amount']);
        $this->assertEquals(1500.00, $details['utility_amount']);
        $this->assertEquals(29500.00, $details['total_amount']);
        $this->assertEquals('Negotiated temporary rent concession + water charges for August.', $details['notes']);
    }

    /**
     * Test 2: getBulkGenerationPreview reflects draft with amber badge and ready status.
     */
    public function test_get_bulk_generation_preview_shows_draft_with_adjusted_badge(): void
    {
        $this->service->saveDraftRentDemand($this->agreement, 8, 2026, [
            'rent_amount' => 27500.00,
            'utility_amount' => 800.00,
        ], $this->user);

        $preview = $this->service->getBulkGenerationPreview(8, 2026);

        $this->assertEquals(1, $preview['summary']['ready_count']);
        $this->assertEquals(0, $preview['summary']['already_generated_count']);

        $item = $preview['items'][0];
        $this->assertEquals('ready', $item['status']);
        $this->assertTrue($item['is_adjusted']);
        $this->assertEquals('Ready • Adjusted', $item['status_label']);
        $this->assertEquals('amber', $item['badge_color']);
        $this->assertEquals(27500.00, $item['rent_amount']);
        $this->assertEquals(800.00, $item['utility_amount']);
        $this->assertEquals(28300.00, $item['total_amount']);
    }

    /**
     * Test 3: Can reset draft rent demand back to defaults.
     */
    public function test_can_reset_draft_rent_demand_back_to_defaults(): void
    {
        $this->service->saveDraftRentDemand($this->agreement, 8, 2026, [
            'rent_amount' => 25000.00,
            'utility_amount' => 500.00,
        ], $this->user);

        $this->assertEquals(1, Invoice::where('reference_type', TenancyAgreement::class)->where('status', InvoiceStatus::Draft)->count());

        $reset = $this->service->resetDraftRentDemand($this->agreement, 8, 2026);
        $this->assertTrue($reset);
        $this->assertEquals(0, Invoice::where('reference_type', TenancyAgreement::class)->where('status', InvoiceStatus::Draft)->count());

        // Recalculated details should now reflect standard agreement rent (30000)
        $details = $this->service->calculateBillingDetails($this->agreement, 8, 2026);
        $this->assertFalse($details['is_adjusted']);
        $this->assertNull($details['draft_invoice_id']);
        $this->assertEquals(30000.00, $details['rent_amount']);
        $this->assertEquals(0.0, $details['utility_amount']);
        $this->assertEquals(30000.00, $details['total_amount']);
    }

    /**
     * Test 4: Generating demand uses draft and transitions to sent with official invoice number.
     */
    public function test_generating_demand_uses_draft_and_transitions_to_sent_with_official_invoice_number(): void
    {
        $draft = $this->service->saveDraftRentDemand($this->agreement, 8, 2026, [
            'rent_amount' => 28500.00,
            'utility_amount' => 1000.00,
            'notes' => 'Custom staged rent demand override',
        ], $this->user);

        $draftId = $draft->id;
        $this->assertStringStartsWith('DRAFT-', $draft->invoice_number);

        // Generate demand
        $generatedInvoice = $this->service->generateRentDemand($this->agreement, 8, 2026);

        $this->assertEquals($draftId, $generatedInvoice->id);
        $this->assertEquals(InvoiceStatus::Sent, $generatedInvoice->status);
        $this->assertStringStartsNotWith('DRAFT-', $generatedInvoice->invoice_number);
        $this->assertEquals(29500.00, (float) $generatedInvoice->grand_total);
        $this->assertEquals('Custom staged rent demand override', $generatedInvoice->notes);
        $this->assertNotNull($generatedInvoice->pdf_path);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($generatedInvoice->pdf_path));

        // Check preview now marks it as already generated
        $preview = $this->service->getBulkGenerationPreview(8, 2026);
        $this->assertEquals(0, $preview['summary']['ready_count']);
        $this->assertEquals(1, $preview['summary']['already_generated_count']);
    }

    /**
     * Test 5: Livewire component saveDemandAdjustment and resetDemandAdjustment methods.
     */
    public function test_livewire_page_save_and_reset_demand_adjustments(): void
    {
        Livewire::test(BulkGenerateMonthlyRent::class)
            ->set('month', 8)
            ->set('year', 2026)
            ->call('saveDemandAdjustment', (string) $this->agreement->id, [
                'rent_amount' => 26000.00,
                'utility_amount' => 1200.00,
                'notes' => 'Adjustment saved via Livewire UI',
            ])
            ->assertHasNoErrors()
            ->assertSee('Ready • Adjusted');

        $draft = Invoice::where('reference_type', TenancyAgreement::class)->where('status', InvoiceStatus::Draft)->first();
        $this->assertNotNull($draft);
        $this->assertEquals(27200.00, (float) $draft->grand_total);
        $this->assertEquals('Adjustment saved via Livewire UI', $draft->notes);

        // Reset via Livewire
        Livewire::test(BulkGenerateMonthlyRent::class)
            ->set('month', 8)
            ->set('year', 2026)
            ->call('resetDemandAdjustment', (string) $this->agreement->id)
            ->assertHasNoErrors()
            ->assertSee('Ready to Bill')
            ->assertDontSee('Ready • Adjusted');

        $this->assertEquals(0, Invoice::where('reference_type', TenancyAgreement::class)->where('status', InvoiceStatus::Draft)->count());
    }

    /**
     * Test 6: Draft preserves selected maintenance invoices without settling them until generation.
     */
    public function test_draft_preserves_selected_maintenance_invoices_without_settling_until_generation(): void
    {
        $maintRequest = MaintenanceRequest::create([
            'ticket_number' => 'TKT-2026-MAG-01',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Geyser Repair',
            'category' => 'plumbing',
            'status' => 'resolved',
            'tenant_amount' => 2500.00,
            'total_cost' => 2500.00,
        ]);

        $maintInvoice = app(MaintenanceBillingService::class)->createMaintenanceInvoice($maintRequest, 'tenant_invoice');
        $this->assertEquals(InvoiceStatus::Draft, $maintInvoice->status);

        // Save draft selecting this maintenance invoice
        $draft = $this->service->saveDraftRentDemand($this->agreement, 8, 2026, [
            'rent_amount' => 30000.00,
            'utility_amount' => 0.0,
            'selected_maintenance_invoice_ids' => [(int) $maintInvoice->id],
        ], $this->user);

        $this->assertEquals(32500.00, (float) $draft->grand_total);

        // Underlying maintenance invoice MUST remain Draft / unpaid while rent demand is only a draft
        $maintInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Draft, $maintInvoice->status);
        $this->assertEquals(2500.00, (float) $maintInvoice->balance_due);

        // Now generate the demand
        $demand = $this->service->generateRentDemand($this->agreement, 8, 2026);
        $this->assertEquals(32500.00, (float) $demand->grand_total);
        $this->assertEquals(InvoiceStatus::Sent, $demand->status);

        // Underlying maintenance invoice remains open upon demand generation
        $maintInvoice->refresh();
        $this->assertNotEquals(InvoiceStatus::Paid, $maintInvoice->status);

        // Record payment against the rent demand
        $this->service->recordPayment($demand, 32500.00);

        // Now underlying maintenance invoice MUST be settled as Paid
        $maintInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $maintInvoice->status);
        $this->assertEquals(0.0, (float) $maintInvoice->balance_due);
    }
}
