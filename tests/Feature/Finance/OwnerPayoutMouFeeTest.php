<?php

namespace Tests\Feature\Finance;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenancyRole;
use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Finance\Services\OwnerPayoutService;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Models\PropertyFinancialTerm;
use App\Filament\Pages\Billing\BulkGenerateOwnerPayouts;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class OwnerPayoutMouFeeTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $user;
    protected Party $owner;
    protected Party $tenant;
    protected Property $property;
    protected TenancyAgreement $agreement;
    protected Opportunity $opp;

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
            'display_name' => 'Rajesh Sharma (Owner)',
            'email' => 'rajesh@example.com',
            'phone' => '9876543210',
        ]);
        $this->owner->ownerProfile()->create([]);
        $this->owner->individual()->create(['name' => 'Rajesh Sharma', 'pan_number' => 'ABCDE1234F']);

        $this->tenant = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Amit Kumar (Tenant)',
            'email' => 'amit@example.com',
            'phone' => '9876543211',
        ]);
        $this->tenant->tenantProfile()->create([]);
        $this->tenant->individual()->create(['name' => 'Amit Kumar', 'pan_number' => 'FGHIJ5678K']);

        $this->property = Property::create([
            'building_name' => 'Lotus Heights 501',
            'code' => 'PROP-LOT-501',
            'status' => 'occupied',
        ]);

        $this->opp = Opportunity::create([
            'number' => 'OPP-LOT-501',
            'title' => 'Lotus Heights Lead',
            'status' => 'converted',
        ]);

        $this->agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-LOT-501',
            'rent_amount' => 50000.00,
            'security_deposit' => 100000.00,
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
     * Test 1: Management fee percentage is dynamically fetched from MOU legal_terms.
     */
    public function test_fee_percent_is_fetched_from_mou_legal_terms()
    {
        Mou::create([
            'number' => 'MOU-LOT-8PCT',
            'property_id' => $this->property->id,
            'opportunity_id' => $this->opp->id,
            'party_id' => $this->owner->id,
            'type' => 'onboarding',
            'status' => 'verified',
            'legal_terms' => [
                'fee_percentage' => 8.0,
            ],
        ]);

        $service = app(OwnerPayoutService::class);
        $percent = $service->getManagementFeePercent($this->property);
        $this->assertEquals(8.0, $percent);

        $details = $service->calculatePayoutDetails($this->property, 8, 2026);
        $this->assertEquals(8.0, $details['management_fee_percent']);
        $this->assertEquals(4000.00, $details['management_fee']); // 8% of 50,000
        $this->assertEquals(46000.00, $details['net_payout']); // 50,000 - 4,000
    }

    /**
     * Test 2: When MOU has 12.5% fee percentage, calculatePayoutDetails applies 12.5%.
     */
    public function test_fee_percent_with_custom_decimal_percentage()
    {
        Mou::create([
            'number' => 'MOU-LOT-12PT5',
            'property_id' => $this->property->id,
            'opportunity_id' => $this->opp->id,
            'party_id' => $this->owner->id,
            'type' => 'onboarding',
            'status' => 'verified',
            'legal_terms' => [
                'fee_percentage' => 12.5,
            ],
        ]);

        $service = app(OwnerPayoutService::class);
        $details = $service->calculatePayoutDetails($this->property, 8, 2026);

        $this->assertEquals(12.5, $details['management_fee_percent']);
        $this->assertEquals(6250.00, $details['management_fee']); // 12.5% of 50,000
        $this->assertEquals(43750.00, $details['net_payout']);
    }

    /**
     * Test 3: Committed PropertyFinancialTerm overrides or provides fee percentage.
     */
    public function test_fee_percent_from_property_financial_term()
    {
        $mou = Mou::create([
            'number' => 'MOU-LOT-PRICING-UPD',
            'property_id' => $this->property->id,
            'opportunity_id' => $this->opp->id,
            'party_id' => $this->owner->id,
            'type' => 'pricing_update',
            'status' => 'verified',
            'legal_terms' => [
                'fee_percentage' => 6.0,
            ],
        ]);

        PropertyFinancialTerm::create([
            'property_id' => $this->property->id,
            'mou_id' => $mou->id,
            'pricing_model' => 'Standard',
            'fee_percentage' => 6.00,
            'effective_from' => '2026-08-01',
        ]);

        $service = app(OwnerPayoutService::class);
        $details = $service->calculatePayoutDetails($this->property, 8, 2026);

        $this->assertEquals(6.0, $details['management_fee_percent']);
        $this->assertEquals(3000.00, $details['management_fee']); // 6% of 50,000
        $this->assertEquals(47000.00, $details['net_payout']);
    }

    /**
     * Test 4: Default fallback of 10% when MOU has no fee percentage defined.
     */
    public function test_fallback_to_10_percent_when_mou_has_no_fee()
    {
        Mou::create([
            'number' => 'MOU-LOT-NOFEE',
            'property_id' => $this->property->id,
            'opportunity_id' => $this->opp->id,
            'party_id' => $this->owner->id,
            'type' => 'onboarding',
            'status' => 'verified',
            'legal_terms' => [],
        ]);

        $service = app(OwnerPayoutService::class);
        $details = $service->calculatePayoutDetails($this->property, 8, 2026);

        $this->assertEquals(10.0, $details['management_fee_percent']);
        $this->assertEquals(5000.00, $details['management_fee']);
        $this->assertEquals(45000.00, $details['net_payout']);
    }

    /**
     * Test 5: Bulk disburse UI reflects MOU fee percentage in preview and disburses correctly.
     */
    public function test_bulk_generate_owner_payouts_page_shows_and_disburses_mou_fee()
    {
        Mou::create([
            'number' => 'MOU-LOT-7PCT',
            'property_id' => $this->property->id,
            'opportunity_id' => $this->opp->id,
            'party_id' => $this->owner->id,
            'type' => 'onboarding',
            'status' => 'verified',
            'legal_terms' => [
                'fee_percentage' => 7.0,
            ],
        ]);

        // Livewire bulk page check
        Livewire::test(BulkGenerateOwnerPayouts::class)
            ->set('month', 8)
            ->set('year', 2026)
            ->assertSee('Lotus Heights 501')
            ->assertSee('7% MOU')
            ->assertSee('₹3,500.00') // 7% fee on 50,000
            ->assertSee('₹46,500.00') // Net payout 50,000 - 3,500
            ->call('disburseSelected');

        $payout = OwnerPayout::where('property_id', $this->property->id)->first();
        $this->assertNotNull($payout);
        $this->assertEquals(3500.00, (float) $payout->management_fee);
        $this->assertEquals(46500.00, (float) $payout->amount);

        // Verify commission invoice was generated with correct amount
        $this->assertNotNull($payout->commission_invoice_id);
        $this->assertEquals(3500.00, (float) $payout->commissionInvoice->grand_total);
    }
}
