<?php

namespace Tests\Feature\Finance;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenancyRole;
use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Finance\Services\OwnerPayoutService;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Services\MaintenanceBillingService;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Pages\Billing\BulkGenerateOwnerPayouts;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class BulkGenerateOwnerPayoutsPageTest extends TestCase
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
     * Test 1: BulkGenerateOwnerPayouts page mounts and renders accurately.
     */
    public function test_bulk_generate_owner_payouts_page_mounts_and_renders()
    {
        Livewire::test(BulkGenerateOwnerPayouts::class)
            ->assertStatus(200)
            ->assertSee('Bulk Disburse Owner Payouts')
            ->assertSee('Magnolia Residences 402')
            ->assertSee('Dr. Sharma (Owner)');
    }

    /**
     * Test 2: Cycle navigation and calculations in BulkGenerateOwnerPayouts page.
     */
    public function test_bulk_generate_owner_payouts_cycle_navigation_and_metrics()
    {
        Livewire::test(BulkGenerateOwnerPayouts::class)
            ->set('month', 8)
            ->set('year', 2026)
            ->assertSee('Magnolia Residences 402')
            ->assertSee('Ready to Disburse')
            ->assertSee('₹27,000.00'); // 30000 - 3000 (10% fee)
    }

    /**
     * Test 3: Disburse selected owner payouts from custom page.
     */
    public function test_bulk_generate_owner_payouts_disburses_selected_properties()
    {
        // Add a maintenance ticket to verify deduction & settlement
        $maintRequest = MaintenanceRequest::create([
            'ticket_number' => 'TKT-2026-08-005',
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'title' => 'Kitchen Tap Repair',
            'category' => 'plumbing',
            'status' => 'resolved',
            'owner_amount' => 1500.00,
            'total_cost' => 1500.00,
        ]);
        $inv = app(MaintenanceBillingService::class)->createMaintenanceInvoice($maintRequest, 'owner_invoice');

        Livewire::test(BulkGenerateOwnerPayouts::class)
            ->set('month', 8)
            ->set('year', 2026)
            ->call('refreshSelectedProperties')
            ->call('disburseSelected')
            ->assertSuccessful();

        // Assert OwnerPayout created
        $payout = OwnerPayout::where('property_id', $this->property->id)->first();
        $this->assertNotNull($payout);
        $this->assertEquals(25500.00, $payout->amount); // 30000 - 3000 - 1500
        $this->assertEquals(1500.00, $payout->advance_offset);
        $this->assertNotNull($payout->commission_invoice_id);

        // Assert Maintenance Invoice marked as Paid
        $inv->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $inv->status);
    }

    /**
     * Test 4: Pagination and per-page selector preserves global selection across pages.
     */
    public function test_bulk_generate_owner_payouts_pagination_and_per_page_controls()
    {
        // Create 30 additional properties and agreements
        for ($i = 1; $i <= 30; $i++) {
            $prop = Property::create([
                'building_name' => "Emerald Heights Unit {$i}",
                'code' => "PROP-EM-{$i}",
                'status' => 'occupied',
            ]);
            $opp = \App\Domain\Opportunity\Models\Opportunity::create([
                'number' => "OPP-2026-EM-{$i}",
                'title' => "Emerald Lead {$i}",
                'status' => 'converted',
            ]);
            \App\Domain\Mou\Models\Mou::create([
                'number' => "MOU-2026-EM-{$i}",
                'property_id' => $prop->id,
                'opportunity_id' => $opp->id,
                'party_id' => $this->owner->id,
                'type' => 'onboarding',
                'status' => 'verified',
            ]);
            $agr = TenancyAgreement::create([
                'property_id' => $prop->id,
                'code' => "AGR-2026-EM-{$i}",
                'rent_amount' => 20000.00,
                'security_deposit' => 40000.00,
                'status' => 'active',
                'start_date' => '2026-08-01',
                'keys_handed_over' => true,
                'keys_handed_over_at' => '2026-08-01',
            ]);
            TenancyRole::create([
                'tenancy_agreement_id' => $agr->id,
                'party_id' => $this->tenant->id,
                'role_type' => 'Primary Tenant',
                'is_primary' => true,
            ]);
        }

        $component = Livewire::test(BulkGenerateOwnerPayouts::class)
            ->set('month', 8)
            ->set('year', 2026)
            ->set('perPage', 10)
            ->call('refreshSelectedProperties');

        // Total properties: 1 (original) + 30 = 31
        $summary = $component->instance()->getSelectedSummary();
        $this->assertEquals(31, $summary['count']); // Global selection has all 31 ready properties

        // Paginated items on page 1 should be 10
        $paginated = $component->instance()->getPaginatedItems();
        $this->assertEquals(31, $paginated->total());
        $this->assertEquals(10, $paginated->perPage());
        $this->assertCount(10, $paginated->items());
        $this->assertEquals(4, $paginated->lastPage()); // 31 / 10 = 4 pages
    }
}
